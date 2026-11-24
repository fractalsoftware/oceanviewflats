<?php

declare(strict_types=1);

namespace OceanViewFlats\Domain\Payment;

use OceanViewFlats\Domain\Fulfillment\BookingFulfillment;
use OceanViewFlats\Domain\Fulfillment\BookingFulfillmentInterface;
use OceanViewFlats\Domain\Fulfillment\CancellationEmailRenderer;
use OceanViewFlats\Domain\Fulfillment\CancellationEmailRendererInterface;
use OceanViewFlats\Domain\Fulfillment\EmailSenderInterface;
use OceanViewFlats\Domain\Fulfillment\PhpMailSender;
use OceanViewFlats\Domain\Reservation\ActorContext;
use OceanViewFlats\Domain\Reservation\CancellationRequest;
use OceanViewFlats\Domain\Reservation\DraftPaymentDetails;
use OceanViewFlats\Domain\Reservation\InvalidReservationStateException;
use OceanViewFlats\Domain\Reservation\PrimaryGuest;
use OceanViewFlats\Domain\Reservation\ReservationDraft;
use OceanViewFlats\Domain\Reservation\ReservationLifecycleEngineInterface;
use OceanViewFlats\Infrastructure\Reservation\ReservationLifecycleEngineFactory;
use PDO;
use Throwable;

/**
 * Authoritative domain service processing asynchronous external webhook settlements from payment gateway.
 *
 * Implements the five core settlement rules:
 * 1. Resurrection Defense: Rejects delayed payment confirmations for cancelled bookings.
 * 2. Full Refund Synchronization: Auto-cancels confirmed bookings, records itemized refund entries,
 *    logs audit trail records, and dispatches localized cancellation notices to primary guests.
 * 3. Partial Refund Synchronization: Updates balances and records line items while preserving active stay dates.
 * 4. Idempotency Replay Guard: Gracefully acknowledges duplicate webhook events without duplicating mutations or emails.
 * 5. Confirmation Fulfillment: Transitions pending reservations to confirmed, associates payment IDs,
 *    records audit history, and dispatches fulfillment without door codes (preserving ADR 0001).
 */
final class WebhookSettlementProcessor implements WebhookSettlementProcessorInterface
{
    private ReservationLifecycleEngineInterface $lifecycleEngine;

    public function __construct(
        private readonly PaymentGatewayInterface $gateway,
        private readonly PDO $pdo,
        private readonly BookingFulfillmentInterface $fulfillment,
        private readonly CancellationEmailRendererInterface $cancellationRenderer,
        private readonly EmailSenderInterface $emailSender,
        private readonly string $publicSiteUrl = 'https://oceanviewflats.com',
        private readonly string $hostNotificationEmail = 'reservas@oceanviewflats.com',
        ?ReservationLifecycleEngineInterface $lifecycleEngine = null
    ) {
        $this->lifecycleEngine = $lifecycleEngine ?? ReservationLifecycleEngineFactory::create($this->pdo, [
            'bookingFulfillment' => $this->fulfillment,
            'cancellationRenderer' => $this->cancellationRenderer,
            'emailSender' => $this->emailSender,
        ]);
    }

    public function getPublicSiteUrl(): string
    {
        return $this->publicSiteUrl;
    }

    public function getHostNotificationEmail(): string
    {
        return $this->hostNotificationEmail;
    }

