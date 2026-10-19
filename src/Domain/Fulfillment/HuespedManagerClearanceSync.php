<?php

declare(strict_types=1);

namespace OceanViewFlats\Domain\Fulfillment;

use InvalidArgumentException;
use LogicException;
use OceanViewFlats\Admin\Audit\AuditLogger;
use OceanViewFlats\Domain\Reservation\PdoReservationRepository;
use OceanViewFlats\Domain\Reservation\Reservation;
use OceanViewFlats\Domain\Reservation\ReservationNotFoundException;
use OceanViewFlats\Domain\Reservation\ReservationRepositoryInterface;
use OceanViewFlats\Domain\Reservation\Search\PdoReservationSearchAdapter;
use OceanViewFlats\Domain\Reservation\Search\ReservationSearchInterface;
use OceanViewFlats\Domain\Security\PayloadEncryptor;
use OceanViewFlats\Domain\Security\PiiMasker;
use PDO;
use Throwable;

/**
 * Outbound synchronization adapter for Condominium Administration Portal (Huésped Manager).
 *
 * Implements the 2-step synchronization protocol documented in ADR 0008:
 * Step 1: POST to reg_guest_owner_pre.php?unit={propertyId}&check={checkToken} to register stay & obtain last_id.
 * Step 2: POST to reg_hpds_pre.php with occupant details, primary guest phone fallback, and session cookies.
 */
final class HuespedManagerClearanceSync implements CondominiumClearanceSyncInterface
{
    public const DEFAULT_BASE_URL = 'https://salguerosunset.huespedmanager.com.co';
    public const DEFAULT_1707_CHECK = 'ep92449222';
    public const DEFAULT_1606_CHECK = 'ep24281580';

    private readonly string $baseUrl;
    private readonly string $token1707;
    private readonly string $token1606;

    public function __construct(
        private readonly CondominiumClearanceRepositoryInterface $repository,
        private readonly ?ReservationRepositoryInterface $reservationRepository = null,
        private readonly ?ReservationSearchInterface $searchAdapter = null,
        private readonly HttpTransportInterface $transport = new CurlHttpTransport(),
        private readonly ?AuditLogger $auditLogger = null,
        ?string $baseUrl = null,
        ?string $token1707 = null,
        ?string $token1606 = null,
    ) {
        $this->baseUrl = $baseUrl
            ?? $_ENV['HUESPED_MANAGER_BASE_URL']
            ?? $_SERVER['HUESPED_MANAGER_BASE_URL']
            ?? (getenv('HUESPED_MANAGER_BASE_URL') ?: null)
            ?: self::DEFAULT_BASE_URL;

        $this->token1707 = $token1707
            ?? $_ENV['HUESPED_MANAGER_1707_CHECK']
            ?? $_SERVER['HUESPED_MANAGER_1707_CHECK']
            ?? (getenv('HUESPED_MANAGER_1707_CHECK') ?: null)
            ?: self::DEFAULT_1707_CHECK;

        $this->token1606 = $token1606
            ?? $_ENV['HUESPED_MANAGER_1606_CHECK']
            ?? $_SERVER['HUESPED_MANAGER_1606_CHECK']
            ?? (getenv('HUESPED_MANAGER_1606_CHECK') ?: null)
            ?: self::DEFAULT_1606_CHECK;
    }

    public static function createDefault(PDO $pdo, ?HttpTransportInterface $transport = null): self
    {
        return new self(
            repository: new PdoCondominiumClearanceRepository($pdo),
            reservationRepository: new PdoReservationRepository($pdo),
            searchAdapter: new PdoReservationSearchAdapter($pdo),
            transport: $transport ?? new CurlHttpTransport(),
            auditLogger: new AuditLogger($pdo)
        );
    }

    /**
     * Centralizes formatting of reservation notes and vehicle details for the condominium portal.
     */
    public static function formatClearanceNotes(?string $notes, ?string $carModel): ?string
    {
        $cleanNotes = ($notes !== null && trim($notes) !== '') ? trim($notes) : null;
        $cleanModel = ($carModel !== null && trim((string) $carModel) !== '') ? trim((string) $carModel) : null;

        if ($cleanModel === null) {
            return $cleanNotes;
        }

        $vehicleNote = 'Vehicle: ' . $cleanModel;
        return $cleanNotes !== null ? $cleanNotes . ' | ' . $vehicleNote : $vehicleNote;
    }

