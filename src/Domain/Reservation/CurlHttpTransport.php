<?php

declare(strict_types=1);

namespace OceanViewFlats\Domain\Reservation;

/**
 * Default cURL-backed HTTP transport for fetching upstream iCalendar feeds.
 */
final class CurlHttpTransport implements HttpTransportInterface
{
    public const DEFAULT_TIMEOUT_SECONDS = 10;
    public const DEFAULT_USER_AGENT = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/58.0.3029.110 Safari/537.3';

    public function __construct(
        private readonly int $defaultTimeoutSeconds = self::DEFAULT_TIMEOUT_SECONDS,
        private readonly string $defaultUserAgent = self::DEFAULT_USER_AGENT,
    ) {}

    /**
     * @param string $url Target URL
     * @param array<string, mixed> $options Optional settings (e.g. timeout, userAgent)
     * @return array{statusCode: int, body: string, error: ?string}
     */
    public function get(string $url, array $options = []): array
    {
        if ($url === '') {
            return [
                'statusCode' => 0,
                'body' => '',
                'error' => 'Request URL must not be empty.',
            ];
        }

        $ch = curl_init();
        if ($ch === false) {
            return [
                'statusCode' => 0,
                'body' => '',
                'error' => 'Failed to initialize cURL handle.',
            ];
        }

        $timeout = isset($options['timeout']) && is_int($options['timeout'])
            ? $options['timeout']
            : $this->defaultTimeoutSeconds;

        $userAgent = isset($options['userAgent']) && is_string($options['userAgent']) && $options['userAgent'] !== ''
            ? $options['userAgent']
            : $this->defaultUserAgent;

        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, $timeout);
        if ($userAgent !== '') {
            curl_setopt($ch, CURLOPT_USERAGENT, $userAgent);
        }
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_MAXREDIRS, 5);
        \OceanViewFlats\Domain\Support\CurlSecurityOptions::apply($ch, $url, includeRedirects: true);

        $body = curl_exec($ch);
        $statusCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_errno($ch) ? curl_error($ch) : null;
        curl_close($ch);

        return [
            'statusCode' => $statusCode,
            'body' => is_string($body) ? $body : '',
            'error' => $curlError,
        ];
    }
}