    /**
     * Factory providing sensible production defaults while allowing complete test seam overrides.
     *
     * @param PDO $pdo
     * @param array<string, mixed> $options
     * @return self
     */
    public static function createDefault(PDO $pdo, array $options = []): self
    {
        $token = (string) (
            $options['mercadopago_access_token']
            ?? $_ENV['MERCADOPAGO_ACCESS_TOKEN']
            ?? $_SERVER['MERCADOPAGO_ACCESS_TOKEN']
            ?? getenv('MERCADOPAGO_ACCESS_TOKEN')
            ?: ''
        );
        $baseUrl = (string) (
            $options['mercadopago_base_url']
            ?? $_ENV['MERCADOPAGO_BASE_URL']
            ?? 'https://api.mercadopago.com'
        );
        $publicSiteUrl = (string) (
            $options['public_site_url']
            ?? $_ENV['PUBLIC_SITE_URL']
            ?? getenv('PUBLIC_SITE_URL')
            ?: 'https://oceanviewflats.com'
        );
        $hostNotificationEmail = (string) (
            $options['host_notification_email']
            ?? $_ENV['RECIPIENT_EMAIL']
            ?? getenv('RECIPIENT_EMAIL')
            ?: 'reservas@oceanviewflats.com'
        );

        /** @var PaymentGatewayInterface $gateway */
        $gateway = $options['gateway'] ?? new MercadoPagoPaymentGateway(
            accessToken: $token,
            baseUrl: $baseUrl
        );

        /** @var EmailSenderInterface $emailSender */
        $emailSender = $options['email_sender'] ?? new PhpMailSender();

        /** @var BookingFulfillmentInterface $fulfillment */
        $fulfillment = $options['fulfillment'] ?? BookingFulfillment::createDefault($hostNotificationEmail, $emailSender);

        /** @var CancellationEmailRendererInterface $cancellationRenderer */
        $cancellationRenderer = $options['cancellation_renderer'] ?? new CancellationEmailRenderer(baseUrl: $publicSiteUrl);

        /** @var ReservationLifecycleEngineInterface|null $lifecycleEngine */
        $lifecycleEngine = $options['lifecycle_engine'] ?? $options['lifecycleEngine'] ?? null;
        if ($lifecycleEngine === null && isset($options['engine']) && $options['engine'] instanceof ReservationLifecycleEngineInterface) {
            $lifecycleEngine = $options['engine'];
        }

        return new self(
            gateway: $gateway,
            pdo: $pdo,
            fulfillment: $fulfillment,
            cancellationRenderer: $cancellationRenderer,
            emailSender: $emailSender,
            publicSiteUrl: $publicSiteUrl,
            hostNotificationEmail: $hostNotificationEmail,
            lifecycleEngine: $lifecycleEngine
        );
    }

