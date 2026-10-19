<?php

declare(strict_types=1);

namespace OceanViewFlats\Domain\Fulfillment;

use DateTimeImmutable;
use OceanViewFlats\Domain\Access\ConfigPropertyCredentialsProvider;
use OceanViewFlats\Domain\Access\DoorCodeGenerator;
use OceanViewFlats\Domain\Access\PropertyCredentialsProviderInterface;
use OceanViewFlats\Domain\Reservation\PdoReservationRepository;
use OceanViewFlats\Domain\Reservation\Reservation;
use OceanViewFlats\Domain\Reservation\ReservationRepositoryInterface;
use OceanViewFlats\Domain\Security\PayloadEncryptor;
use OceanViewFlats\Domain\Security\PiiMasker;
use OceanViewFlats\Domain\Support\PathResolver;
use OceanViewFlats\Domain\Support\PublicUrlBuilder;
use PDO;
use Throwable;

/**
 * Authoritative domain service managing the guest registration lifecycle,
 * ADR 0001 access credential generation and disclosure, administrative manual registry,
 * PIN overrides, and PIN regeneration.
 */
final class GuestLifecycleFulfillmentService implements GuestLifecycleFulfillmentServiceInterface
{
    private readonly AccessDispatchEmailRendererInterface $accessDispatchRenderer;
    private readonly PropertyCredentialsProviderInterface $credentialsProvider;
    private readonly PublicUrlBuilder $urlBuilder;
    private readonly ?CondominiumClearanceSyncInterface $clearanceSync;

    public function __construct(
        private readonly PDO $pdo,
        private readonly ReservationRepositoryInterface $reservationRepository,
        private readonly HostRegistryEmailRendererInterface $hostRegistryRenderer,
        private readonly EmailSenderInterface $emailSender,
        private readonly string $hostNotificationEmail = 'rentals@oceanviewflats.com',
        private readonly string $publicSiteUrl = 'https://oceanviewflats.com',
        ?AccessDispatchEmailRendererInterface $accessDispatchRenderer = null,
        ?CondominiumClearanceSyncInterface $clearanceSync = null,
        ?PropertyCredentialsProviderInterface $credentialsProvider = null,
        ?PublicUrlBuilder $urlBuilder = null
    ) {
        $this->accessDispatchRenderer = $accessDispatchRenderer ?? new AccessDispatchEmailRenderer($this->publicSiteUrl);
        $this->credentialsProvider = $credentialsProvider ?? new ConfigPropertyCredentialsProvider();
        $this->urlBuilder = $urlBuilder ?? new PublicUrlBuilder($this->publicSiteUrl);
        $this->clearanceSync = $clearanceSync;
    }

