<?php

declare(strict_types=1);

namespace OceanViewFlats\Domain\Fulfillment;

use OceanViewFlats\Domain\Security\PiiMasker as SecurityPiiMasker;

/**
 * Convenience delegate for PiiMasker within the Fulfillment domain namespace.
 */
final class PiiMasker
{
    public static function maskDocNumber(string $docNum): string
    {
        return SecurityPiiMasker::maskDocNumber($docNum);
    }

    public static function maskPhone(?string $phone): ?string
    {
        return SecurityPiiMasker::maskPhone($phone);
    }

    /**
     * @param array<string, mixed> $occupant
     * @return array<string, mixed>
     */
    public static function maskOccupantArray(array $occupant): array
    {
        return SecurityPiiMasker::maskOccupantArray($occupant);
    }

    /**
     * @param array<string, mixed> $submissionData
     * @return array<string, mixed>
     */
    public static function maskSubmissionArray(array $submissionData): array
    {
        return SecurityPiiMasker::maskSubmissionArray($submissionData);
    }
}