    /**
     * Authoritatively processes and settles an incoming payment notification from the payment gateway.
     *
     * @param string $paymentId External gateway payment ID
     * @return WebhookSettlementResult
     */
    public function settlePayment(string $paymentId): WebhookSettlementResult
    {
        $cleanPaymentId = trim($paymentId);
        if ($cleanPaymentId === '') {
            return WebhookSettlementResult::gatewayError(
                message: 'Payment ID cannot be empty',
                httpStatusCode: 400,
                data: ['payment_id' => $paymentId]
            );
        }

        // 1. Authoritative Gateway Lookup
        try {
            $details = $this->gateway->getPayment($cleanPaymentId);
        } catch (PaymentGatewayException $e) {
            error_log('[Webhook Settlement Gateway Exception] ' . $e->getMessage());
            $code = $e->getCode();
            $statusCode = ($code >= 400 && $code < 600) ? $code : 502;
            return WebhookSettlementResult::gatewayError(
                message: 'Payment gateway communication error.',
                httpStatusCode: $statusCode,
                data: ['payment_id' => $cleanPaymentId, 'error_type' => $e->getErrorType()]
            );
        } catch (Throwable $e) {
            error_log('[Webhook Settlement Gateway Error] ' . $e->getMessage());
            return WebhookSettlementResult::gatewayError(
                message: 'Unexpected gateway communication failure.',
                httpStatusCode: 502,
                data: ['payment_id' => $cleanPaymentId]
            );
        }

        // 2. Reservation External Reference Lookup
        $externalRef = trim($details->externalReference);
        if ($externalRef === '') {
            return WebhookSettlementResult::unknownReservation(
                reservationUid: null,
                data: ['payment_id' => $cleanPaymentId, 'status' => $details->status],
                message: 'Payment does not contain external_reference.'
            );
        }

        try {
            $stmt = $this->pdo->prepare('SELECT * FROM reservations WHERE reservation_uid = ? LIMIT 1');
            $stmt->execute([$externalRef]);
            /** @var array<string, mixed>|false $reservation */
            $reservation = $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (Throwable $e) {
            error_log('[Webhook Settlement DB Error] ' . $e->getMessage());
            return WebhookSettlementResult::databaseError(
                message: 'Database query failure.',
                httpStatusCode: 500,
                data: ['payment_id' => $cleanPaymentId, 'reservation_uid' => $externalRef],
                reservationUid: $externalRef
            );
        }

        if ($reservation === false) {
            return WebhookSettlementResult::unknownReservation(
                reservationUid: $externalRef,
                data: ['payment_id' => $cleanPaymentId, 'status' => $details->status],
                message: sprintf('Reservation %s not found in database.', $externalRef)
            );
        }

        $resStatus = (string) ($reservation['status'] ?? '');
        $resTotalPrice = (float) ($reservation['total_price'] ?? 0.0);
        $totalRefundedAmount = $details->totalRefundedAmount;

        // 3. Branch 1: Full Refund Synchronization (Rule 2)
        $isFullRefund = $details->isRefunded() || ($resTotalPrice > 0.0 && $totalRefundedAmount >= $resTotalPrice);
        if ($isFullRefund) {
            $actualRefundAmount = $totalRefundedAmount > 0.0 ? $totalRefundedAmount : $resTotalPrice;

            if ($resStatus !== 'cancelled') {
                try {
                    $refunds = $details->refunds;
                    $lastRefundId = !empty($refunds) ? end($refunds)->refundId : null;

                    $cancellationRequest = new CancellationRequest(
                        reason: 'External Refund Sync: Mercado Pago full refund webhook',
                        refundAmountCop: $actualRefundAmount,
                        dispatchGatewayRefund: false,
                        externalRefundId: $lastRefundId,
                        actor: ActorContext::webhook(),
                        sendCancellationEmail: true,
                    );

                    $this->lifecycleEngine->cancel($externalRef, $cancellationRequest);
                } catch (Throwable $e) {
                    error_log('[Webhook Full Refund Error] ' . $e->getMessage());
                    return WebhookSettlementResult::databaseError(
                        message: 'Failed to process full refund cancellation.',
                        httpStatusCode: 500,
                        data: ['payment_id' => $cleanPaymentId, 'reservation_uid' => $externalRef],
                        reservationUid: $externalRef
                    );
                }

                return WebhookSettlementResult::refundCancelled(
                    reservationUid: $externalRef,
                    data: [
                        'payment_id' => $cleanPaymentId,
                        'refunded_amount' => $actualRefundAmount,
                        'reservation_status' => 'cancelled',
                    ]
                );
            }

            // Already cancelled: synchronize refund rows and update refunded amount if needed
            try {
                $upStmt = $this->pdo->prepare("
                    UPDATE reservations 
                    SET payment_status = 'refunded', 
                        refunded_amount = :refunded_amount, 
                        updated_at = CURRENT_TIMESTAMP
                    WHERE reservation_uid = :uid
                ");
                $upStmt->execute([
                    ':uid' => $externalRef,
                    ':refunded_amount' => $actualRefundAmount,
                ]);

                $this->syncRefundItems($externalRef, $cleanPaymentId, $details->refunds, $actualRefundAmount);
            } catch (Throwable $e) {
                error_log('[Webhook Refund Sync Error] ' . $e->getMessage());
                return WebhookSettlementResult::databaseError(
                    message: 'Failed to sync refund items on cancelled reservation.',
                    httpStatusCode: 500,
                    data: ['payment_id' => $cleanPaymentId, 'reservation_uid' => $externalRef],
                    reservationUid: $externalRef
                );
            }

            return WebhookSettlementResult::refundCancelled(
                reservationUid: $externalRef,
                data: [
                    'payment_id' => $cleanPaymentId,
                    'refunded_amount' => $actualRefundAmount,
                    'reservation_status' => 'cancelled',
                ],
                message: 'Reservation was already cancelled; synchronized refund items.'
            );
        }

        // 4. Branch 2: Partial Refund Synchronization (Rule 3)
        $isPartialRefund = $details->isPartiallyRefunded()
            || ($totalRefundedAmount > 0.0 && $totalRefundedAmount < $resTotalPrice);
        if ($isPartialRefund) {
            try {
                $this->pdo->beginTransaction();

                $upStmt = $this->pdo->prepare("
                    UPDATE reservations 
                    SET payment_status = 'partially_refunded', 
                        refunded_amount = :refunded_amount, 
                        updated_at = CURRENT_TIMESTAMP
                    WHERE reservation_uid = :uid
                ");
                $upStmt->execute([
                    ':uid' => $externalRef,
                    ':refunded_amount' => $totalRefundedAmount,
                ]);

                $this->syncRefundItems($externalRef, $cleanPaymentId, $details->refunds, $totalRefundedAmount);

                $this->recordAuditLog(
                    action: 'external_partial_refund',
                    reservationUid: $externalRef,
                    payloadBefore: [
                        'payment_status' => $reservation['payment_status'] ?? null,
                        'refunded_amount' => (float) ($reservation['refunded_amount'] ?? 0.0),
                    ],
                    payloadAfter: [
                        'payment_status' => 'partially_refunded',
                        'refunded_amount' => $totalRefundedAmount,
                    ]
                );

                $this->pdo->commit();
            } catch (Throwable $e) {
                if ($this->pdo->inTransaction()) {
                    $this->pdo->rollBack();
                }
                error_log('[Webhook Partial Refund Error] ' . $e->getMessage());
                return WebhookSettlementResult::databaseError(
                    message: 'Failed to synchronize partial refund.',
                    httpStatusCode: 500,
                    data: ['payment_id' => $cleanPaymentId, 'reservation_uid' => $externalRef],
                    reservationUid: $externalRef
                );
            }

            return WebhookSettlementResult::partialRefundSynced(
                reservationUid: $externalRef,
                data: [
                    'payment_id' => $cleanPaymentId,
                    'refunded_amount' => $totalRefundedAmount,
                    'reservation_status' => $resStatus,
                ]
            );
        }

        // 5. Branch 3: Approved Payment
        if ($details->isApproved()) {
            // Rule 1: Resurrection Defense (cancelled is terminal)
            if ($resStatus === 'cancelled') {
                return WebhookSettlementResult::ignoredCancelled(
                    reservationUid: $externalRef,
                    data: [
                        'payment_id' => $cleanPaymentId,
                        'current_status' => $resStatus,
                    ]
                );
            }

            // Rule 4: Idempotency Replay Guard
            if ($resStatus === 'confirmed') {
                return WebhookSettlementResult::alreadyConfirmed(
                    reservationUid: $externalRef,
                    data: [
                        'payment_id' => $cleanPaymentId,
                        'current_status' => $resStatus,
                    ]
                );
            }

            // Settlement integrity check: fail closed unless currency is COP and settled amount covers reservation Quote
            $currencyId = strtoupper(trim($details->currencyId));
            if ($currencyId !== 'COP') {
                error_log(sprintf(
                    '[Webhook Settlement Security] Currency mismatch or missing currency for %s (payment %s): expected COP, got %s',
                    $externalRef,
                    $cleanPaymentId,
                    $currencyId !== '' ? $currencyId : '(empty)'
                ));
                return WebhookSettlementResult::gatewayError(
                    message: 'Payment currency does not match settlement currency (COP).',
                    httpStatusCode: 422,
                    data: ['payment_id' => $cleanPaymentId, 'reservation_uid' => $externalRef]
                );
            }

            if ($resTotalPrice <= 0.0 || $details->transactionAmount <= 0.0 || ($details->transactionAmount + 1.0) < $resTotalPrice) {
                error_log(sprintf(
                    '[Webhook Settlement Security] Amount mismatch for %s (payment %s): expected %.2f COP, got %.2f COP',
                    $externalRef,
                    $cleanPaymentId,
                    $resTotalPrice,
                    $details->transactionAmount
                ));
                return WebhookSettlementResult::gatewayError(
                    message: 'Settled payment amount does not cover reservation quote.',
                    httpStatusCode: 422,
                    data: ['payment_id' => $cleanPaymentId, 'reservation_uid' => $externalRef]
                );
            }

            try {
                $paymentMethodId = $details->paymentMethodId !== ''
                    ? $details->paymentMethodId
                    : (isset($reservation['payment_method_id']) ? (string) $reservation['payment_method_id'] : null);

                $ip = isset($_SERVER['REMOTE_ADDR']) && is_string($_SERVER['REMOTE_ADDR'])
                    ? $_SERVER['REMOTE_ADDR']
                    : '127.0.0.1';
                $ua = isset($_SERVER['HTTP_USER_AGENT']) && is_string($_SERVER['HTTP_USER_AGENT'])
                    ? $_SERVER['HTTP_USER_AGENT']
                    : null;

                $draft = ReservationDraft::direct(
                    propertyId: (string) $reservation['property_id'],
                    checkIn: (string) $reservation['check_in'],
                    checkOut: (string) $reservation['check_out'],
                    primaryGuest: PrimaryGuest::create(
                        name: (string) $reservation['guest_name'],
                        email: (string) $reservation['guest_email'],
                        phone: (string) $reservation['guest_phone'],
                        lang: isset($reservation['lang']) ? (string) $reservation['lang'] : 'en'
                    ),
                    payment: DraftPaymentDetails::create(
                        paymentMethodId: $paymentMethodId,
                        mercadopagoPaymentId: $cleanPaymentId,
                        paymentStatus: 'approved'
                    ),
                    totalPrice: (float) $reservation['total_price'],
                    reservationUid: $externalRef,
                    actor: ActorContext::webhook($ip, $ua),
                    sendConfirmationEmail: true
                );

                $this->lifecycleEngine->confirmOrRecord($draft);

                return WebhookSettlementResult::confirmed(
                    reservationUid: $externalRef,
                    data: [
                        'payment_id' => $cleanPaymentId,
                        'fulfillment_success' => true,
                        'fulfillment_errors' => [],
                    ]
                );
            } catch (InvalidReservationStateException $e) {
                return WebhookSettlementResult::ignoredCancelled(
                    reservationUid: $externalRef,
                    data: [
                        'payment_id' => $cleanPaymentId,
                        'current_status' => $resStatus,
                        'reason' => $e->getMessage(),
                    ]
                );
            } catch (Throwable $e) {
                error_log('[Webhook Confirm Error] ' . $e->getMessage());
                return WebhookSettlementResult::databaseError(
                    message: 'Failed to confirm reservation.',
                    httpStatusCode: 500,
                    data: ['payment_id' => $cleanPaymentId, 'reservation_uid' => $externalRef],
                    reservationUid: $externalRef
                );
            }
        }

        // 6. Default / Unrecognized Status
        return WebhookSettlementResult::unhandledStatus(
            status: $details->status,
            reservationUid: $externalRef,
            data: ['payment_id' => $cleanPaymentId]
        );
    }

    /**
     * @param array<int, RefundLineItem> $refunds
     */
    private function syncRefundItems(string $uid, string $paymentId, array $refunds, float $fallbackAmount): void
    {
        try {
            if (empty($refunds)) {
                if ($fallbackAmount > 0.0) {
                    $checkStmt = $this->pdo->prepare('SELECT COUNT(*) FROM reservation_refunds WHERE reservation_uid = :uid');
                    $checkStmt->execute([':uid' => $uid]);
                    if ((int) $checkStmt->fetchColumn() === 0) {
                        $ins = $this->pdo->prepare('
                            INSERT INTO reservation_refunds (
                                reservation_uid, mercadopago_refund_id, mercadopago_payment_id,
                                amount, status, reason, source, admin_user_id, created_at
                            ) VALUES (
                                :uid, NULL, :payment_id, :amount, "approved", "External refund synchronized via webhook", "mercadopago_webhook", NULL, CURRENT_TIMESTAMP
                            )
                        ');
                        $ins->execute([
                            ':uid' => $uid,
                            ':payment_id' => $paymentId !== '' ? $paymentId : 'external_webhook',
                            ':amount' => $fallbackAmount,
                        ]);
                    }
                }
                return;
            }

            $checkStmt = $this->pdo->prepare('SELECT COUNT(*) FROM reservation_refunds WHERE mercadopago_refund_id = :refund_id');
            $insStmt = $this->pdo->prepare('
                INSERT INTO reservation_refunds (
                    reservation_uid, mercadopago_refund_id, mercadopago_payment_id,
                    amount, status, reason, source, admin_user_id, created_at
                ) VALUES (
                    :uid, :refund_id, :payment_id, :amount, :status, :reason, "mercadopago_webhook", NULL, CURRENT_TIMESTAMP
                )
            ');

            foreach ($refunds as $refund) {
                $refundId = $refund->refundId !== '' ? $refund->refundId : null;
                if ($refundId !== null) {
                    $checkStmt->execute([':refund_id' => $refundId]);
                    if ((int) $checkStmt->fetchColumn() > 0) {
                        continue; // Already synced
                    }
                }

                $amount = $refund->amount > 0.0 ? $refund->amount : $fallbackAmount;
                if ($amount <= 0.0) {
                    continue;
                }

                $status = $refund->status !== '' ? $refund->status : 'approved';
                $insStmt->execute([
                    ':uid' => $uid,
                    ':refund_id' => $refundId,
                    ':payment_id' => $refund->paymentId !== '' ? $refund->paymentId : ($paymentId !== '' ? $paymentId : 'external_webhook'),
                    ':amount' => $amount,
                    ':status' => $status,
                    ':reason' => 'Mercado Pago external refund event',
                ]);
            }
        } catch (Throwable) {
            // Gracefully ignore if reservation_refunds table is not present
        }
    }

    /**
     * @param array<string, mixed>|null $payloadBefore
     * @param array<string, mixed>|null $payloadAfter
     */
    private function recordAuditLog(
        string $action,
        string $reservationUid,
        ?array $payloadBefore = null,
        ?array $payloadAfter = null
    ): void {
        try {
            $stmt = $this->pdo->prepare('
                INSERT INTO admin_audit_logs (
                    admin_user_id, action, entity_type, entity_id, payload_before, payload_after, ip_address, created_at
                ) VALUES (
                    NULL, :action, "reservation", :entity_id, :payload_before, :payload_after, :ip_address, CURRENT_TIMESTAMP
                )
            ');
            $stmt->execute([
                ':action' => $action,
                ':entity_id' => $reservationUid,
                ':payload_before' => $payloadBefore !== null ? json_encode($payloadBefore, JSON_UNESCAPED_SLASHES) : null,
                ':payload_after' => $payloadAfter !== null ? json_encode($payloadAfter, JSON_UNESCAPED_SLASHES) : null,
                ':ip_address' => $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1',
            ]);
        } catch (Throwable) {
            // Tolerant of missing audit logs table or non-fatal logging failure
        }
    }
}
