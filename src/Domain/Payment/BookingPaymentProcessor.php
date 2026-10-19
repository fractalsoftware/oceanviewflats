<?php

declare(strict_types=1);

namespace OceanViewFlats\Domain\Payment;

use DateTimeImmutable;
use DateTimeZone;
use InvalidArgumentException;
use OceanViewFlats\Domain\Fulfillment\BookingFulfillment;
use OceanViewFlats\Domain\Fulfillment\BookingFulfillmentInterface;
use OceanViewFlats\Domain\Fulfillment\EmailSenderInterface;
use OceanViewFlats\Domain\Fulfillment\PhpMailSender;
use OceanViewFlats\Domain\Quote\Quote;
use OceanViewFlats\Domain\Quote\QuoteEngine;
use OceanViewFlats\Domain\Quote\QuoteEngineInterface;
use OceanViewFlats\Domain\Reservation\ActorContext;
use OceanViewFlats\Domain\Reservation\DirectHoldRequest;
use OceanViewFlats\Domain\Reservation\DraftPaymentDetails;
use OceanViewFlats\Domain\Reservation\PdoReservationRepository;
use OceanViewFlats\Domain\Reservation\PrimaryGuest;
use OceanViewFlats\Domain\Reservation\Reservation;
use OceanViewFlats\Domain\Reservation\ReservationDraft;
use OceanViewFlats\Domain\Reservation\ReservationLedger;
use OceanViewFlats\Domain\Reservation\ReservationLedgerInterface;
use OceanViewFlats\Domain\Reservation\ReservationLifecycleEngineInterface;
use OceanViewFlats\Infrastructure\Reservation\ReservationLifecycleEngineFactory;
use OceanViewFlats\Domain\Reservation\ReservationRepositoryInterface;
use OceanViewFlats\Domain\Reservation\ReservationStatus;
use OceanViewFlats\Domain\Support\PathResolver;
use PDO;
use Throwable;

/**
 * Direct Booking Payment Processor.
 * Orchestrates the full lifecycle of a direct guest checkout:
 * - Input validation & hygiene
 * - Calendar availability verification (channel blocks, maintenance blocks, reservation holds)
 * - Authoritative quote computation & minimum stay enforcement
 * - Idempotency double-charge shield
 * - Payment gateway charge dispatch
 * - Reservation persistence
 * - Post-payment fulfillment and email dispatch
 */
final class BookingPaymentProcessor implements BookingPaymentProcessorInterface
{
    /**
     * @var array<string, array<string, mixed>>
     */
    private readonly array $translations;

    private readonly PendingPaymentEmailRendererInterface $pendingEmailRenderer;

    private readonly ReservationLifecycleEngineInterface $lifecycleEngine;

    /**
     * @param PaymentGatewayInterface $gateway
     * @param PDO $pdo
     * @param ReservationLedgerInterface $ledger
     * @param QuoteEngineInterface $quoteEngine
     * @param ReservationRepositoryInterface $repository
     * @param BookingFulfillmentInterface $fulfillment
     * @param EmailSenderInterface $emailSender
     * @param string $publicSiteUrl
     * @param string $hostNotificationEmail
     * @param array<string, array<string, mixed>>|null $translations
     * @param PendingPaymentEmailRendererInterface|null $pendingEmailRenderer
     * @param ReservationLifecycleEngineInterface|null $lifecycleEngine
     */
    public function __construct(
        private readonly PaymentGatewayInterface $gateway,
        private readonly PDO $pdo,
        private readonly ReservationLedgerInterface $ledger,
        private readonly QuoteEngineInterface $quoteEngine,
        private readonly ReservationRepositoryInterface $repository,
        private readonly BookingFulfillmentInterface $fulfillment,
        private readonly EmailSenderInterface $emailSender,
        private readonly string $publicSiteUrl = 'https://oceanviewflats.com',
        private readonly string $hostNotificationEmail = 'reservas@oceanviewflats.com',
        ?array $translations = null,
        ?PendingPaymentEmailRendererInterface $pendingEmailRenderer = null,
        ?ReservationLifecycleEngineInterface $lifecycleEngine = null
    ) {
        $this->translations = $translations ?? PathResolver::loadTranslations();
        $this->pendingEmailRenderer = $pendingEmailRenderer ?? new PendingPaymentEmailRenderer($this->translations);
        $this->lifecycleEngine = $lifecycleEngine ?? ReservationLifecycleEngineFactory::create($this->pdo, [
            'ledger' => $this->ledger,
            'quoteEngine' => $this->quoteEngine,
            'repository' => $this->repository,
            'bookingFulfillment' => $this->fulfillment,
            'emailSender' => $this->emailSender,
        ]);
    }