    /**
     * Factory creating a service configured with production defaults or custom overrides.
     *
     * @param array<string, mixed> $options
     */
    public static function createDefault(PDO $pdo, array $options = []): self
    {
        $resolvedHost = (string) (
            $options['host_notification_email']
            ?? $_ENV['RECIPIENT_EMAIL']
            ?? $_SERVER['RECIPIENT_EMAIL']
            ?? getenv('RECIPIENT_EMAIL')
            ?: (defined('RECIPIENT_EMAIL') ? constant('RECIPIENT_EMAIL') : 'rentals@oceanviewflats.com')
        );

        $resolvedPublicSiteUrl = (string) (
            $options['public_site_url']
            ?? $_ENV['PUBLIC_SITE_URL']
            ?? $_SERVER['PUBLIC_SITE_URL']
            ?? getenv('PUBLIC_SITE_URL')
            ?: (defined('PUBLIC_SITE_URL') ? constant('PUBLIC_SITE_URL') : 'https://oceanviewflats.com')
        );

        /** @var ReservationRepositoryInterface $reservationRepository */
        $reservationRepository = $options['reservation_repository'] ?? new PdoReservationRepository($pdo);
        /** @var HostRegistryEmailRendererInterface $hostRegistryRenderer */
        $hostRegistryRenderer = $options['host_registry_renderer'] ?? new HostRegistryEmailRenderer();
        /** @var EmailSenderInterface $emailSender */
        $emailSender = $options['email_sender'] ?? new PhpMailSender();
        /** @var AccessDispatchEmailRendererInterface $accessDispatchRenderer */
        $accessDispatchRenderer = $options['access_dispatch_renderer'] ?? new AccessDispatchEmailRenderer($resolvedPublicSiteUrl);
        /** @var ?CondominiumClearanceSyncInterface $clearanceSync */
        $clearanceSync = array_key_exists('clearance_sync', $options)
            ? $options['clearance_sync']
            : (array_key_exists('condominium_clearance_sync', $options)
                ? $options['condominium_clearance_sync']
                : HuespedManagerClearanceSync::createDefault($pdo));
        /** @var PropertyCredentialsProviderInterface $credentialsProvider */
        $credentialsProvider = $options['credentials_provider'] ?? new ConfigPropertyCredentialsProvider();
        /** @var PublicUrlBuilder $urlBuilder */
        $urlBuilder = $options['public_url_builder'] ?? new PublicUrlBuilder($resolvedPublicSiteUrl);

        return new self(
            pdo: $pdo,
            reservationRepository: $reservationRepository,
            hostRegistryRenderer: $hostRegistryRenderer,
            emailSender: $emailSender,
            hostNotificationEmail: $resolvedHost,
            publicSiteUrl: $resolvedPublicSiteUrl,
            accessDispatchRenderer: $accessDispatchRenderer,
            clearanceSync: $clearanceSync,
            credentialsProvider: $credentialsProvider,
            urlBuilder: $urlBuilder
        );
    }

