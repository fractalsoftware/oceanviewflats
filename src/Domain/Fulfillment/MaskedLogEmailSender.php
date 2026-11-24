<?php

declare(strict_types=1);

namespace OceanViewFlats\Domain\Fulfillment;

use OceanViewFlats\Domain\Security\PiiMasker;

/**
 * Local development/testing email sender stub that masks recipient PII before logging (ADR 0012).
 */
final class MaskedLogEmailSender implements EmailSenderInterface
{
    /**
     * @param array<string, string> $headers
     */
    public function send(string $to, string $subject, string $htmlBody, array $headers = []): bool
    {
        $maskedRecipient = PiiMasker::maskEmail($to) ?? '****';
        error_log("[Local Mock Email] To: {$maskedRecipient}, Subject: {$subject}");
        return true;
    }
}