    /**
     * Factory creating a processor with production defaults.
     *
     * @param PDO $pdo
     * @param array<string, mixed> $options
     * @return self
     */
    public static function createDefault(PDO $pdo, array $options = []): self
    {
        $publicSiteUrl = (string) ($options['publicSiteUrl'] ?? 'https://oceanviewflats.com');
        $hostNotificationEmail = (string) ($options['hostNotificationEmail'] ?? 'reservas@oceanviewflats.com');

        $gateway = $options['gateway'] ?? new MercadoPagoPaymentGateway(
            accessToken: (string) ($options['mpAccessToken'] ?? (getenv('MERCADOPAGO_ACCESS_TOKEN') ?: ''))
        );

        $ledger = $options['ledger'] ?? ReservationLedger::createDefault($pdo, $options['cacheDir'] ?? null);
        $quoteEngine = $options['quoteEngine'] ?? QuoteEngine::createDefault($options['csvPath'] ?? null);
        $repository = $options['repository'] ?? new PdoReservationRepository($pdo);
        $emailSender = $options['emailSender'] ?? new PhpMailSender();
        $fulfillment = $options['fulfillment'] ?? BookingFulfillment::createDefault($hostNotificationEmail, $emailSender);
        $translations = $options['translations'] ?? null;
        $pendingEmailRenderer = $options['pendingEmailRenderer'] ?? null;
        $lifecycleEngine = isset($options['lifecycleEngine']) && $options['lifecycleEngine'] instanceof ReservationLifecycleEngineInterface
            ? $options['lifecycleEngine']
            : null;

        return new self(
            gateway: $gateway,
            pdo: $pdo,
            ledger: $ledger,
            quoteEngine: $quoteEngine,
            repository: $repository,
            fulfillment: $fulfillment,
            emailSender: $emailSender,
            publicSiteUrl: $publicSiteUrl,
            hostNotificationEmail: $hostNotificationEmail,
            translations: $translations,
            pendingEmailRenderer: $pendingEmailRenderer,
            lifecycleEngine: $lifecycleEngine
        );
    }