    /**
     * Deep synchronization entry point loading reservation and guest registry from persistence.
     *
     * @throws LogicException If repository or search adapter dependencies are not configured
     * @throws ReservationNotFoundException If reservation does not exist
     * @throws GuestRegistryRequiredException If guest registry has not been submitted
     */
    public function syncForReservation(string $reservationUid, ?AdminContext $admin = null): CondominiumClearance
    {
        if ($this->reservationRepository === null) {
            throw new LogicException('Reservation repository is not configured on HuespedManagerClearanceSync.');
        }

        $reservation = $this->reservationRepository->findByUid($reservationUid);
        if ($reservation === null) {
            throw new ReservationNotFoundException("Reservation not found: {$reservationUid}");
        }

        if ($this->searchAdapter === null) {
            throw new LogicException('Reservation search adapter is not configured on HuespedManagerClearanceSync.');
        }

        $registry = $this->searchAdapter->findGuestRegistry($reservationUid);
        if ($registry === null) {
            throw new GuestRegistryRequiredException("Guest registry must be submitted before condominium clearance can be synced for reservation: {$reservationUid}");
        }

        $guests = PayloadEncryptor::decrypt($registry['guests_payload'] ?? []);

        $carPlates = isset($registry['car_plates']) && trim((string) $registry['car_plates']) !== ''
            ? trim((string) $registry['car_plates'])
            : null;
        $carModel = isset($registry['car_model']) && trim((string) $registry['car_model']) !== ''
            ? trim((string) $registry['car_model'])
            : null;

        $notes = self::formatClearanceNotes($reservation->notes, $carModel);

        return $this->performSync(
            reservationUid: $reservation->reservationUid,
            propertyId: $reservation->propertyId,
            checkIn: $reservation->checkIn,
            checkOut: $reservation->checkOut,
            guests: $guests,
            carPlates: $carPlates,
            notes: $notes,
            admin: $admin
        );
    }

    /**
     * Deep synchronization entry point utilizing in-memory reservation and guest registry submission models.
     *
     * @throws InvalidArgumentException If guest registry submission does not contain any occupants
     */
    public function syncSubmission(
        Reservation $reservation,
        GuestRegistrySubmission $submission,
        ?AdminContext $admin = null
    ): CondominiumClearance {
        if (empty($submission->occupants)) {
            throw new InvalidArgumentException('Guest registry submission must contain at least one occupant for condominium clearance sync.');
        }

        $notes = self::formatClearanceNotes($reservation->notes, $submission->carModel);

        return $this->performSync(
            reservationUid: $reservation->reservationUid,
            propertyId: $reservation->propertyId,
            checkIn: $reservation->checkIn,
            checkOut: $reservation->checkOut,
            guests: $submission->occupants,
            carPlates: $submission->carPlates,
            notes: $notes,
            admin: $admin
        );
    }

