<?php

declare(strict_types=1);

namespace OceanViewFlats\Admin\Service;

use DateTimeImmutable;
use InvalidArgumentException;
use OceanViewFlats\Admin\Audit\AuditLogger;
use OceanViewFlats\Domain\Access\ConfigPropertyCredentialsProvider;
use OceanViewFlats\Domain\Access\DoorCodeGenerator;
use OceanViewFlats\Domain\Fulfillment\AccessDispatchEmailRenderer;
use OceanViewFlats\Domain\Fulfillment\AdminContext;
use OceanViewFlats\Domain\Fulfillment\CondominiumClearance;
use OceanViewFlats\Domain\Fulfillment\CondominiumClearanceSyncInterface;
use OceanViewFlats\Domain\Fulfillment\EmailSenderInterface;
use OceanViewFlats\Domain\Fulfillment\OccupantDetails;
use OceanViewFlats\Domain\Reservation\Dashboard\AlertType;
use OceanViewFlats\Domain\Reservation\Dashboard\OperationalAlertDismissalRepositoryInterface;
use OceanViewFlats\Domain\Reservation\Dashboard\PdoOperationalAlertDismissalRepository;
use OceanViewFlats\Domain\Reservation\Reservation;
use OceanViewFlats\Domain\Reservation\ReservationLedgerInterface;
use OceanViewFlats\Domain\Reservation\ReservationRepositoryInterface;
use OceanViewFlats\Domain\Reservation\Search\InMemoryReservationSearchAdapter;
use OceanViewFlats\Domain\Reservation\Search\ReservationSearchInterface;
use PDO;
use Throwable;

/**
 * Application service orchestrating administrative updates to active Confirmed Reservations,
 * including stay rescheduling, Occupancy Override (max_guests) validation, vehicle registration,
 * Guest Registry occupant roster management, atomic persistence, audit logging, and opt-in fulfillment.
 */
final class AdminReservationUpdateService
{
    private readonly ?OperationalAlertDismissalRepositoryInterface $alertDismissalRepo;

    public function __construct(
        private readonly ReservationRepositoryInterface $repository,
        private readonly ReservationSearchInterface $search,
        private readonly ReservationLedgerInterface $ledger,
        private readonly AuditLogger $auditLogger,
        private readonly EmailSenderInterface $emailSender,
        private readonly PublicUrlBuilder $urlBuilder,
        private readonly string $publicSiteUrl = 'https://oceanviewflats.com',
        private readonly ?PDO $pdo = null,
        private readonly ?CondominiumClearanceSyncInterface $clearanceSync = null,
        ?OperationalAlertDismissalRepositoryInterface $alertDismissalRepo = null
    ) {
        $this->alertDismissalRepo = $alertDismissalRepo
            ?? ($this->pdo !== null ? new PdoOperationalAlertDismissalRepository($this->pdo) : null);
    }