    /**
     * @inheritDoc
     */
    public function processBookingPayment(BookingPaymentRequest $request): BookingPaymentResult
    {
        $lang = $request->lang;

        // 1. Input hygiene & validation
        $propertyId = trim($request->propertyId);
        if ($propertyId !== '1606' && $propertyId !== '1707') {
            return BookingPaymentResult::error(
                $this->trans($lang, 'booking', 'err_property', 'Invalid property selected.'),
                400
            );
        }

        $guestName = trim($request->guestName);
        if (strlen($guestName) < 3) {
            return BookingPaymentResult::error(
                $this->trans($lang, 'booking', 'err_name', 'Please enter your full name (minimum 3 characters).'),
                400
            );
        }

        $guestEmail = trim($request->guestEmail);
        if (!filter_var($guestEmail, FILTER_VALIDATE_EMAIL)) {
            return BookingPaymentResult::error(
                $this->trans($lang, 'booking', 'err_email', 'Please enter a valid email address.'),
                400
            );
        }

        $guestPhone = trim($request->guestPhone);
        if (strlen($guestPhone) < 6) {
            return BookingPaymentResult::error(
                $this->trans($lang, 'booking', 'err_phone', 'Please enter a valid phone number.'),
                400
            );
        }

        $paymentMethodId = trim($request->paymentMethodId);
        if ($paymentMethodId === '') {
            return BookingPaymentResult::error('Payment method is required.', 400);
        }

        $checkInTime = strtotime($request->checkIn);
        $checkOutTime = strtotime($request->checkOut);

        if (!$checkInTime || !$checkOutTime || $checkInTime >= $checkOutTime) {
            return BookingPaymentResult::error(
                $this->trans($lang, 'booking', 'err_dates_invalid', 'Please enter a valid check-in and check-out range.'),
                400
            );
        }

        if ($checkInTime < strtotime(date('Y-m-d'))) {
            return BookingPaymentResult::error(
                $this->trans($lang, 'booking', 'err_dates_past', 'Check-in date cannot be in the past.'),
                400
            );
        }

        if (!$request->privacyConsent) {
            return BookingPaymentResult::error(
                $this->trans(
                    $lang,
                    'booking',
                    'err_privacy_consent',
                    'Please accept the Personal Data Processing Policy to proceed with your booking.'
                ),
                400
            );
        }

        // 2. Idempotency double-charge shield check
        // Must precede availability checks so idempotency retries do not collide with their own booked dates
        if ($request->idempotencyKey !== null && trim($request->idempotencyKey) !== '') {
            $existingPaymentId = $this->findExistingIdempotency(trim($request->idempotencyKey));
            if ($existingPaymentId !== null) {
                $reservation = $this->findReservationByMercadoPagoPaymentId($existingPaymentId);
                if ($reservation !== null) {
                    $resCode = (string) ($reservation['reservation_uid'] ?? trim($request->idempotencyKey));
                    $resStatus = (string) ($reservation['status'] ?? 'confirmed');

                    if ($resStatus === 'pending_payment') {
                        return BookingPaymentResult::pendingPayment(
                            reservationUid: $resCode,
                            message: 'Payment already processed.',
                            extra: [
                                'payment_id' => $existingPaymentId,
                                'reservation_code' => $resCode,
                                'status' => 'pending_payment',
                            ]
                        );
                    }

                    return BookingPaymentResult::confirmed(
                        reservationUid: $resCode,
                        message: 'Payment already processed.',
                        extra: [
                            'payment_id' => $existingPaymentId,
                            'reservation_code' => $resCode,
                            'status' => 'confirmed',
                        ]
                    );
                }

                return BookingPaymentResult::confirmed(
                    reservationUid: trim($request->idempotencyKey),
                    message: 'Payment already processed.',
                    extra: [
                        'payment_id' => $existingPaymentId,
                        'reservation_code' => trim($request->idempotencyKey),
                        'status' => 'confirmed',
                    ]
                );
            }
        }

        // 3. Availability verification via ReservationLedger
        $channelConflict = $this->ledger->findChannelConflict($propertyId, $request->checkIn, $request->checkOut);
        if ($channelConflict !== null) {
            $msgTpl = $this->trans(
                $lang,
                'booking',
                'err_overlap_airbnb',
                'The selected dates overlap with an existing Airbnb booking (%s). Please choose other dates.'
            );
            return BookingPaymentResult::conflict(sprintf($msgTpl, $channelConflict->startDate));
        }

        $maintenanceConflict = $this->ledger->findMaintenanceConflict($propertyId, $request->checkIn, $request->checkOut);
        if ($maintenanceConflict !== null) {
            return BookingPaymentResult::conflict(
                'The selected dates overlap with scheduled maintenance. Please select another range.'
            );
        }

        $resConflict = $this->ledger->findReservationConflict($propertyId, $request->checkIn, $request->checkOut);
        if ($resConflict !== null) {
            return BookingPaymentResult::conflict(
                $this->trans(
                    $lang,
                    'booking',
                    'err_overlap_db',
                    'The selected dates are already locked in our direct booking system. Please select another range.'
                )
            );
        }

        // 4. Authoritative Quote computation via QuoteEngine (ADR 0004)
        try {
            $quote = $this->quoteEngine->quote($propertyId, $request->checkIn, $request->checkOut);
        } catch (InvalidArgumentException $e) {
            return BookingPaymentResult::error(
                $this->trans($lang, 'booking', 'err_dates_invalid', $e->getMessage()),
                400
            );
        }

        if (!$quote->isValid()) {
            $msgTpl = $this->trans(
                $lang,
                'booking',
                'err_min_stay',
                'The minimum stay for the selected season is %d nights. Your requested stay is %d nights.'
            );
            return BookingPaymentResult::error(
                sprintf($msgTpl, $quote->minimumStayRequired(), $quote->nightsCount()),
                422
            );
        }

        // 5. Generate unique reservation business identifier
        $uid = 'ovf_' . bin2hex(random_bytes(4));
        $effectiveIdempotencyKey = ($request->idempotencyKey !== null && trim($request->idempotencyKey) !== '')
            ? trim($request->idempotencyKey)
            : $uid;

        // 6. Build PaymentIntent and dispatch to payment gateway port
        $intent = new PaymentIntent(
            reservationUid: $uid,
            transactionAmount: $quote->totalCop(),
            paymentMethodId: $paymentMethodId,
            payerEmail: $guestEmail,
            token: $request->token,
            installments: $request->installments,
            issuerId: $request->issuerId,
            identificationType: $request->identificationType,
            identificationNumber: $request->identificationNumber,
            financialInstitution: $request->financialInstitution,
            description: 'Reserva OceanViewFlats Apto ' . $propertyId,
            notificationUrl: rtrim($this->publicSiteUrl, '/') . '/api/mercadopago-webhook.php',
            idempotencyKey: $effectiveIdempotencyKey,
            ipAddress: $request->clientIp,
            payerEntityType: ($paymentMethodId === 'pse' ? 'individual' : null)
        );

        try {
            $gatewayResult = $this->gateway->createPayment($intent);
        } catch (PaymentGatewayException $e) {
            return BookingPaymentResult::error(
                'Direct payment declined: ' . $e->getMessage(),
                $e->getCode() >= 400 && $e->getCode() < 600 ? $e->getCode() : 502
            );
        } catch (Throwable $e) {
            return BookingPaymentResult::error(
                'Gateway communication error: ' . $e->getMessage(),
                500
            );
        }

        // 7. Gateway rejection handling
        if ($gatewayResult->isRejected()) {
            $errorMsg = $gatewayResult->errorMessage ?? 'Payment was declined or rejected';
            return BookingPaymentResult::rejected(
                message: $errorMsg,
                statusDetail: $gatewayResult->statusDetail,
                extra: array_filter([
                    'payment_id' => $gatewayResult->paymentId,
                ], fn($v) => $v !== null),
                httpStatusCode: 400
            );
        }

        $consentAt = new DateTimeImmutable('now', new DateTimeZone('America/Bogota'));
        $consentPolicyVersion = ($request->privacyPolicyVersion !== null && trim($request->privacyPolicyVersion) !== '')
            ? trim($request->privacyPolicyVersion)
            : Reservation::DEFAULT_PRIVACY_POLICY_VERSION;
        $consentIp = ($request->clientIp !== null && trim($request->clientIp) !== '')
            ? trim($request->clientIp)
            : '127.0.0.1';

        // 8. Gateway approval handling
        if ($gatewayResult->isApproved()) {
            $draft = ReservationDraft::direct(
                propertyId: $propertyId,
                checkIn: $request->checkIn,
                checkOut: $request->checkOut,
                primaryGuest: PrimaryGuest::create(
                    name: $guestName,
                    email: $guestEmail,
                    phone: $guestPhone,
                    lang: $lang
                ),
                totalPrice: $quote->totalCop(),
                payment: DraftPaymentDetails::direct(
                    paymentMethodId: $gatewayResult->paymentMethodId ?? $paymentMethodId,
                    mercadopagoPaymentId: (string) $gatewayResult->paymentId,
                    paymentStatus: 'approved',
                    paymentDetail: $gatewayResult->statusDetail
                ),
                reservationUid: $uid,
                actor: ActorContext::guest(ipAddress: $consentIp),
                privacyPolicyVersion: $consentPolicyVersion,
                privacyConsentAt: $consentAt,
                privacyConsentIp: $consentIp
            );

            $this->lifecycleEngine->confirmOrRecord($draft);

            $this->recordIdempotency($effectiveIdempotencyKey, (string) ($gatewayResult->paymentId ?? $uid));
            if ($effectiveIdempotencyKey !== $uid) {
                $this->recordIdempotency($uid, (string) ($gatewayResult->paymentId ?? $uid));
            }

            $extra = [
                'payment_id' => $gatewayResult->paymentId,
                'status' => 'approved',
            ];

            return BookingPaymentResult::confirmed(
                reservationUid: $uid,
                message: 'Payment request processed successfully',
                extra: $extra
            );
        }

        // 9. Gateway pending handling (cash vouchers, PSE asynchronous clearing)
        if ($gatewayResult->isPending()) {
            $holdRequest = DirectHoldRequest::create(
                propertyId: $propertyId,
                checkIn: $request->checkIn,
                checkOut: $request->checkOut,
                primaryGuest: PrimaryGuest::create(
                    name: $guestName,
                    email: $guestEmail,
                    phone: $guestPhone,
                    lang: $lang
                ),
                paymentMethodId: $gatewayResult->paymentMethodId ?? $paymentMethodId,
                actor: ActorContext::guest(ipAddress: $consentIp),
                reservationUid: $uid,
                payment: DraftPaymentDetails::direct(
                    paymentMethodId: $gatewayResult->paymentMethodId ?? $paymentMethodId,
                    mercadopagoPaymentId: (string) $gatewayResult->paymentId,
                    paymentStatus: $gatewayResult->status,
                    paymentDetail: $gatewayResult->statusDetail
                ),
                privacyPolicyVersion: $consentPolicyVersion,
                privacyConsentAt: $consentAt,
                privacyConsentIp: $consentIp
            );

            $holdResult = $this->lifecycleEngine->holdDirect($holdRequest);
            $reservation = $holdResult->reservation;

            $this->recordIdempotency($effectiveIdempotencyKey, (string) ($gatewayResult->paymentId ?? $uid));
            if ($effectiveIdempotencyKey !== $uid) {
                $this->recordIdempotency($uid, (string) ($gatewayResult->paymentId ?? $uid));
            }

            // Render and send pending payment instructions emails
            $this->dispatchPendingEmails($reservation, $quote, $gatewayResult);

            $extra = [
                'payment_id' => $gatewayResult->paymentId,
                'status' => $gatewayResult->status,
            ];

            if ($gatewayResult->externalResourceUrl !== null && $gatewayResult->externalResourceUrl !== '') {
                $extra['external_resource_url'] = $gatewayResult->externalResourceUrl;
                if (strtolower($paymentMethodId) === 'efecty') {
                    $extra['printable_voucher_url'] = $gatewayResult->externalResourceUrl;
                }
            }

            if ($gatewayResult->barcode !== null && $gatewayResult->barcode !== '') {
                $extra['barcode'] = $gatewayResult->barcode;
            }

            if ($gatewayResult->verificationCode !== null && $gatewayResult->verificationCode !== '') {
                $extra['verification_code'] = $gatewayResult->verificationCode;
            }

            return BookingPaymentResult::pendingPayment(
                reservationUid: $uid,
                message: 'Payment request processed successfully',
                extra: $extra
            );
        }

        return BookingPaymentResult::error(
            'Unhandled gateway transaction status: ' . $gatewayResult->status,
            500
        );
    }

