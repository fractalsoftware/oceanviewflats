<?php

declare(strict_types=1);

namespace OceanViewFlats\Domain\Fulfillment;

final class PhpMailSender implements EmailSenderInterface
{
    public function __construct(
        private readonly string $fromAddress = 'OceanViewFlats <no-reply@oceanviewflats.com>',
        private readonly string $replyToAddress = 'rentals@oceanviewflats.com'
    ) {}

    /**
     * @param array<string, string> $headers
     */
    public function send(string $to, string $subject, string $htmlBody, array $headers = []): bool
    {
        $cleanTo = self::stripHeaderBreak($to);
        $cleanSubject = self::stripHeaderBreak($subject);

        $defaultHeaders = [
            'MIME-Version' => '1.0',
            'Content-Type' => 'text/html; charset=UTF-8',
            'From' => $this->fromAddress,
            'Reply-To' => $this->replyToAddress,
            'X-Mailer' => 'PHP/' . phpversion(),
        ];

        $mergedHeaders = array_merge($defaultHeaders, $headers);

        $headerLines = [];
        foreach ($mergedHeaders as $name => $value) {
            $cleanName = self::stripHeaderBreak((string) $name);
            $cleanValue = self::stripHeaderBreak((string) $value);
            if ($cleanName !== '') {
                $headerLines[] = sprintf('%s: %s', $cleanName, $cleanValue);
            }
        }
        $headerString = implode("\r\n", $headerLines);

        return mail($cleanTo, $cleanSubject, $htmlBody, $headerString);
    }

    private static function stripHeaderBreak(string $value): string
    {
        return str_replace(["\r", "\n", "\0", '%0a', '%0d', '%0A', '%0D'], '', $value);
    }
}
