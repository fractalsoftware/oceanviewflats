<?php

declare(strict_types=1);

namespace OceanViewFlats\Domain\Security;

/**
 * Deterministic PII masking helper for secondary persistence and notification sinks (ADR 0012).
 *
 * Masks government identification document numbers and contact phone numbers so
 * audit logs, condominium clearance request snapshots, and host notification emails
 * never store or transmit full unencrypted government ID numbers.
 */
final class PiiMasker
{
    /**
     * Masks a government document number, preserving only up to the last 4 characters
     * prefixed with '****' (or returning '****' when <= 4 characters).
     */
    public static function maskDocNumber(string $docNum): string
    {
        $trimmed = trim($docNum);
        if ($trimmed === '' || strlen($trimmed) <= 4) {
            return '****';
        }

        return '****' . substr($trimmed, -4);
    }

    /**
     * Masks a contact phone number, preserving only the last 4 digits prefixed with '****'.
     */
    public static function maskPhone(?string $phone): ?string
    {
        if ($phone === null) {
            return null;
        }

        $trimmed = trim($phone);
        if ($trimmed === '') {
            return '';
        }

        if (strtoupper($trimmed) === 'N/A') {
            return $trimmed;
        }

        $digits = preg_replace('/\D/', '', $trimmed) ?? '';
        if (strlen($digits) > 4) {
            return '****' . substr($digits, -4);
        }

        if (strlen($trimmed) <= 4) {
            return '****';
        }

        return '****' . substr($trimmed, -4);
    }

    /**
     * Masks sensitive PII fields (doc_num, doc_number, docNum, phone) inside an occupant array.
     *
     * @param array<string, mixed> $occupant
     * @return array<string, mixed>
     */
    public static function maskOccupantArray(array $occupant): array
    {
        $masked = $occupant;

        if (isset($masked['doc_num'])) {
            $masked['doc_num'] = self::maskDocNumber((string) $masked['doc_num']);
        }
        if (isset($masked['doc_number'])) {
            $masked['doc_number'] = self::maskDocNumber((string) $masked['doc_number']);
        }
        if (isset($masked['docNum'])) {
            $masked['docNum'] = self::maskDocNumber((string) $masked['docNum']);
        }
        if (array_key_exists('phone', $masked) && $masked['phone'] !== null && $masked['phone'] !== '') {
            $masked['phone'] = self::maskPhone((string) $masked['phone']);
        }

        return $masked;
    }

    /**
     * Masks all occupant document numbers and phone numbers in a serialized GuestRegistrySubmission array.
     *
     * @param array<string, mixed> $submissionData
     * @return array<string, mixed>
     */
    public static function maskSubmissionArray(array $submissionData): array
    {
        $masked = $submissionData;

        if (isset($masked['occupants']) && is_array($masked['occupants'])) {
            $maskedOccupants = [];
            foreach ($masked['occupants'] as $occ) {
                $maskedOccupants[] = is_array($occ) ? self::maskOccupantArray($occ) : $occ;
            }
            $masked['occupants'] = $maskedOccupants;
        }

        if (isset($masked['guests']) && is_array($masked['guests'])) {
            $maskedGuests = [];
            foreach ($masked['guests'] as $guest) {
                $maskedGuests[] = is_array($guest) ? self::maskOccupantArray($guest) : $guest;
            }
            $masked['guests'] = $maskedGuests;
        }

        return $masked;
    }

    /**
     * Evaluates whether a raw form or JSON consent field represents an affirmative boolean grant.
     */
    public static function isTruthyConsent(mixed $value): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        if (is_int($value)) {
            return $value === 1;
        }

        if (is_string($value) || is_numeric($value)) {
            return in_array(strtolower(trim((string) $value)), ['1', 'true', 'on', 'yes'], true);
        }

        return false;
    }
}