    /**
     * {@inheritdoc}
     */
    public function submitRegistry(GuestRegistrySubmission $submission): RegistryFulfillmentResult
    {
        // 1. Validate stay dates if provided
        if ($submission->checkIn !== '' || $submission->checkOut !== '') {
            if (!$submission->hasValidDates()) {
                return RegistryFulfillmentResult::validationFailure([
                    'Check-in and check-out dates are invalid or improperly ordered.',
                ]);
            }
        }

        // 2. Resolve reservation (if available) to determine effective max_guests capacity
        $reservation = null;
        if ($submission->reservationCode !== '') {
            $reservation = $this->reservationRepository->findByUid($submission->reservationCode);
        }
        if ($reservation === null && $submission->propertyId !== '' && $submission->checkIn !== '' && $submission->checkOut !== '') {
            $reservation = $this->reservationRepository->findByPropertyAndDates(
                $submission->propertyId,
                $submission->checkIn,
                $submission->checkOut
            );
        }

        $maxGuests = $reservation !== null
            ? $reservation->maxGuests
            : Reservation::DEFAULT_MAX_GUESTS;

        // 3. Validate guest count: at least 1, up to reservation's max_guests (default 6)
        $guestCount = $submission->getGuestCount();
        if ($guestCount < 1 || $guestCount > $maxGuests) {
            return RegistryFulfillmentResult::validationFailure([
                sprintf('A reservation must register between 1 and %d guests.', $maxGuests),
            ]);
        }

        // 4. Validate occupant details defensively
        $validationErrors = [];
        foreach ($submission->occupants as $occupant) {
            $trimmedName = trim($occupant->name);
            if (strlen($trimmedName) < 2 || strlen($occupant->name) > 100) {
                $validationErrors[] = sprintf('Guest %d name must be between 2 and 100 characters.', $occupant->index);
            }
            if ($occupant->age < 0 || $occupant->age > 120) {
                $validationErrors[] = sprintf('Guest %d age must be between 0 and 120.', $occupant->index);
            }
            $trimmedDoc = trim($occupant->docNum);
            if (strlen($trimmedDoc) < 2 || strlen($occupant->docNum) > 50) {
                $validationErrors[] = sprintf('Guest %d document number must be between 2 and 50 characters.', $occupant->index);
            }
        }
        if (!empty($validationErrors)) {
            return RegistryFulfillmentResult::validationFailure($validationErrors);
        }

        // 4b. Enforce Personal Data Authorization (Ley 1581 of 2012) & Minor Guardian Attestation (Art. 7 / Ley 679)
        if (!$submission->privacyConsent) {
            return RegistryFulfillmentResult::validationFailure([
                $this->translateRegistryError(
                    $submission->lang,
                    'err_privacy_consent',
                    'Personal data processing authorization is required.'
                ),
            ]);
        }

        if ($submission->hasMinorOccupant() && !$submission->minorGuardianConsent) {
            return RegistryFulfillmentResult::validationFailure([
                $this->translateRegistryError(
                    $submission->lang,
                    'err_minor_guardian_consent',
                    'Parental authority or legal guardian attestation is required when registering minors under 18.'
                ),
            ]);
        }

        if ($reservation === null) {
            return RegistryFulfillmentResult::notFound(
                $submission->reservationCode,
                'No confirmed reservation matches the provided booking details.'
            );
        }

        // 5. Check reservation status & completion lock
        if ($reservation->status->isCancelled()) {
            return RegistryFulfillmentResult::validationFailure([
                'Cannot register guests for a cancelled reservation.',
            ]);
        }

        if ($reservation->isConcluded()) {
            return RegistryFulfillmentResult::validationFailure([
                'Cannot submit guest registry for a concluded reservation.',
            ]);
        }

        if ($reservation->registryCompleted) {
            return RegistryFulfillmentResult::validationFailure([
                'Guest registry has already been completed for this reservation.',
            ]);
        }

        // 5. Generate algorithmic PIN per ADR 0001
        $primary = $submission->getPrimaryOccupant();
        $primaryDoc = $primary !== null ? $primary->docNum : '';
        $doorCode = DoorCodeGenerator::generate($primaryDoc, $reservation->guestPhone);

        // 6. Atomic PDO transaction
        $this->pdo->beginTransaction();
        try {
            $this->reservationRepository->markRegistryCompleted(
                $reservation->reservationUid,
                new DateTimeImmutable(),
                $doorCode
            );

            // Enrich external/placeholder reservation email with verified Primary Guest email
            if ($this->shouldEnrichReservationEmail($reservation)) {
                $submittedEmail = trim($submission->primaryGuestEmail);
                if (strcasecmp(trim($reservation->guestEmail), $submittedEmail) !== 0) {
                    $stmtEmail = $this->pdo->prepare("
                        UPDATE `reservations`
                        SET `guest_email` = :guest_email,
                            `updated_at` = CURRENT_TIMESTAMP
                        WHERE `reservation_uid` = :reservation_uid
                    ");
                    $stmtEmail->execute([
                        ':guest_email' => $submittedEmail,
                        ':reservation_uid' => $reservation->reservationUid,
                    ]);
                    $reservation = $reservation->withGuestEmail($submittedEmail);
                }
            }

            $consentTimestamp = ($submission->privacyConsentAt ?? new DateTimeImmutable())->format('Y-m-d H:i:s');
            $consentIp = $submission->ipAddress ?? '127.0.0.1';
            $policyVersion = $submission->privacyPolicyVersion !== '' ? $submission->privacyPolicyVersion : '2026-10';

            $this->insertGuestRegistry(
                reservationUid: $reservation->reservationUid,
                propertyId: $reservation->propertyId,
                checkIn: $reservation->checkIn,
                checkOut: $reservation->checkOut,
                occupants: $submission->occupants,
                carPlates: $submission->carPlates,
                carModel: $submission->carModel,
                ipAddress: $submission->ipAddress,
                privacyPolicyVersion: $policyVersion,
                privacyConsentAt: $consentTimestamp,
                privacyConsentIp: $consentIp,
                minorGuardianConsent: $submission->minorGuardianConsent
            );

            $maskedAuditSubmission = PiiMasker::maskSubmissionArray($submission->toArray());

            $stmtAudit = $this->pdo->prepare("
                INSERT INTO `admin_audit_logs` (
                    `action`, `entity_type`, `entity_id`,
                    `payload_after`, `ip_address`, `created_at`
                ) VALUES (
                    'guest_registry_submitted', 'reservation', :entity_id,
                    :payload_after, :ip_address, CURRENT_TIMESTAMP
                )
            ");
            $stmtAudit->execute([
                ':entity_id' => $reservation->reservationUid,
                ':payload_after' => json_encode($maskedAuditSubmission, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
                ':ip_address' => $submission->ipAddress ?? '127.0.0.1',
            ]);

            $this->pdo->commit();
        } catch (Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            return RegistryFulfillmentResult::systemError('Database transaction failed: ' . $e->getMessage());
        }

        // 7. Side effects (after commit, catching exceptions)
        $hostReportSent = false;
        try {
            $subject = $this->hostRegistryRenderer->renderSubject($submission);
            $html = $this->hostRegistryRenderer->renderHtml(
                $submission,
                $doorCode
            );
            $hostReportSent = $this->emailSender->send($this->hostNotificationEmail, $subject, $html);
        } catch (Throwable) {
            $hostReportSent = false;
        }

        // Condominium clearance synchronization (out-of-band side effect, ADR 0008)
        if ($this->clearanceSync !== null) {
            try {
                $clearance = $this->clearanceSync->syncSubmission($reservation, $submission);
            } catch (Throwable $e) {
                error_log('Condominium clearance synchronization failed for reservation ' . $reservation->reservationUid . ': ' . $e->getMessage());
            }
        }

        // 8. Generate unlocked guide URL
        $guideUrl = $this->urlBuilder->buildGuideUrl($submission->lang, $reservation->reservationUid);

        // 9. Dispatch Access Credentials email to Primary Guest (ADR 0001, Ticket #131)
        $accessDispatchSent = false;
        if (trim($submission->primaryGuestEmail) !== '') {
            try {
                $recipientEmail = trim($submission->primaryGuestEmail);
                $lang = $submission->lang !== '' ? $submission->lang : ($reservation->lang !== '' ? $reservation->lang : 'en');
                $primaryOccupantName = $submission->getPrimaryOccupant()->name;
                $recipientName = $primaryOccupantName !== '' ? $primaryOccupantName : $reservation->guestName;

                $parkingSpot = null;
                if ($submission->hasVehicle()) {
                    $creds = $this->credentialsProvider->getCredentials($reservation->propertyId);
                    $parkingSpot = $creds?->parkingSpot;
                }

                $dispatchSubject = $this->accessDispatchRenderer->renderSubject($reservation, $lang);
                $dispatchHtml = $this->accessDispatchRenderer->renderHtml(
                    reservation: $reservation,
                    doorCode: $doorCode,
                    guideUrl: $guideUrl,
                    lang: $lang,
                    recipientName: $recipientName,
                    parkingSpot: $parkingSpot
                );

                $accessDispatchSent = $this->emailSender->send($recipientEmail, $dispatchSubject, $dispatchHtml);
            } catch (Throwable $e) {
                error_log('Access Dispatch email transmission failed for reservation ' . $reservation->reservationUid . ': ' . $e->getMessage());
                $accessDispatchSent = false;
            }
        }

        // 10. Fetch fresh reservation
        $freshReservation = $this->reservationRepository->findByUid($reservation->reservationUid)
            ?? $reservation->withRegistryCompleted(new DateTimeImmutable(), $doorCode);

        return RegistryFulfillmentResult::success(
            reservation: $freshReservation,
            doorCode: $doorCode,
            guideUrl: $guideUrl,
            hostReportDispatched: $hostReportSent,
            accessDispatchDispatched: $accessDispatchSent
        );
    }

    /**
     * {@inheritdoc}
     */
    public function completeRegistryManually(
        string $reservationUid,
        ?AdminContext $admin = null,
        ?GuestRegistrySubmission $submission = null
    ): RegistryFulfillmentResult {
        $reservation = $this->reservationRepository->findByUid($reservationUid);
        if ($reservation === null) {
            return RegistryFulfillmentResult::notFound(
                $reservationUid,
                'Reservation not found'
            );
        }

        $existingCode = $reservation->doorCode;
        $doorCode = ($existingCode !== null && $existingCode !== '')
            ? $existingCode
            : DoorCodeGenerator::generateRandom();

        $this->pdo->beginTransaction();
        try {
            $this->reservationRepository->markRegistryCompleted(
                $reservationUid,
                new DateTimeImmutable(),
                $doorCode
            );

            if ($submission !== null) {
                $adminIp = $admin !== null ? $admin->ipAddress : null;
                $this->insertGuestRegistry(
                    reservationUid: $reservationUid,
                    propertyId: $submission->propertyId !== '' ? $submission->propertyId : $reservation->propertyId,
                    checkIn: $submission->checkIn !== '' ? $submission->checkIn : $reservation->checkIn,
                    checkOut: $submission->checkOut !== '' ? $submission->checkOut : $reservation->checkOut,
                    occupants: $submission->occupants,
                    carPlates: $submission->carPlates,
                    carModel: $submission->carModel,
                    ipAddress: $submission->ipAddress ?? $adminIp,
                    privacyPolicyVersion: $submission->privacyConsent ? ($submission->privacyPolicyVersion !== '' ? $submission->privacyPolicyVersion : '2026-10') : null,
                    privacyConsentAt: $submission->privacyConsent ? ($submission->privacyConsentAt ?? new DateTimeImmutable())->format('Y-m-d H:i:s') : null,
                    privacyConsentIp: $submission->privacyConsent ? ($submission->ipAddress ?? $adminIp ?? '127.0.0.1') : null,
                    minorGuardianConsent: $submission->minorGuardianConsent
                );
            }

            $stmtAudit = $this->pdo->prepare("
                INSERT INTO `admin_audit_logs` (
                    `admin_user_id`, `action`, `entity_type`, `entity_id`,
                    `payload_before`, `payload_after`, `ip_address`, `user_agent`, `created_at`
                ) VALUES (
                    :admin_user_id, 'registry_manual_complete', 'reservation', :entity_id,
                    :payload_before, :payload_after, :ip_address, :user_agent, CURRENT_TIMESTAMP
                )
            ");
            $stmtAudit->execute([
                ':admin_user_id' => $admin?->adminUserId,
                ':entity_id' => $reservationUid,
                ':payload_before' => json_encode([
                    'registry_completed' => (int) $reservation->registryCompleted,
                    'door_code' => $existingCode,
                ], JSON_UNESCAPED_SLASHES),
                ':payload_after' => json_encode([
                    'registry_completed' => 1,
                    'door_code' => $doorCode,
                ], JSON_UNESCAPED_SLASHES),
                ':ip_address' => $admin !== null && $admin->ipAddress !== null ? $admin->ipAddress : '127.0.0.1',
                ':user_agent' => $admin?->userAgent,
            ]);

            $this->pdo->commit();
        } catch (Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            return RegistryFulfillmentResult::systemError('Database transaction failed: ' . $e->getMessage());
        }

        // Condominium clearance synchronization (out-of-band side effect, ADR 0008, Ticket #156)
        if ($this->clearanceSync !== null) {
            try {
                $this->clearanceSync->syncForReservation($reservationUid, $admin);
            } catch (\Throwable $e) {
                error_log("Failed to sync condominium clearance during manual registry completion for {$reservationUid}: " . $e->getMessage());
            }
        }

        $lang = $submission !== null ? $submission->lang : $reservation->lang;
        $guideUrl = $this->urlBuilder->buildGuideUrl($lang, $reservationUid);

        $freshReservation = $this->reservationRepository->findByUid($reservationUid)
            ?? $reservation->withRegistryCompleted(new DateTimeImmutable(), $doorCode);

        return RegistryFulfillmentResult::success(
            reservation: $freshReservation,
            doorCode: $doorCode,
            guideUrl: $guideUrl,
            hostReportDispatched: false
        );
    }

    /**
     * {@inheritdoc}
     */
    public function overrideDoorCode(
        string $reservationUid,
        string $rawDoorCode,
        ?AdminContext $admin = null
    ): DoorCodeResult {
        $reservation = $this->reservationRepository->findByUid($reservationUid);
        if ($reservation === null) {
            return DoorCodeResult::failure('Reservation not found');
        }

        if (!$reservation->status->isConfirmed()) {
            return DoorCodeResult::failure('PIN modification is strictly restricted to confirmed reservations.');
        }

        $trimmed = trim($rawDoorCode);
        if (!preg_match('/^[0-9]{4,10}#?$/', $trimmed)) {
            return DoorCodeResult::failure('Invalid PIN format. Code must be 4 to 10 digits optionally ending with #.');
        }

        $formattedCode = str_ends_with($trimmed, '#') ? $trimmed : $trimmed . '#';

        $this->pdo->beginTransaction();
        try {
            $this->reservationRepository->updateDoorCode($reservationUid, $formattedCode);

            $stmtAudit = $this->pdo->prepare("
                INSERT INTO `admin_audit_logs` (
                    `admin_user_id`, `action`, `entity_type`, `entity_id`,
                    `payload_before`, `payload_after`, `ip_address`, `user_agent`, `created_at`
                ) VALUES (
                    :admin_user_id, 'pin_override', 'reservation', :entity_id,
                    :payload_before, :payload_after, :ip_address, :user_agent, CURRENT_TIMESTAMP
                )
            ");
            $stmtAudit->execute([
                ':admin_user_id' => $admin?->adminUserId,
                ':entity_id' => $reservationUid,
                ':payload_before' => json_encode(['door_code' => $reservation->doorCode], JSON_UNESCAPED_SLASHES),
                ':payload_after' => json_encode(['door_code' => $formattedCode], JSON_UNESCAPED_SLASHES),
                ':ip_address' => $admin !== null && $admin->ipAddress !== null ? $admin->ipAddress : '127.0.0.1',
                ':user_agent' => $admin?->userAgent,
            ]);

            $this->pdo->commit();
            return DoorCodeResult::success($formattedCode);
        } catch (Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            return DoorCodeResult::failure('Failed to update door PIN: ' . $e->getMessage());
        }
    }

    /**
     * {@inheritdoc}
     */
    public function regenerateDoorCode(
        string $reservationUid,
        ?AdminContext $admin = null
    ): DoorCodeResult {
        $reservation = $this->reservationRepository->findByUid($reservationUid);
        if ($reservation === null) {
            return DoorCodeResult::failure('Reservation not found');
        }

        if (!$reservation->status->isConfirmed()) {
            return DoorCodeResult::failure('PIN modification is strictly restricted to confirmed reservations.');
        }

        $newCode = DoorCodeGenerator::generateRandom();

        $this->pdo->beginTransaction();
        try {
            $this->reservationRepository->updateDoorCode($reservationUid, $newCode);

            $stmtAudit = $this->pdo->prepare("
                INSERT INTO `admin_audit_logs` (
                    `admin_user_id`, `action`, `entity_type`, `entity_id`,
                    `payload_before`, `payload_after`, `ip_address`, `user_agent`, `created_at`
                ) VALUES (
                    :admin_user_id, 'pin_regenerate', 'reservation', :entity_id,
                    :payload_before, :payload_after, :ip_address, :user_agent, CURRENT_TIMESTAMP
                )
            ");
            $stmtAudit->execute([
                ':admin_user_id' => $admin?->adminUserId,
                ':entity_id' => $reservationUid,
                ':payload_before' => json_encode(['door_code' => $reservation->doorCode], JSON_UNESCAPED_SLASHES),
                ':payload_after' => json_encode(['door_code' => $newCode], JSON_UNESCAPED_SLASHES),
                ':ip_address' => $admin !== null && $admin->ipAddress !== null ? $admin->ipAddress : '127.0.0.1',
                ':user_agent' => $admin?->userAgent,
            ]);

            $this->pdo->commit();
            return DoorCodeResult::success($newCode);
        } catch (Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            return DoorCodeResult::failure('Failed to regenerate door PIN: ' . $e->getMessage());
        }
    }

    /**
     * Helper to persist guest registry occupant records uniformly.
     *
     * @param array<int, OccupantDetails> $occupants
     */
    private function insertGuestRegistry(
        string $reservationUid,
        string $propertyId,
        string $checkIn,
        string $checkOut,
        array $occupants,
        ?string $carPlates,
        ?string $carModel,
        ?string $ipAddress,
        ?string $privacyPolicyVersion = null,
        ?string $privacyConsentAt = null,
        ?string $privacyConsentIp = null,
        bool $minorGuardianConsent = false
    ): void {
        $occupantsArray = array_map(fn (OccupantDetails $o): array => $o->toArray(), $occupants);
        $guestsPayloadJson = PayloadEncryptor::encrypt($occupantsArray);

        $hasConsentCols = true;
        $driver = (string) $this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
        if ($driver === 'sqlite') {
            try {
                $colsStmt = $this->pdo->query("PRAGMA table_info(`guest_registries`)");
                if ($colsStmt !== false) {
                    $existingCols = [];
                    foreach ($colsStmt->fetchAll(PDO::FETCH_ASSOC) as $colInfo) {
                        $existingCols[(string) $colInfo['name']] = true;
                    }
                    $hasConsentCols = isset(
                        $existingCols['privacy_policy_version'],
                        $existingCols['privacy_consent_at'],
                        $existingCols['privacy_consent_ip'],
                        $existingCols['minor_guardian_consent']
                    );
                }
            } catch (Throwable) {
            }
        }

        $stmtCheck = $this->pdo->prepare('SELECT id, car_plates, car_model FROM `guest_registries` WHERE `reservation_uid` = :uid ORDER BY `id` DESC LIMIT 1');
        $stmtCheck->execute([':uid' => $reservationUid]);
        /** @var array<string, mixed>|false $existing */
        $existing = $stmtCheck->fetch(PDO::FETCH_ASSOC);

        if ($existing !== false) {
            $effectivePlates = ($carPlates !== null && trim($carPlates) !== '')
                ? $carPlates
                : (isset($existing['car_plates']) && trim((string) $existing['car_plates']) !== '' ? (string) $existing['car_plates'] : null);
            $effectiveModel = ($carModel !== null && trim($carModel) !== '')
                ? $carModel
                : (isset($existing['car_model']) && trim((string) $existing['car_model']) !== '' ? (string) $existing['car_model'] : null);

            if ($hasConsentCols) {
                $stmtUpdate = $this->pdo->prepare("
                    UPDATE `guest_registries`
                    SET `property_id` = :property_id,
                        `check_in` = :check_in,
                        `check_out` = :check_out,
                        `guest_count` = :guest_count,
                        `guests_payload` = :guests_payload,
                        `car_plates` = :car_plates,
                        `car_model` = :car_model,
                        `ip_address` = :ip_address,
                        `privacy_policy_version` = :privacy_policy_version,
                        `privacy_consent_at` = :privacy_consent_at,
                        `privacy_consent_ip` = :privacy_consent_ip,
                        `minor_guardian_consent` = :minor_guardian_consent
                    WHERE `id` = :id
                ");
                $stmtUpdate->execute([
                    ':property_id' => $propertyId,
                    ':check_in' => $checkIn,
                    ':check_out' => $checkOut,
                    ':guest_count' => count($occupants),
                    ':guests_payload' => $guestsPayloadJson,
                    ':car_plates' => $effectivePlates,
                    ':car_model' => $effectiveModel,
                    ':ip_address' => $ipAddress,
                    ':privacy_policy_version' => $privacyPolicyVersion,
                    ':privacy_consent_at' => $privacyConsentAt,
                    ':privacy_consent_ip' => $privacyConsentIp,
                    ':minor_guardian_consent' => $minorGuardianConsent ? 1 : 0,
                    ':id' => (int) $existing['id'],
                ]);
                return;
            }

            $stmtUpdate = $this->pdo->prepare("
                UPDATE `guest_registries`
                SET `property_id` = :property_id,
                    `check_in` = :check_in,
                    `check_out` = :check_out,
                    `guest_count` = :guest_count,
                    `guests_payload` = :guests_payload,
                    `car_plates` = :car_plates,
                    `car_model` = :car_model,
                    `ip_address` = :ip_address
                WHERE `id` = :id
            ");
            $stmtUpdate->execute([
                ':property_id' => $propertyId,
                ':check_in' => $checkIn,
                ':check_out' => $checkOut,
                ':guest_count' => count($occupants),
                ':guests_payload' => $guestsPayloadJson,
                ':car_plates' => $effectivePlates,
                ':car_model' => $effectiveModel,
                ':ip_address' => $ipAddress,
                ':id' => (int) $existing['id'],
            ]);
            return;
        }

        if ($hasConsentCols) {
            $stmtReg = $this->pdo->prepare("
                INSERT INTO `guest_registries` (
                    `reservation_uid`, `property_id`, `check_in`, `check_out`,
                    `guest_count`, `guests_payload`, `car_plates`, `car_model`,
                    `ip_address`, `privacy_policy_version`, `privacy_consent_at`,
                    `privacy_consent_ip`, `minor_guardian_consent`, `created_at`
                ) VALUES (
                    :reservation_uid, :property_id, :check_in, :check_out,
                    :guest_count, :guests_payload, :car_plates, :car_model,
                    :ip_address, :privacy_policy_version, :privacy_consent_at,
                    :privacy_consent_ip, :minor_guardian_consent, CURRENT_TIMESTAMP
                )
            ");
            $stmtReg->execute([
                ':reservation_uid' => $reservationUid,
                ':property_id' => $propertyId,
                ':check_in' => $checkIn,
                ':check_out' => $checkOut,
                ':guest_count' => count($occupants),
                ':guests_payload' => $guestsPayloadJson,
                ':car_plates' => $carPlates,
                ':car_model' => $carModel,
                ':ip_address' => $ipAddress,
                ':privacy_policy_version' => $privacyPolicyVersion,
                ':privacy_consent_at' => $privacyConsentAt,
                ':privacy_consent_ip' => $privacyConsentIp,
                ':minor_guardian_consent' => $minorGuardianConsent ? 1 : 0,
            ]);
            return;
        }

        $stmtReg = $this->pdo->prepare("
            INSERT INTO `guest_registries` (
                `reservation_uid`, `property_id`, `check_in`, `check_out`,
                `guest_count`, `guests_payload`, `car_plates`, `car_model`,
                `ip_address`, `created_at`
            ) VALUES (
                :reservation_uid, :property_id, :check_in, :check_out,
                :guest_count, :guests_payload, :car_plates, :car_model,
                :ip_address, CURRENT_TIMESTAMP
            )
        ");
        $stmtReg->execute([
            ':reservation_uid' => $reservationUid,
            ':property_id' => $propertyId,
            ':check_in' => $checkIn,
            ':check_out' => $checkOut,
            ':guest_count' => count($occupants),
            ':guests_payload' => $guestsPayloadJson,
            ':car_plates' => $carPlates,
            ':car_model' => $carModel,
            ':ip_address' => $ipAddress,
        ]);
    }

    private function translateRegistryError(string $lang, string $key, string $fallback): string
    {
        try {
            $translations = PathResolver::loadTranslations();
            $normalizedLang = strtolower(trim($lang));
            if (isset($translations[$normalizedLang]['registry'][$key]) && is_string($translations[$normalizedLang]['registry'][$key])) {
                return $translations[$normalizedLang]['registry'][$key];
            }
            if (isset($translations['en']['registry'][$key]) && is_string($translations['en']['registry'][$key])) {
                return $translations['en']['registry'][$key];
            }
        } catch (Throwable) {
        }

        return $fallback;
    }

    /**
     * Evaluates whether a reservation's guest email qualifies for enrichment
     * with the Primary Guest's verified email upon Guest Registry submission.
     *
     * Criteria (Q7-A):
     * - source === 'airbnb' OR
     * - UID starts with 'res-abnb-' OR
     * - email contains 'airbnb.com' OR
     * - email is empty OR
     * - email starts with 'guest@' OR
     * - email starts with 'none@'
     */
    private function shouldEnrichReservationEmail(Reservation $reservation): bool
    {
        return $reservation->hasExternalOrPlaceholderEmail();
    }
}