    /**
     * Validates and applies an administrative update to a confirmed reservation and its Guest Registry.
     *
     * @param array<string, mixed>|null $registry
     * @param array<string, mixed> $body
     * @return array{
     *     success: bool,
     *     reservation?: Reservation,
     *     errorMessage?: string,
     *     conflictReasons?: list<string>|null,
     *     registeredGuestCount: int
     * }
     */
    public function updateReservation(
        Reservation $reservation,
        ?array $registry,
        array $body,
        ?int $adminUserId,
        string $ipAddress,
        string $userAgent
    ): array {
        $uid = $reservation->reservationUid;
        $registeredGuestCount = $this->resolveRegisteredGuestCount($registry);

        $propertyId = array_key_exists('property_id', $body)
            ? trim((string) $body['property_id'])
            : $reservation->propertyId;
        $checkIn = array_key_exists('check_in', $body)
            ? trim((string) $body['check_in'])
            : $reservation->checkIn;
        $checkOut = array_key_exists('check_out', $body)
            ? trim((string) $body['check_out'])
            : $reservation->checkOut;
        $totalPriceRaw = array_key_exists('total_price', $body)
            ? $body['total_price']
            : (string) $reservation->totalPrice;
        $maxGuestsRaw = array_key_exists('max_guests', $body)
            ? $body['max_guests']
            : (string) $reservation->maxGuests;
        $guestName = array_key_exists('guest_name', $body)
            ? trim((string) $body['guest_name'])
            : $reservation->guestName;
        $guestEmail = array_key_exists('guest_email', $body)
            ? trim((string) $body['guest_email'])
            : $reservation->guestEmail;
        $guestPhone = array_key_exists('guest_phone', $body)
            ? trim((string) $body['guest_phone'])
            : $reservation->guestPhone;
        $lang = array_key_exists('lang', $body)
            ? strtolower(trim((string) $body['lang']))
            : $reservation->lang;
        $externalConfirmationCode = array_key_exists('external_confirmation_code', $body)
            ? trim((string) $body['external_confirmation_code'])
            : (string) ($reservation->externalConfirmationCode ?? '');
        $notes = array_key_exists('notes', $body)
            ? trim((string) $body['notes'])
            : (string) ($reservation->notes ?? '');

        $rosterExtraction = $this->extractSubmittedRoster($body);
        $rosterSubmitted = $rosterExtraction['rosterSubmitted'];
        $resetRequested = $rosterExtraction['resetRequested'];
        $rawOccupants = $rosterExtraction['rawOccupants'];

        $vehicleSubmitted = array_key_exists('car_plates', $body) || array_key_exists('car_model', $body);
        $carPlatesRaw = $resetRequested
            ? ''
            : (array_key_exists('car_plates', $body)
                ? trim((string) $body['car_plates'])
                : trim((string) ($registry['car_plates'] ?? '')));
        $carModelRaw = $resetRequested
            ? ''
            : (array_key_exists('car_model', $body)
                ? trim((string) $body['car_model'])
                : trim((string) ($registry['car_model'] ?? '')));
        $carPlates = $carPlatesRaw !== '' ? $carPlatesRaw : null;
        $carModel = $carModelRaw !== '' ? $carModelRaw : null;

        $syncClearance = $this->parseCheckbox($body['sync_condominium_clearance'] ?? null);
        $sendAccessDispatch = $this->parseCheckbox($body['send_access_dispatch'] ?? null);

        // 1. Validate Property
        if (!in_array($propertyId, ['1606', '1707'], true)) {
            return $this->failure($registeredGuestCount, 'Please select a valid property (1606 or 1707).');
        }

        // 2. Validate Check-in and Check-out Dates
        $checkInDate = DateTimeImmutable::createFromFormat('Y-m-d', $checkIn);
        $checkOutDate = DateTimeImmutable::createFromFormat('Y-m-d', $checkOut);
        $isValidCheckIn = ($checkInDate !== false) && ($checkInDate->format('Y-m-d') === $checkIn);
        $isValidCheckOut = ($checkOutDate !== false) && ($checkOutDate->format('Y-m-d') === $checkOut);

        if (!$isValidCheckIn || !$isValidCheckOut) {
            return $this->failure($registeredGuestCount, 'Check-in and check-out dates must be valid dates in Y-m-d format.');
        }

        if ($checkIn >= $checkOut) {
            return $this->failure($registeredGuestCount, 'Check-out date must be strictly after check-in date.');
        }

        // 3. Validate Language
        if (!in_array($lang, ['en', 'es'], true)) {
            return $this->failure($registeredGuestCount, 'Language must be either "en" or "es".');
        }

        // 4. Validate Total Price (>= 0)
        if ($totalPriceRaw === null || trim((string) $totalPriceRaw) === '' || !is_numeric($totalPriceRaw)) {
            return $this->failure($registeredGuestCount, 'Total price must be a valid non-negative number.');
        }
        $totalPrice = (float) $totalPriceRaw;
        if ($totalPrice < 0.0) {
            return $this->failure($registeredGuestCount, 'Total price cannot be negative.');
        }

        // 5. Validate Guest Registry Occupants (when submitted)
        /** @var list<OccupantDetails> $validatedOccupants */
        $validatedOccupants = [];
        if ($rosterSubmitted && count($rawOccupants) > 0) {
            if (count($rawOccupants) > Reservation::MAX_MAX_GUESTS) {
                return $this->failure(
                    $registeredGuestCount,
                    'Cannot register more than 12 guests. Maximum occupancy (max_guests) must be between 1 and 12.'
                );
            }

            $validationOutcome = $this->validateAndNormalizeOccupants($rawOccupants, $body);
            if (is_string($validationOutcome)) {
                return $this->failure($registeredGuestCount, $validationOutcome);
            }
            $validatedOccupants = $validationOutcome;
        }

        // 6. Validate Occupancy Override (max_guests 1..12, lowering guard, and auto-bump when adding guests)
        $maxGuestsValidated = filter_var($maxGuestsRaw, FILTER_VALIDATE_INT);
        if ($maxGuestsValidated === false) {
            return $this->failure($registeredGuestCount, 'Maximum occupancy (max_guests) must be between 1 and 12.');
        }
        $maxGuests = (int) $maxGuestsValidated;
        if ($maxGuests < Reservation::MIN_MAX_GUESTS || $maxGuests > Reservation::MAX_MAX_GUESTS) {
            return $this->failure($registeredGuestCount, 'Maximum occupancy (max_guests) must be between 1 and 12.');
        }

        if (!$rosterSubmitted) {
            if ($registeredGuestCount > 0 && $maxGuests < $registeredGuestCount) {
                return $this->failure(
                    $registeredGuestCount,
                    sprintf(
                        'Cannot lower maximum occupancy (%d) below the %d currently registered guests. Please remove excess guests from the Guest Registry first.',
                        $maxGuests,
                        $registeredGuestCount
                    )
                );
            }
        } else {
            $submittedCount = count($validatedOccupants);
            if ($submittedCount > 0 && $maxGuests < $submittedCount) {
                if ($maxGuests < $reservation->maxGuests || $submittedCount <= $registeredGuestCount) {
                    return $this->failure(
                        $submittedCount,
                        sprintf(
                            'Cannot lower maximum occupancy (%d) below the %d currently registered guests. Please remove excess guests from the Guest Registry first.',
                            $maxGuests,
                            $submittedCount
                        )
                    );
                }
                $maxGuests = $submittedCount;
            }
        }

        // 7. Enrich placeholder reservation email from Guest #1 when saving >= 1 valid Guests
        if ($rosterSubmitted && count($validatedOccupants) >= 1) {
            $primaryOccEmail = trim((string) ($validatedOccupants[0]->email ?? ''));
            if (
                $primaryOccEmail !== ''
                && (
                    Reservation::isPlaceholderEmail($guestEmail)
                    || ($reservation->hasExternalOrPlaceholderEmail() && strcasecmp($guestEmail, $reservation->guestEmail) === 0)
                )
            ) {
                $guestEmail = $primaryOccEmail;
            }
        }

        // 8. Source-dependent contact validation
        $isAirbnb = $reservation->isAirbnb();
        if ($guestName === '') {
            return $this->failure($registeredGuestCount, 'Guest name is required.');
        }

        if ($isAirbnb) {
            if ($externalConfirmationCode === '') {
                return $this->failure($registeredGuestCount, 'Airbnb confirmation code is required.');
            }
            if ($guestEmail === '') {
                $cleanCode = strtolower(preg_replace('/[^a-zA-Z0-9]/', '', $externalConfirmationCode) ?? 'guest');
                $guestEmail = 'airbnb-' . $cleanCode . '@guest.oceanviewflats.com';
            } elseif (!filter_var($guestEmail, FILTER_VALIDATE_EMAIL)) {
                return $this->failure($registeredGuestCount, 'A valid guest email address is required.');
            }
            if ($guestPhone === '') {
                $guestPhone = 'N/A';
            } elseif ($guestPhone !== 'N/A' && (strlen($guestPhone) < 7 || strlen($guestPhone) > 25)) {
                return $this->failure($registeredGuestCount, 'Guest phone number must be between 7 and 25 characters.');
            }
        } else {
            if ($guestEmail === '' || !filter_var($guestEmail, FILTER_VALIDATE_EMAIL)) {
                return $this->failure($registeredGuestCount, 'A valid guest email address is required.');
            }
            if (strlen($guestPhone) < 7 || strlen($guestPhone) > 25) {
                return $this->failure($registeredGuestCount, 'Guest phone number must be between 7 and 25 characters.');
            }
        }

        // 9. Validate Reservation Ledger availability with self-conflict exclusion when stay dates or Property change
        $stayChanged = (
            $propertyId !== $reservation->propertyId
            || $checkIn !== $reservation->checkIn
            || $checkOut !== $reservation->checkOut
        );

        if ($stayChanged) {
            $absorbingSource = $isAirbnb ? 'airbnb' : null;
            $isAvailable = $this->ledger->isAvailable(
                $propertyId,
                $checkIn,
                $checkOut,
                null,
                $absorbingSource,
                $reservation->reservationUid
            );
            if (!$isAvailable) {
                $conflictReasons = $this->ledger->getConflictReasons(
                    $propertyId,
                    $checkIn,
                    $checkOut,
                    null,
                    $absorbingSource,
                    $reservation->reservationUid
                );
                $reasonText = !empty($conflictReasons)
                    ? implode('; ', $conflictReasons)
                    : 'Selected dates conflict with an existing reservation or block.';
                return $this->failure(
                    $registeredGuestCount,
                    'Selected dates conflict: ' . $reasonText,
                    $conflictReasons
                );
            }
        }

        // 10. Resolve registry completion state & Access Credential (door_code)
        $now = new DateTimeImmutable();
        if ($rosterSubmitted) {
            if (count($validatedOccupants) >= 1) {
                $newRegistryCompleted = true;
                $newRegistryCompletedAt = ($reservation->registryCompleted && $reservation->registryCompletedAt !== null)
                    ? $reservation->registryCompletedAt
                    : $now;
                $existingDoorCode = $reservation->doorCode;
                if ($existingDoorCode !== null && trim($existingDoorCode) !== '') {
                    $newDoorCode = $existingDoorCode;
                } else {
                    $phoneForPin = strtoupper($guestPhone) !== 'N/A'
                        ? $guestPhone
                        : (string) ($validatedOccupants[0]->phone ?? '');
                    $newDoorCode = DoorCodeGenerator::generate($validatedOccupants[0]->docNum, $phoneForPin);
                }
            } else {
                $newRegistryCompleted = false;
                $newRegistryCompletedAt = null;
                $newDoorCode = $reservation->doorCode;
            }
        } else {
            $newRegistryCompleted = $reservation->registryCompleted;
            $newRegistryCompletedAt = $reservation->registryCompletedAt;
            $newDoorCode = $reservation->doorCode;
        }

        // 11. Build before payload and updated Reservation entity via immutable mutator
        $beforePayload = $reservation->toArray();
        $beforePayload['car_plates'] = $registry['car_plates'] ?? null;
        $beforePayload['car_model'] = $registry['car_model'] ?? null;
        $beforePayload['guest_registry'] = $this->formatRegistryForAudit(
            $registry,
            $reservation->propertyId,
            $reservation->checkIn,
            $reservation->checkOut
        );

        $updatedReservation = $reservation->withUpdatedDetails(
            propertyId: $propertyId,
            guestName: $guestName,
            guestEmail: $guestEmail,
            guestPhone: $guestPhone,
            checkIn: $checkIn,
            checkOut: $checkOut,
            totalPrice: $totalPrice,
            lang: $lang,
            registryCompleted: $newRegistryCompleted,
            registryCompletedAt: $newRegistryCompletedAt,
            doorCode: $newDoorCode,
            notes: $notes !== '' ? $notes : null,
            externalConfirmationCode: $externalConfirmationCode !== '' ? $externalConfirmationCode : null,
            maxGuests: $maxGuests,
            updatedAt: $now
        );

        // 12. Atomic Transaction: save Reservation, upsert/delete/cascade guest_registries, and record Audit Log
        $inExternalTransaction = $this->pdo !== null && $this->pdo->inTransaction();
        if ($this->pdo !== null && !$inExternalTransaction) {
            $this->pdo->beginTransaction();
        }

        try {
            $savedReservation = $this->repository->save($updatedReservation);

            if ($resetRequested) {
                $this->deleteGuestRegistryRecord($uid);
                $this->alertDismissalRepo?->clearDismissal(AlertType::registryAlertId($uid));
            } elseif ($rosterSubmitted) {
                if (count($validatedOccupants) >= 1) {
                    $occupantsPayloadArray = array_map(
                        fn(OccupantDetails $o): array => $o->toArray(),
                        $validatedOccupants
                    );
                    $this->upsertGuestRegistryRecord(
                        uid: $uid,
                        propertyId: $propertyId,
                        checkIn: $checkIn,
                        checkOut: $checkOut,
                        occupantsPayloadArray: $occupantsPayloadArray,
                        carPlates: $carPlates,
                        carModel: $carModel,
                        ipAddress: $ipAddress,
                        now: $now
                    );
                } elseif ($carPlates !== null || $carModel !== null) {
                    $this->upsertGuestRegistryRecord(
                        uid: $uid,
                        propertyId: $propertyId,
                        checkIn: $checkIn,
                        checkOut: $checkOut,
                        occupantsPayloadArray: [],
                        carPlates: $carPlates,
                        carModel: $carModel,
                        ipAddress: $ipAddress,
                        now: $now
                    );
                } else {
                    $this->deleteGuestRegistryRecord($uid);
                }
            } elseif ($stayChanged || $vehicleSubmitted) {
                if ($registeredGuestCount === 0 && $registry === null) {
                    if ($carPlates !== null || $carModel !== null) {
                        $this->upsertGuestRegistryRecord(
                            uid: $uid,
                            propertyId: $propertyId,
                            checkIn: $checkIn,
                            checkOut: $checkOut,
                            occupantsPayloadArray: [],
                            carPlates: $carPlates,
                            carModel: $carModel,
                            ipAddress: $ipAddress,
                            now: $now
                        );
                    }
                } elseif ($registeredGuestCount === 0 && $carPlates === null && $carModel === null) {
                    $this->deleteGuestRegistryRecord($uid);
                } elseif ($this->pdo !== null) {
                    $stmtCascade = $this->pdo->prepare('
                        UPDATE guest_registries
                        SET property_id = :property_id,
                            check_in = :check_in,
                            check_out = :check_out,
                            car_plates = :car_plates,
                            car_model = :car_model
                        WHERE reservation_uid = :uid
                    ');
                    $stmtCascade->execute([
                        ':property_id' => $propertyId,
                        ':check_in' => $checkIn,
                        ':check_out' => $checkOut,
                        ':car_plates' => $carPlates,
                        ':car_model' => $carModel,
                        ':uid' => $uid,
                    ]);
                }
            }

            $afterRegistry = $this->search->findGuestRegistry($uid);
            $afterPayload = $savedReservation->toArray();
            $afterPayload['car_plates'] = $afterRegistry['car_plates'] ?? null;
            $afterPayload['car_model'] = $afterRegistry['car_model'] ?? null;
            $afterPayload['guest_registry'] = $this->formatRegistryForAudit(
                $afterRegistry,
                $propertyId,
                $checkIn,
                $checkOut
            );

            $this->auditLogger->record(
                action: 'reservation_updated',
                entityType: 'reservation',
                entityId: $uid,
                before: $beforePayload,
                after: $afterPayload,
                adminUserId: $adminUserId,
                ipAddress: $ipAddress,
                userAgent: $userAgent
            );

            if ($this->pdo !== null && !$inExternalTransaction && $this->pdo->inTransaction()) {
                $this->pdo->commit();
            }
        } catch (Throwable $e) {
            if ($this->pdo !== null && !$inExternalTransaction && $this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            return $this->failure($registeredGuestCount, 'Failed to update reservation: ' . $e->getMessage());
        }

        // 13. Post-Commit Opt-In Downstream Side Effects
        $afterRegistry = $this->search->findGuestRegistry($uid);
        $afterOccupants = $this->extractRegistryOccupants($afterRegistry);

        if ($syncClearance && $savedReservation->registryCompleted && count($afterOccupants) >= 1) {
            $this->alertDismissalRepo?->clearDismissal(AlertType::clearanceAlertId($uid));
            if ($this->clearanceSync !== null) {
                $adminContext = new AdminContext(
                    adminUserId: $adminUserId,
                    ipAddress: $ipAddress,
                    userAgent: $userAgent
                );
                try {
                    $this->clearanceSync->syncForReservation($uid, $adminContext);
                } catch (Throwable $e) {
                    error_log('Opt-in condominium clearance sync failed for reservation ' . $uid . ': ' . $e->getMessage());
                }
            }
        }

        if ($sendAccessDispatch && $savedReservation->registryCompleted) {
            $doorCodeToSend = (string) ($savedReservation->doorCode ?? '');
            $primaryOccData = $afterOccupants[0] ?? null;
            $primaryOccEmail = is_array($primaryOccData) ? trim((string) ($primaryOccData['email'] ?? '')) : '';
            $recipientEmail = $primaryOccEmail !== '' ? $primaryOccEmail : trim($savedReservation->guestEmail);

            if (
                $doorCodeToSend !== ''
                && $recipientEmail !== ''
                && filter_var($recipientEmail, FILTER_VALIDATE_EMAIL)
                && !Reservation::isPlaceholderEmail($recipientEmail)
            ) {
                try {
                    $dispatchLang = $savedReservation->lang !== '' ? $savedReservation->lang : 'es';
                    $primaryOccName = is_array($primaryOccData) ? trim((string) ($primaryOccData['name'] ?? '')) : '';
                    $recipientName = $primaryOccName !== '' ? $primaryOccName : $savedReservation->guestName;
                    $guideUrl = $this->urlBuilder->buildGuideUrl($dispatchLang, $uid);

                    $parkingSpot = null;
                    $hasVehicle = (($afterRegistry['car_plates'] ?? null) !== null && trim((string) $afterRegistry['car_plates']) !== '')
                        || (($afterRegistry['car_model'] ?? null) !== null && trim((string) $afterRegistry['car_model']) !== '');
                    if ($hasVehicle) {
                        $creds = (new ConfigPropertyCredentialsProvider())->getCredentials($savedReservation->propertyId);
                        $parkingSpot = $creds?->parkingSpot;
                    }

                    $accessRenderer = new AccessDispatchEmailRenderer($this->publicSiteUrl);
                    $subject = $accessRenderer->renderSubject($savedReservation, $dispatchLang);
                    $html = $accessRenderer->renderHtml(
                        reservation: $savedReservation,
                        doorCode: $doorCodeToSend,
                        guideUrl: $guideUrl,
                        lang: $dispatchLang,
                        recipientName: $recipientName,
                        parkingSpot: $parkingSpot
                    );
                    $this->emailSender->send($recipientEmail, $subject, $html);
                } catch (Throwable $e) {
                    error_log('Opt-in Access Dispatch email failed for reservation ' . $uid . ': ' . $e->getMessage());
                }
            }
        }

        return [
            'success' => true,
            'reservation' => $savedReservation,
            'registeredGuestCount' => count($afterOccupants),
        ];
    }

    /**
     * @param array<string, mixed>|null $registry
     */
    public function resolveRegisteredGuestCount(?array $registry): int
    {
        if ($registry === null) {
            return 0;
        }
        $guestsPayload = $registry['guests_payload'] ?? null;
        if (is_array($guestsPayload)) {
            return count($guestsPayload);
        }
        if (is_string($guestsPayload) && $guestsPayload !== '') {
            $decoded = json_decode($guestsPayload, true);
            if (is_array($decoded)) {
                return count($decoded);
            }
        }
        return isset($registry['guest_count']) ? (int) $registry['guest_count'] : 0;
    }

    /**
     * @param array<string, mixed>|null $registry
     * @return list<array<string, mixed>>
     */
    public function extractRegistryOccupants(?array $registry): array
    {
        if ($registry === null) {
            return [];
        }
        $rawPayload = $registry['guests_payload'] ?? [];
        if (is_string($rawPayload) && $rawPayload !== '') {
            $decoded = json_decode($rawPayload, true);
            $rawPayload = is_array($decoded) ? $decoded : [];
        }
        if (!is_array($rawPayload)) {
            return [];
        }
        $occupants = [];
        foreach ($rawPayload as $item) {
            if (is_array($item)) {
                $occupants[] = $item;
            }
        }
        return $occupants;
    }

    /**
     * Extracts submitted Guest Registry roster from POST body.
     *
     * @param array<string, mixed> $body
     * @return array{rosterSubmitted: bool, resetRequested: bool, rawOccupants: list<array<string, mixed>>}
     */
    public function extractSubmittedRoster(array $body): array
    {
        $resetRequested = $this->parseCheckbox($body['reset_guest_registry'] ?? null);
        if ($resetRequested) {
            return [
                'rosterSubmitted' => true,
                'resetRequested' => true,
                'rawOccupants' => [],
            ];
        }

        $hasExplicitGuestCount = array_key_exists('guest_count', $body) && trim((string) $body['guest_count']) !== '';
        $explicitGuestCount = $hasExplicitGuestCount ? (int) $body['guest_count'] : null;

        $slotsWithKeys = [];
        $slotsWithData = [];
        $slotDataMap = [];

        for ($slot = 1; $slot <= 20; $slot++) {
            $keys = [
                "guest_first_name_{$slot}",
                "guest_last_name_{$slot}",
                "guest_name_{$slot}",
                "guest_doc_type_{$slot}",
                "guest_doc_num_{$slot}",
                "guest_age_{$slot}",
                "guest_country_{$slot}",
                "guest_email_{$slot}",
                "guest_phone_{$slot}",
            ];
            $hasAnyKey = false;
            foreach ($keys as $k) {
                if (array_key_exists($k, $body)) {
                    $hasAnyKey = true;
                    break;
                }
            }
            if (!$hasAnyKey) {
                continue;
            }

            $slotsWithKeys[] = $slot;
            $fn = trim((string) ($body["guest_first_name_{$slot}"] ?? ''));
            $ln = trim((string) ($body["guest_last_name_{$slot}"] ?? ''));
            $nm = trim((string) ($body["guest_name_{$slot}"] ?? ''));
            $dt = trim((string) ($body["guest_doc_type_{$slot}"] ?? ''));
            $dn = trim((string) ($body["guest_doc_num_{$slot}"] ?? ''));
            $ag = $body["guest_age_{$slot}"] ?? null;
            $co = trim((string) ($body["guest_country_{$slot}"] ?? ''));
            $em = $body["guest_email_{$slot}"] ?? null;
            $ph = $body["guest_phone_{$slot}"] ?? null;

            $hasMeaningfulData = $fn !== ''
                || $ln !== ''
                || $nm !== ''
                || $dn !== ''
                || ($ag !== null && trim((string) $ag) !== '')
                || ($em !== null && trim((string) $em) !== '')
                || ($ph !== null && trim((string) $ph) !== '');

            if ($hasMeaningfulData) {
                $slotsWithData[] = $slot;
            }

            $slotDataMap[$slot] = [
                'slot' => $slot,
                'first_name' => $fn,
                'last_name' => $ln,
                'name' => $nm,
                'doc_type' => $dt,
                'doc_num' => $dn,
                'age' => $ag,
                'country' => $co,
                'email' => $em,
                'phone' => $ph,
            ];
        }

        if (!$hasExplicitGuestCount && $slotsWithKeys === []) {
            return [
                'rosterSubmitted' => false,
                'resetRequested' => false,
                'rawOccupants' => [],
            ];
        }

        if ($hasExplicitGuestCount && $explicitGuestCount <= 0 && $slotsWithData === []) {
            return [
                'rosterSubmitted' => true,
                'resetRequested' => false,
                'rawOccupants' => [],
            ];
        }

        $maxDataSlot = $slotsWithData !== [] ? max($slotsWithData) : 0;
        $rawOccupants = [];

        if ($explicitGuestCount !== null && $explicitGuestCount > 0) {
            if ($maxDataSlot > $explicitGuestCount || count($slotsWithData) === $explicitGuestCount) {
                foreach ($slotsWithData as $slot) {
                    $rawOccupants[] = $slotDataMap[$slot];
                }
            } else {
                for ($slot = 1; $slot <= $explicitGuestCount; $slot++) {
                    $rawOccupants[] = $slotDataMap[$slot] ?? [
                        'slot' => $slot,
                        'first_name' => '',
                        'last_name' => '',
                        'name' => '',
                        'doc_type' => '',
                        'doc_num' => '',
                        'age' => null,
                        'country' => '',
                        'email' => null,
                        'phone' => null,
                    ];
                }
            }
        } else {
            foreach ($slotsWithKeys as $slot) {
                $rawOccupants[] = $slotDataMap[$slot];
            }
        }

        return [
            'rosterSubmitted' => true,
            'resetRequested' => false,
            'rawOccupants' => $rawOccupants,
        ];
    }

    /**
     * Determines whether the Guest Registry or stay parameters have been modified since the last Condominium Clearance Sync.
     *
     * @param array<string, mixed>|null $registry
     */
    public function isRegistryModifiedSinceLastSync(
        Reservation $reservation,
        ?array $registry,
        ?CondominiumClearance $clearance
    ): bool {
        if ($clearance === null || (!$clearance->isSynced() && ($clearance->clearanceNumber ?? '') === '')) {
            return false;
        }

        if ($registry === null) {
            return false;
        }

        $occupants = $this->extractRegistryOccupants($registry);
        $reqPayload = $clearance->requestPayload;

        if (is_array($reqPayload)) {
            $step1 = $reqPayload['step1'] ?? null;
            if (is_array($step1)) {
                if ((string) ($step1['apt'] ?? '') !== $reservation->propertyId) {
                    return true;
                }
                if ((string) ($step1['indate'] ?? '') !== $reservation->checkIn) {
                    return true;
                }
                if ((string) ($step1['outdate'] ?? '') !== $reservation->checkOut) {
                    return true;
                }
                if (trim((string) ($step1['car'] ?? '')) !== trim((string) ($registry['car_plates'] ?? ''))) {
                    return true;
                }
                if ((int) ($step1['cant_hp'] ?? 0) !== count($occupants)) {
                    return true;
                }
            }

            $step2 = $reqPayload['step2'] ?? null;
            if (is_array($step2)) {
                $syncedDocs = is_array($step2['ide'] ?? null) ? array_values($step2['ide']) : [];
                $syncedNames = is_array($step2['name'] ?? null) ? array_values($step2['name']) : [];
                $syncedLastNames = is_array($step2['lname'] ?? null) ? array_values($step2['lname']) : [];

                foreach ($occupants as $i => $occ) {
                    $occDoc = trim((string) ($occ['doc_num'] ?? $occ['doc_number'] ?? ''));
                    $occFn = trim((string) ($occ['first_name'] ?? $occ['name'] ?? ''));
                    $occLn = trim((string) ($occ['last_name'] ?? ''));

                    if (($syncedDocs[$i] ?? null) !== $occDoc) {
                        return true;
                    }
                    if ($occLn !== '' && ($syncedNames[$i] ?? null) !== $occFn) {
                        return true;
                    }
                    if ($occLn !== '' && ($syncedLastNames[$i] ?? null) !== $occLn) {
                        return true;
                    }
                }
            }
        }

        if ($this->pdo !== null) {
            try {
                $stmtAudit = $this->pdo->prepare('
                    SELECT action FROM admin_audit_logs
                    WHERE entity_type = :entity_type AND entity_id = :entity_id
                    ORDER BY id DESC LIMIT 1
                ');
                $stmtAudit->execute([
                    ':entity_type' => 'reservation',
                    ':entity_id' => $reservation->reservationUid,
                ]);
                $latestAction = $stmtAudit->fetchColumn();
                if ($latestAction === 'reservation_updated') {
                    return true;
                }
            } catch (Throwable) {
            }
        }

        if ($clearance->syncedAt !== null && $reservation->updatedAt !== null) {
            $syncedTs = strtotime($clearance->syncedAt);
            if ($syncedTs !== false && $reservation->updatedAt->getTimestamp() > $syncedTs) {
                return true;
            }
        }

        return false;
    }

    /**
     * Validates and re-indexes submitted Guest rows (1..N) against OccupantDetails rules.
     *
     * @param list<array<string, mixed>> $rawOccupants
     * @param array<string, mixed> $body
     * @return list<OccupantDetails>|string List of validated OccupantDetails or error message string
     */
    private function validateAndNormalizeOccupants(array $rawOccupants, array $body): array|string
    {
        $docCodeMap = [
            'CC' => 'Cédula de Ciudadanía',
            'PA' => 'Passport',
            'TI' => 'Tarjeta de Identidad',
            'RC' => 'Registro Civil',
            'CE' => 'Cédula de Extranjería',
            'DE' => 'National ID',
            'DL' => 'Driver License',
        ];

        $validated = [];
        foreach ($rawOccupants as $zeroIdx => $item) {
            $idx = $zeroIdx + 1;
            $slot = isset($item['slot']) ? (int) $item['slot'] : $idx;

            $firstName = trim((string) ($item['first_name'] ?? ''));
            $lastName = trim((string) ($item['last_name'] ?? ''));
            $fullName = trim((string) ($item['name'] ?? ''));

            if ($firstName === '' && $lastName === '' && $fullName !== '') {
                $parts = preg_split('/\s+/', $fullName) ?: [];
                if (count($parts) >= 2) {
                    $lastName = (string) array_pop($parts);
                    $firstName = implode(' ', $parts);
                }
            }

            if ($firstName === '') {
                return sprintf('Guest #%d: First name is required.', $idx);
            }
            if ($lastName === '') {
                return sprintf('Guest #%d: Last name is required.', $idx);
            }

            $combinedName = trim($firstName . ' ' . $lastName);
            if (strlen($combinedName) < 2 || strlen($combinedName) > 100) {
                return sprintf('Guest #%d: Full name must be between 2 and 100 characters.', $idx);
            }

            $rawDocType = trim((string) ($item['doc_type'] ?? ''));
            $upperDoc = strtoupper($rawDocType);
            $docType = $docCodeMap[$upperDoc] ?? $rawDocType;
            if (!in_array($docType, OccupantDetails::VALID_DOC_TYPES, true)) {
                return sprintf('Guest #%d: Invalid document type.', $idx);
            }

            $docNum = trim((string) ($item['doc_num'] ?? ''));
            if (strlen($docNum) < 2 || strlen($docNum) > 50) {
                return sprintf('Guest #%d: Document number must be between 2 and 50 characters.', $idx);
            }

            $rawAge = $item['age'] ?? null;
            if ($rawAge === null || trim((string) $rawAge) === '' || filter_var($rawAge, FILTER_VALIDATE_INT) === false) {
                return sprintf('Guest #%d: Age must be a valid integer between 0 and 120.', $idx);
            }
            $age = (int) $rawAge;
            if ($age < 0 || $age > 120) {
                return sprintf('Guest #%d: Age must be between 0 and 120.', $idx);
            }

            $rawCountry = trim((string) ($item['country'] ?? ''));
            $normalizedCountry = OccupantDetails::normalizeCountry($rawCountry);
            if ($normalizedCountry === null || $normalizedCountry === '') {
                return sprintf('Guest #%d: Country is required.', $idx);
            }

            if ($idx === 1) {
                $rawEmail = $item['email'] ?? null;
                if (($rawEmail === null || trim((string) $rawEmail) === '') && $slot > 1) {
                    $rawEmail = $body['guest_email_1'] ?? null;
                }
                $trimmedEmail = trim((string) ($rawEmail ?? ''));
                if ($trimmedEmail === '' || !filter_var($trimmedEmail, FILTER_VALIDATE_EMAIL) || strlen($trimmedEmail) > 100) {
                    return 'Guest #1: A valid email address is required for the Primary Guest.';
                }

                $rawPhone = $item['phone'] ?? null;
                if (($rawPhone === null || trim((string) $rawPhone) === '') && $slot > 1) {
                    $rawPhone = $body['guest_phone_1'] ?? null;
                }
                $trimmedPhone = trim((string) ($rawPhone ?? ''));
                if ($trimmedPhone === '' || strtoupper($trimmedPhone) === 'N/A' || strlen($trimmedPhone) < 6 || strlen($trimmedPhone) > 30) {
                    return 'Guest #1: Phone number must be between 6 and 30 characters.';
                }
            } else {
                $trimmedEmail = null;
                $rawPhone = $item['phone'] ?? null;
                $trimmedPhone = ($rawPhone !== null && trim((string) $rawPhone) !== '')
                    ? trim((string) $rawPhone)
                    : null;
                if ($trimmedPhone !== null && (strlen($trimmedPhone) < 6 || strlen($trimmedPhone) > 30)) {
                    return sprintf('Guest #%d: Phone number must be between 6 and 30 characters.', $idx);
                }
            }

            try {
                $validated[] = new OccupantDetails(
                    index: $idx,
                    name: $combinedName,
                    age: $age,
                    docType: $docType,
                    docNum: $docNum,
                    email: $trimmedEmail,
                    firstName: $firstName,
                    lastName: $lastName,
                    phone: $trimmedPhone,
                    country: $normalizedCountry
                );
            } catch (InvalidArgumentException $e) {
                return sprintf('Guest #%d: %s', $idx, $e->getMessage());
            }
        }

        return $validated;
    }

    /**
     * @param list<array<string, mixed>> $occupantsPayloadArray
     */
    private function upsertGuestRegistryRecord(
        string $uid,
        string $propertyId,
        string $checkIn,
        string $checkOut,
        array $occupantsPayloadArray,
        ?string $carPlates,
        ?string $carModel,
        string $ipAddress,
        DateTimeImmutable $now
    ): void {
        $guestsPayloadJson = (string) json_encode(
            $occupantsPayloadArray,
            JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
        );

        if ($this->pdo !== null) {
            $stmtExists = $this->pdo->prepare('SELECT id FROM guest_registries WHERE reservation_uid = :uid ORDER BY id DESC LIMIT 1');
            $stmtExists->execute([':uid' => $uid]);
            $existingRegId = $stmtExists->fetchColumn();

            if ($existingRegId !== false) {
                $stmtUpdateReg = $this->pdo->prepare('
                    UPDATE guest_registries
                    SET property_id = :property_id,
                        check_in = :check_in,
                        check_out = :check_out,
                        guest_count = :guest_count,
                        guests_payload = :guests_payload,
                        car_plates = :car_plates,
                        car_model = :car_model,
                        ip_address = :ip_address
                    WHERE id = :id
                ');
                $stmtUpdateReg->execute([
                    ':property_id' => $propertyId,
                    ':check_in' => $checkIn,
                    ':check_out' => $checkOut,
                    ':guest_count' => count($occupantsPayloadArray),
                    ':guests_payload' => $guestsPayloadJson,
                    ':car_plates' => $carPlates,
                    ':car_model' => $carModel,
                    ':ip_address' => $ipAddress,
                    ':id' => (int) $existingRegId,
                ]);
            } else {
                $stmtInsertReg = $this->pdo->prepare('
                    INSERT INTO guest_registries (
                        reservation_uid, property_id, check_in, check_out,
                        guest_count, guests_payload, car_plates, car_model,
                        ip_address, created_at
                    ) VALUES (
                        :reservation_uid, :property_id, :check_in, :check_out,
                        :guest_count, :guests_payload, :car_plates, :car_model,
                        :ip_address, CURRENT_TIMESTAMP
                    )
                ');
                $stmtInsertReg->execute([
                    ':reservation_uid' => $uid,
                    ':property_id' => $propertyId,
                    ':check_in' => $checkIn,
                    ':check_out' => $checkOut,
                    ':guest_count' => count($occupantsPayloadArray),
                    ':guests_payload' => $guestsPayloadJson,
                    ':car_plates' => $carPlates,
                    ':car_model' => $carModel,
                    ':ip_address' => $ipAddress,
                ]);
            }
        }

        if ($this->search instanceof InMemoryReservationSearchAdapter) {
            $this->search->addGuestRegistry($uid, [
                'reservation_uid' => $uid,
                'property_id' => $propertyId,
                'check_in' => $checkIn,
                'check_out' => $checkOut,
                'guest_count' => count($occupantsPayloadArray),
                'guests_payload' => $occupantsPayloadArray,
                'car_plates' => $carPlates,
                'car_model' => $carModel,
                'ip_address' => $ipAddress,
                'created_at' => $now->format('Y-m-d H:i:s'),
            ]);
        }
    }

    private function deleteGuestRegistryRecord(string $uid): void
    {
        if ($this->pdo !== null) {
            $stmtDeleteReg = $this->pdo->prepare('DELETE FROM guest_registries WHERE reservation_uid = :uid');
            $stmtDeleteReg->execute([':uid' => $uid]);
        }
        if ($this->search instanceof InMemoryReservationSearchAdapter) {
            $this->search->removeGuestRegistry($uid);
        }
    }

    /**
     * @param array<string, mixed>|null $registry
     * @return array<string, mixed>|null
     */
    private function formatRegistryForAudit(
        ?array $registry,
        string $fallbackPropertyId,
        string $fallbackCheckIn,
        string $fallbackCheckOut
    ): ?array {
        if ($registry === null) {
            return null;
        }
        $occupants = $this->extractRegistryOccupants($registry);
        return [
            'property_id' => $registry['property_id'] ?? $fallbackPropertyId,
            'check_in' => $registry['check_in'] ?? $fallbackCheckIn,
            'check_out' => $registry['check_out'] ?? $fallbackCheckOut,
            'guest_count' => (int) ($registry['guest_count'] ?? count($occupants)),
            'car_plates' => $registry['car_plates'] ?? null,
            'car_model' => $registry['car_model'] ?? null,
            'guests_payload' => $occupants,
            'occupants' => $occupants,
        ];
    }

    /**
     * @param list<string>|null $conflictReasons
     * @return array{
     *     success: false,
     *     errorMessage: string,
     *     conflictReasons: list<string>|null,
     *     registeredGuestCount: int
     * }
     */
    private function failure(int $registeredGuestCount, string $errorMessage, ?array $conflictReasons = null): array
    {
        return [
            'success' => false,
            'errorMessage' => $errorMessage,
            'conflictReasons' => $conflictReasons,
            'registeredGuestCount' => $registeredGuestCount,
        ];
    }

    private function parseCheckbox(mixed $val): bool
    {
        return $val !== null && in_array((string) $val, ['1', 'true', 'on'], true);
    }
}