    /**
     * Looks up existing processed payment by idempotency key.
     */
    private function findExistingIdempotency(string $key): ?string
    {
        try {
            $stmt = $this->pdo->prepare('SELECT payment_id FROM payment_idempotency WHERE idempotency_key = :key LIMIT 1');
            $stmt->execute(['key' => $key]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($row !== false && isset($row['payment_id'])) {
                return (string) $row['payment_id'];
            }
        } catch (Throwable) {
            // Silently tolerate database errors or unmigrated environments
        }

        return null;
    }

    /**
     * Records an entry into the payment idempotency table.
     */
    private function recordIdempotency(string $key, string $paymentId): void
    {
        if (trim($key) === '') {
            return;
        }

        try {
            $stmt = $this->pdo->prepare('
                INSERT INTO payment_idempotency (idempotency_key, payment_id)
                VALUES (:key, :pay_id)
            ');
            $stmt->execute([
                'key' => trim($key),
                'pay_id' => trim($paymentId),
            ]);
        } catch (Throwable) {
            // Silently tolerate duplicates or schema differences
        }
    }



    /**
     * Dispatches localized pending payment instructions to guest and host.
     */
    private function dispatchPendingEmails(Reservation $reservation, Quote $quote, PaymentGatewayResult $gatewayResult): void
    {
        $lang = $reservation->lang;
        $htmlMessage = $this->pendingEmailRenderer->renderPendingEmailHtml($reservation, $quote, $gatewayResult);

        // 1. Host Notification Email
        $subjectHost = sprintf(
            'BOOKING INQUIRY: Prop %s (%s) - [%s]',
            $reservation->propertyId,
            $reservation->guestName,
            strtoupper($lang)
        );
        $this->emailSender->send($this->hostNotificationEmail, $subjectHost, $htmlMessage);

        // 2. Primary Guest Email
        $guestTpl = $this->trans(
            $lang,
            'booking',
            'email_subject_guest',
            'We received your booking inquiry - OceanViewFlats %s'
        );
        $subjectGuest = sprintf($guestTpl, $reservation->propertyId);
        $this->emailSender->send($reservation->guestEmail, $subjectGuest, $htmlMessage);
    }

    /**
     * Looks up an existing reservation row by Mercado Pago payment identifier.
     *
     * @param string $paymentId
     * @return array<string, mixed>|null
     */
    private function findReservationByMercadoPagoPaymentId(string $paymentId): ?array
    {
        try {
            $stmt = $this->pdo->prepare('SELECT * FROM reservations WHERE mercadopago_payment_id = :payment_id LIMIT 1');
            $stmt->execute(['payment_id' => $paymentId]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            return is_array($row) ? $row : null;
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * Resolves translation string with graceful fallback.
     */
    private function trans(string $lang, string $section, string $key, string $default): string
    {
        $langKey = strtolower($lang);
        if (isset($this->translations[$langKey][$section][$key])) {
            return (string) $this->translations[$langKey][$section][$key];
        }
        if (isset($this->translations['en'][$section][$key])) {
            return (string) $this->translations['en'][$section][$key];
        }
        return $default;
    }
}
