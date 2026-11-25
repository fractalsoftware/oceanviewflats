<?php

declare(strict_types=1);

namespace OceanViewFlats\Domain\Fulfillment;

/**
 * Standard cURL implementation of HttpTransportInterface.
 */
final class CurlHttpTransport implements HttpTransportInterface
{
    public const DEFAULT_TIMEOUT_SECONDS = 15;
    public const DEFAULT_USER_AGENT = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36';

    public function __construct(
        private readonly int $defaultTimeoutSeconds = self::DEFAULT_TIMEOUT_SECONDS,
        private readonly string $defaultUserAgent = self::DEFAULT_USER_AGENT,
    ) {}

    public function post(string $url, array|string $data = [], array $headers = [], array $options = []): array
    {
        if ($url === '') {
            return [
                'statusCode' => 0,
                'body' => '',
                'headers' => [],
                'cookies' => [],
                'error' => 'Request URL must not be empty.',
            ];
        }

        $ch = curl_init();
        if ($ch === false) {
            return [
                'statusCode' => 0,
                'body' => '',
                'headers' => [],
                'cookies' => [],
                'error' => 'Failed to initialize cURL handle.',
            ];
        }

        $timeout = isset($options['timeout']) && is_int($options['timeout'])
            ? $options['timeout']
            : $this->defaultTimeoutSeconds;

        $userAgent = isset($options['userAgent']) && is_string($options['userAgent']) && $options['userAgent'] !== ''
            ? $options['userAgent']
            : $this->defaultUserAgent;

        $followRedirects = (bool) ($options['followRedirects'] ?? false);

        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, $timeout);
        if ($userAgent !== '') {
            curl_setopt($ch, CURLOPT_USERAGENT, $userAgent);
        }
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, $followRedirects);
        \OceanViewFlats\Domain\Support\CurlSecurityOptions::apply($ch, $url, includeRedirects: true);

        // Prepare post payload
        if (is_array($data)) {
            $flattened = $this->flattenPayload($data);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $flattened);
        } else {
            curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
        }

        // Format and apply headers
        $headerLines = [];
        foreach ($headers as $name => $value) {
            if (is_numeric($name)) {
                $headerLines[] = (string) $value;
            } else {
                $headerLines[] = "{$name}: {$value}";
            }
        }
        if ($headerLines !== []) {
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headerLines);
        }

        // Apply cookies
        $cookiesOption = $options['cookies'] ?? null;
        if (is_array($cookiesOption) && $cookiesOption !== []) {
            $cookiePairs = [];
            foreach ($cookiesOption as $k => $v) {
                $cookiePairs[] = "{$k}={$v}";
            }
            curl_setopt($ch, CURLOPT_COOKIE, implode('; ', $cookiePairs));
        } elseif (is_string($cookiesOption)) {
            $trimmedCookies = trim($cookiesOption);
            if ($trimmedCookies !== '') {
                curl_setopt($ch, CURLOPT_COOKIE, $trimmedCookies);
            }
        }

        // Capture response headers & cookies
        $responseHeaders = [];
        $responseCookies = [];
        curl_setopt($ch, CURLOPT_HEADERFUNCTION, function ($ch, string $headerLine) use (&$responseHeaders, &$responseCookies): int {
            $len = strlen($headerLine);
            $trimmed = trim($headerLine);
            if ($trimmed === '') {
                return $len;
            }

            if (preg_match('/^Set-Cookie:\s*([^=;]+)=([^;]*)/i', $trimmed, $matches)) {
                $responseCookies[trim($matches[1])] = trim($matches[2]);
            }

            $parts = explode(':', $trimmed, 2);
            if (count($parts) === 2) {
                $key = strtolower(trim($parts[0]));
                $responseHeaders[$key] = trim($parts[1]);
            }

            return $len;
        });

        $body = curl_exec($ch);
        $statusCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $effectiveUrl = (string) curl_getinfo($ch, CURLINFO_EFFECTIVE_URL);
        $curlError = curl_errno($ch) ? curl_error($ch) : null;
        curl_close($ch);

        return [
            'statusCode' => $statusCode,
            'body' => is_string($body) ? $body : '',
            'headers' => $responseHeaders,
            'cookies' => $responseCookies,
            'effectiveUrl' => $effectiveUrl,
            'error' => $curlError,
        ];
    }

    /**
     * Flattens multi-dimensional arrays for multipart cURL transmission.
     * E.g. ['typeid' => ['CC', 'TI']] becomes ['typeid[0]' => 'CC', 'typeid[1]' => 'TI'].
     *
     * @param array<string, mixed> $data
     * @return array<string, string>
     */
    private function flattenPayload(array $data, string $prefix = ''): array
    {
        $result = [];
        foreach ($data as $key => $value) {
            $cleanKey = (string) $key;
            $fullKey = $prefix === '' ? $cleanKey : "{$prefix}[{$cleanKey}]";
            if (is_array($value)) {
                $base = rtrim($fullKey, '[]');
                foreach ($value as $idx => $subValue) {
                    $subKey = "{$base}[{$idx}]";
                    if (is_array($subValue)) {
                        $result = array_merge($result, $this->flattenPayload($subValue, $subKey));
                    } else {
                        $result[$subKey] = (string) $subValue;
                    }
                }
            } else {
                $result[$fullKey] = (string) $value;
            }
        }
        return $result;
    }
}
