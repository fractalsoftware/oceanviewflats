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
        $rawNotes = $notes !== null ? trim($notes) : '';
        $rawModel = $carModel !== null ? trim((string) $carModel) : '';
        $combined = $rawModel !== ''
            ? ($rawNotes !== '' ? $rawNotes . ' - Vehicle - ' . $rawModel : 'Vehicle - ' . $rawModel)
            : $rawNotes;
        $sanitized = self::sanitizePortalText($combined);

        return $sanitized !== '' ? $sanitized : null;
    }

    /**
     * Sanitizes free-text fields (observacion) for Condominium Administration Portal RX.texto rule (max 250 chars).
     */
    public static function sanitizePortalText(?string $text): string
    {
        $normalized = str_replace(['|', ':', ';', '/', '\\'], ' - ', (string) $text);
        return self::sanitizePortalLatin($normalized, '0-9 .,\\-', 1, 250);
    }

    /**
     * Sanitizes occupant name segments for Condominium Administration Portal RX.nombre rule (2–60 chars).
     */
    public static function sanitizePortalName(?string $name): string
    {
        return self::sanitizePortalLatin((string) $name, '', 2, 60);
    }

    /**
     * Sanitizes vehicle license plates for Condominium Administration Portal RX.placa rule (/^[A-Za-z0-9]{3,8}$/).
     */
    public static function sanitizePortalCarPlate(?string $carPlates): string
    {
        $alnum = strtoupper((string) preg_replace('/[^A-Za-z0-9]/', '', (string) $carPlates));
        return strlen($alnum) >= 3 ? substr($alnum, 0, 8) : '';
    }

    /**
     * Sanitizes phone numbers for Condominium Administration Portal RX.telefono rule (/^[0-9]{7,15}$/).
     */
    public static function sanitizePortalPhone(?string $phone): string
    {
        $digits = (string) preg_replace('/\D+/', '', (string) $phone);
        if (str_starts_with($digits, '00') && strlen($digits) > 9) {
            $digits = substr($digits, 2);
        }
        $trimmed = strlen($digits) > 15 ? substr($digits, -15) : $digits;
        return strlen($trimmed) >= 7 ? $trimmed : '';
    }

    /**
     * Resolves portal document code and sanitized document number honoring Condominium Administration Portal DOCS bounds.
     *
     * @return array{0: string, 1: string}
     */
    private static function resolvePortalDocument(OccupantDetails $occ): array
    {
        $type = $occ->getPortalDocType();
        $alnum = (string) preg_replace('/[^A-Za-z0-9]/', '', $occ->docNum);
        $bounds = ['CC' => [true, 3, 10], 'TI' => [true, 6, 11], 'RC' => [true, 6, 11], 'CE' => [true, 3, 15], 'PA' => [false, 4, 20]];
        if (!isset($bounds[$type])) {
            return [$type, $alnum];
        }
        [$numericOnly, $min, $max] = $bounds[$type];
        $candidate = substr($numericOnly ? (string) preg_replace('/\D+/', '', $occ->docNum) : $alnum, 0, $max);
        return strlen($candidate) >= $min ? [$type, $candidate] : ['DE', $alnum];
    }

    private static function sanitizePortalLatin(string $value, string $extraChars, int $minLen, int $maxLen): string
    {
        $pattern = '/[^A-Za-zÁÉÍÓÚÜÑáéíóúüñÀÈÌÒÙàèìòùÂÊÎÔÛâêîôûÇç ' . $extraChars . ']+/u';
        $cleaned = mb_substr(trim((string) preg_replace('/\s+/u', ' ', (string) preg_replace($pattern, ' ', $value))), 0, $maxLen, 'UTF-8');
        return mb_strlen($cleaned, 'UTF-8') >= $minLen ? $cleaned : '';
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

        $primaryPhone = self::sanitizePortalPhone($occupants[0]->phone);
        $sanitizedCarPlates = self::sanitizePortalCarPlate($carPlates);
        $sanitizedNotes = self::sanitizePortalText($notes);

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
            'car' => $sanitizedCarPlates,
            'observacion' => $sanitizedNotes,

            // Defensive backward-compatibility aliases
            'send_apt' => $propertyId,
            'send_indate' => $checkIn,
            'send_horain' => '15:00',
            'send_outdate' => $checkOut,
            'send_horaout' => '11:00',
            'send_fpay' => 'TRANSFERENCIA',
            'send_cant_hp' => (string) count($occupants),
            'send_price' => '0',
            'send_car' => $sanitizedCarPlates,
            'send_observacion' => $sanitizedNotes,
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
            [$docType, $docNum] = self::resolvePortalDocument($occ);
            $firstName = self::sanitizePortalName($occ->firstName ?? $occ->name);
            $lastName = self::sanitizePortalName($occ->lastName);
            $typeid[] = $docType;
            $ide[] = $docNum;
            $name[] = $firstName;
            $sname[] = self::sanitizePortalName($occ->middleName);
            $lname[] = $lastName !== '' ? $lastName : $firstName;
            $mname[] = self::sanitizePortalName($occ->secondLastName);
            $country[] = $occ->normalizeCountry($occ->country ?? 'COLOMBIA') ?? 'COLOMBIA';

            // Companion inherits primary guest phone if empty or invalid (<7 digits)
            $occPhone = self::sanitizePortalPhone($occ->phone);
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
                $isValidationErrorPage = str_contains($bodyLower, 'datos por corregir') || str_contains($bodyLower, 'revise la informaci');
                if (
                    !$isValidationErrorPage
                    && (
                        str_contains($effLower, 'succ')
                        || str_contains($bodyLower, 'succ')
                        || str_contains($bodyLower, 'registrad')
                        || str_contains($bodyLower, 'exitos')
                        || str_contains($bodyLower, 'consecutivo')
                    )
                ) {
                    $isSuccess = true;
                } else {
                    $trimmedBody = trim($response2['body']);
                    if ($trimmedBody === '') {
                        $failureReason = 'Step 2 returned HTTP 200 with empty body (missing success redirect or confirmation text)';
                    } else {
                        $failureReason = sprintf(
                            'Step 2 returned HTTP 200 without recognized success indicator: %s',
                            self::extractPortalErrorSnippet($trimmedBody)
                        );
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

    /**
     * Extracts a concise, human-readable diagnostic message from Huésped Manager HTML error responses
     * (e.g., <title>Datos por corregir</title> ... <h1>Revise la información</h1><p>Huésped #1: ...</p>)
     * instead of truncating inside inline <style> blocks.
     */
    public static function extractPortalErrorSnippet(string $rawBody): string
    {
        $withoutCssJs = (string) preg_replace('/<(style|script)\b[^>]*>.*?<\/\1>/is', ' ', $rawBody);
        $spacedTags = str_replace(['</title>', '</h1>', '</p>', '</div>'], ' - ', $withoutCssJs);
        $plain = trim((string) preg_replace('/\s*(?:-\s*)+/u', ' - ', (string) preg_replace('/\s+/u', ' ', html_entity_decode(strip_tags($spacedTags), ENT_QUOTES | ENT_HTML5, 'UTF-8'))), " \t\n\r\0\x0B-");
        return $plain !== '' ? mb_substr($plain, 0, 250, 'UTF-8') : substr(trim($rawBody), 0, 250);
    }
}