    /**
     * Encapsulates reception portal synchronization handshake, persistence, and audit logging.
     *
     * @param array<int, OccupantDetails|array<string, mixed>> $guests
     */
    private function performSync(
        string $reservationUid,
        string $propertyId,
        string $checkIn,
        string $checkOut,
        array $guests,
        ?string $carPlates = null,
        ?string $notes = null,
        ?AdminContext $admin = null
    ): CondominiumClearance {
        $existing = $this->repository->findByReservationUid($reservationUid);
        $payloadBefore = $existing?->toArray();

        $clearance = $existing !== null
            ? $existing->recordAttempt()
            : CondominiumClearance::createPending($reservationUid, $propertyId);

        // 1. Validate property check token
        $checkToken = $this->getCheckTokenForProperty($propertyId);
        if ($checkToken === null || trim($checkToken) === '') {
            $clearance = $clearance->markFailed(sprintf('No check token configured for property ID: %s', $propertyId));
            return $this->finalizeClearance($clearance, $reservationUid, $payloadBefore, $admin);
        }

        // 2. Parse and normalize occupants
        $occupants = $this->normalizeOccupants($guests);
        if ($occupants === []) {
            $clearance = $clearance->markFailed('Occupant list is empty; at least 1 guest required for condominium clearance.');
            return $this->finalizeClearance($clearance, $reservationUid, $payloadBefore, $admin);
        }

        $primaryPhone = trim((string) ($occupants[0]->phone ?? ''));

        $productionBase = $this->getProductionBaseUrl();
        $step1Url = sprintf('%s/reg_guest_owner_pre.php?unit=%s&check=%s', $productionBase, urlencode($propertyId), urlencode($checkToken));

        $step1Payload = [
            // Authoritative portal form input names (DOM verified)
            'apt' => $propertyId,
            'indate' => $checkIn,
            'horain' => '15:00',
            'outdate' => $checkOut,
            'horaout' => '11:00',
            'fpay' => 'TRANSFERENCIA',
            'cant_hp' => (string) count($occupants),
            'price' => '0',
            'car' => $carPlates ?? '',
            'observacion' => $notes ?? '',

            // Defensive backward-compatibility aliases
            'send_apt' => $propertyId,
            'send_indate' => $checkIn,
            'send_horain' => '15:00',
            'send_outdate' => $checkOut,
            'send_horaout' => '11:00',
            'send_fpay' => 'TRANSFERENCIA',
            'send_cant_hp' => (string) count($occupants),
            'send_price' => '0',
            'send_car' => $carPlates ?? '',
            'send_observacion' => $notes ?? '',
            'send_btn' => '1',
            'btn_send' => '1',
        ];

        // 3. Step 1: Register reservation stay and acquire consecutivo (last_id)
        try {
            $response1 = $this->transport->post($step1Url, $step1Payload);
        } catch (Throwable $e) {
            $clearance = $clearance->withPayload(['step1' => $step1Payload])
                ->markFailed('Step 1 transport exception: ' . $e->getMessage());
            return $this->finalizeClearance($clearance, $reservationUid, $payloadBefore, $admin);
        }

        if ($response1['error'] !== null) {
            $clearance = $clearance->withPayload(['step1' => $step1Payload])
                ->markFailed('Step 1 HTTP transport error: ' . $response1['error']);
            return $this->finalizeClearance($clearance, $reservationUid, $payloadBefore, $admin);
        }

        if ($response1['statusCode'] < 200 || $response1['statusCode'] >= 300) {
            $clearance = $clearance->withPayload(['step1' => $step1Payload])
                ->markFailed(sprintf(
                    'Step 1 returned unexpected HTTP status %d: %s',
                    $response1['statusCode'],
                    substr($response1['body'], 0, 250)
                ));
            return $this->finalizeClearance($clearance, $reservationUid, $payloadBefore, $admin);
        }

        $json1 = json_decode($response1['body'], true);
        if (!is_array($json1) || empty($json1['last_id']) || !is_numeric($json1['last_id']) || (int) $json1['last_id'] <= 0) {
            $clearance = $clearance->withPayload(['step1' => $step1Payload])
                ->markFailed("Step 1 response missing valid 'last_id': " . substr($response1['body'], 0, 250));
            return $this->finalizeClearance($clearance, $reservationUid, $payloadBefore, $admin);
        }

        $lastId = (int) $json1['last_id'];
        $cookies = $response1['cookies'];

        // 4. Step 2: Prepare occupant form arrays and submit with session cookies
        $typeid = [];
        $ide = [];
        $name = [];
        $sname = [];
        $lname = [];
        $mname = [];
        $country = [];
        $phone = [];
        $nexo = [];
        $covid = [];

        foreach ($occupants as $i => $occ) {
            $typeid[] = $occ->getPortalDocType();
            $ide[] = $occ->docNum;
            $name[] = $occ->firstName ?? $occ->name;
            $sname[] = $occ->middleName ?? '';
            $lname[] = $occ->lastName ?? '';
            $mname[] = $occ->secondLastName ?? '';
            $country[] = $occ->normalizeCountry($occ->country ?? 'COLOMBIA') ?? 'COLOMBIA';

            // Companion inherits primary guest phone if empty
            $occPhone = trim((string) ($occ->phone ?? ''));
            $phone[] = $occPhone !== '' ? $occPhone : $primaryPhone;

            // Primary occupant is TITULAR, companions are ACOMPAÑANTE
            $nexo[] = $i === 0 ? 'TITULAR' : 'ACOMPAÑANTE';
            $covid[] = '0';
        }

        $step2Url = sprintf('%s/reg_hpds_pre.php', $productionBase);
        $step2Payload = [
            'consecutivo' => (string) $lastId,
            'btn_checkin' => '0',
            'send_btn' => '1',
            'typeid' => $typeid,
            'ide' => $ide,
            'name' => $name,
            'sname' => $sname,
            'lname' => $lname,
            'mname' => $mname,
            'country' => $country,
            'phone' => $phone,
            'nexo' => $nexo,
            'covid3' => $covid,
            'covid4' => $covid,
            'covid5' => $covid,
            'covid6' => $covid,
            'covid7' => $covid,
            'covid8' => $covid,
            'covid9' => $covid,
            'covid10' => $covid,
        ];

        $maskedStep2Payload = $step2Payload;
        $maskedStep2Payload['ide'] = array_map(
            static fn (string $docNum): string => PiiMasker::maskDocNumber($docNum),
            $ide
        );
        $maskedStep2Payload['phone'] = array_map(
            static fn (string $p): string => (string) PiiMasker::maskPhone($p),
            $phone
        );

        $fullPayload = [
            'step1' => $step1Payload,
            'step2' => $maskedStep2Payload,
        ];

        try {
            $response2 = $this->transport->post(
                $step2Url,
                $step2Payload,
                [],
                [
                    'cookies' => $cookies,
                    'followRedirects' => false,
                ]
            );
        } catch (Throwable $e) {
            $clearance = $clearance->withPayload($fullPayload)
                ->markFailed('Step 2 transport exception: ' . $e->getMessage());
            return $this->finalizeClearance($clearance, $reservationUid, $payloadBefore, $admin);
        }

        // 5. Verify Step 2 outcome
        $isSuccess = false;
        $failureReason = null;

        if ($response2['error'] !== null) {
            $failureReason = 'Step 2 HTTP transport error: ' . $response2['error'];
        } else {
            $status2 = $response2['statusCode'];
            $location = $response2['headers']['location'] ?? '';

            if ($status2 === 302 || $status2 === 303 || $status2 === 301) {
                $locationLower = strtolower($location);
                if (str_contains($locationLower, 'err') || str_contains($locationLower, 'fallo')) {
                    $failureReason = sprintf('Step 2 redirected to error page: %s', $location);
                } else {
                    $isSuccess = true;
                }
            } elseif ($status2 === 200) {
                $bodyLower = strtolower($response2['body']);
                $effLower = strtolower($response2['effectiveUrl'] ?? '');
                if (
                    str_contains($effLower, 'succ')
                    || str_contains($bodyLower, 'succ')
                    || str_contains($bodyLower, 'registrad')
                    || str_contains($bodyLower, 'exitos')
                    || str_contains($bodyLower, 'consecutivo')
                ) {
                    $isSuccess = true;
                } else {
                    $trimmedBody = trim($response2['body']);
                    if ($trimmedBody === '') {
                        $failureReason = 'Step 2 returned HTTP 200 with empty body (missing success redirect or confirmation text)';
                    } else {
                        $failureReason = sprintf('Step 2 returned HTTP 200 without recognized success indicator: %s', substr($trimmedBody, 0, 250));
                    }
                }
            } else {
                $trimmedBody = trim($response2['body']);
                $failureReason = sprintf('Step 2 returned unexpected HTTP status %d: %s', $status2, substr($trimmedBody, 0, 250));
            }
        }

        if ($isSuccess) {
            $clearance = $clearance->withPayload($fullPayload)
                ->markSynced((string) $lastId);
        } else {
            $clearance = $clearance->withPayload($fullPayload)
                ->markFailed($failureReason ?? 'Step 2 failed during occupant submission.');
        }

        return $this->finalizeClearance($clearance, $reservationUid, $payloadBefore, $admin);
    }

