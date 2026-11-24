<?php

declare(strict_types=1);

namespace OceanViewFlats\Domain\Support;

use CurlHandle;

/**
 * Shared TLS verification and strict HTTPS protocol-pinning helper for outbound cURL transports.
 */
final class CurlSecurityOptions
{
    /**
     * Returns hardened cURL transport options unconditionally enforcing certificate,
     * hostname, and HTTPS-only protocol restrictions (blocking http://, file://, gopher://, etc.).
     *
     * @return array<int, mixed>
     */
    public static function build(string $url, bool $includeRedirects = false): array
    {
        $options = [
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
        ];

        // Unconditionally restrict outbound cURL protocols to HTTPS (defense against SSRF/scheme smuggling)
        if (defined('CURLOPT_PROTOCOLS_STR')) {
            $options[CURLOPT_PROTOCOLS_STR] = 'https';
            if ($includeRedirects && defined('CURLOPT_REDIR_PROTOCOLS_STR')) {
                $options[CURLOPT_REDIR_PROTOCOLS_STR] = 'https';
            }
        } elseif (defined('CURLOPT_PROTOCOLS') && defined('CURLPROTO_HTTPS')) {
            $options[CURLOPT_PROTOCOLS] = CURLPROTO_HTTPS;
            if ($includeRedirects && defined('CURLOPT_REDIR_PROTOCOLS')) {
                $options[CURLOPT_REDIR_PROTOCOLS] = CURLPROTO_HTTPS;
            }
        }

        return $options;
    }

    /**
     * Applies hardened TLS and HTTPS-only protocol options directly to an active CurlHandle.
     */
    public static function apply(CurlHandle $ch, string $url, bool $includeRedirects = false): void
    {
        curl_setopt_array($ch, self::build($url, $includeRedirects));
    }
}