    /**
     * @param array<string, mixed>|null $payloadBefore
     */
    private function finalizeClearance(
        CondominiumClearance $clearance,
        string $reservationUid,
        ?array $payloadBefore,
        ?AdminContext $admin
    ): CondominiumClearance {
        $this->repository->save($clearance);

        if ($admin !== null && $this->auditLogger !== null) {
            $action = $payloadBefore !== null ? 'condominium_clearance_retry' : 'condominium_clearance_sync';
            $this->auditLogger->record(
                action: $action,
                entityType: 'reservation',
                entityId: $reservationUid,
                before: $payloadBefore,
                after: $clearance->toArray(),
                adminUserId: $admin->adminUserId,
                ipAddress: (string) ($admin->ipAddress ?? ''),
                userAgent: $admin->userAgent
            );
        }

        return $clearance;
    }

    private function getProductionBaseUrl(): string
    {
        $clean = rtrim($this->baseUrl, '/');
        if (str_ends_with($clean, '/propietarios/production')) {
            return $clean;
        }
        return $clean . '/propietarios/production';
    }

    private function getCheckTokenForProperty(string $propertyId): ?string
    {
        $clean = trim($propertyId);
        if ($clean === '1707') {
            return $this->token1707;
        }
        if ($clean === '1606') {
            return $this->token1606;
        }
        return null;
    }

    /**
     * @param array<int, OccupantDetails|array<string, mixed>> $guests
     * @return list<OccupantDetails>
     */
    private function normalizeOccupants(array $guests): array
    {
        $codeMap = [
            'CC' => 'Cédula de Ciudadanía',
            'PA' => 'Passport',
            'TI' => 'Tarjeta de Identidad',
            'RC' => 'Registro Civil',
            'CE' => 'Cédula de Extranjería',
            'DE' => 'National ID',
            'DL' => 'Driver License',
        ];

        $normalized = [];
        $index = 1;
        foreach ($guests as $guest) {
            if ($guest instanceof OccupantDetails) {
                $normalized[] = $guest;
                $index++;
            } elseif (is_array($guest)) {
                $guestData = $guest;
                if (isset($guestData['doc_number']) && !isset($guestData['doc_num'])) {
                    $guestData['doc_num'] = (string) $guestData['doc_number'];
                }
                $rawDocType = (string) ($guestData['doc_type'] ?? $guestData['docType'] ?? '');
                $upperDoc = strtoupper(trim($rawDocType));
                if (isset($codeMap[$upperDoc])) {
                    $guestData['doc_type'] = $codeMap[$upperDoc];
                }
                $normalized[] = OccupantDetails::fromArray($guestData, $index);
                $index++;
            }
        }
        return $normalized;
    }
}
