<?php

declare(strict_types=1);

namespace OceanViewFlats\Domain\Security;

use RuntimeException;

/**
 * Owner-only, flock-synchronized file-backed nonce store for single-use CAPTCHA enforcement.
 *
 * Designed for shared hosting (cPanel Pattern A), Docker, and local runtimes with zero
 * database or extension requirements. Uses an owner-only (0700) directory, owner-only (0600)
 * store file, symlink checks, and kernel-level exclusive file locks (LOCK_EX) to prevent
 * concurrent replay race conditions.
 */
final class FileCaptchaNonceStore implements CaptchaNonceStoreInterface
{
    private const MAX_ENTRIES = 10000;

    private readonly string $filePath;

    public function __construct(?string $filePath = null)
    {
        $resolved = $filePath !== null && trim($filePath) !== ''
            ? trim($filePath)
            : self::resolveConfiguredPath();

        if ($resolved === null) {
            $uidTag = function_exists('posix_geteuid')
                ? (string) posix_geteuid()
                : (string) getmyuid();
            $dir = rtrim(sys_get_temp_dir(), '/\\') . '/ovf_captcha_nonces_' . $uidTag;
            $resolved = $dir . '/consumed_nonces.json';
        }

        $this->filePath = $resolved;
    }

    public static function default(): self
    {
        return new self();
    }

    public function consume(string $nonce, int $expiresAt, int $now): bool
    {
        if (trim($nonce) === '') {
            return false;
        }

        $this->ensureSecureStorageDirectory();

        if (is_link($this->filePath)) {
            throw new RuntimeException('Refusing to use symlinked CAPTCHA nonce store file.');
        }

        $fp = @fopen($this->filePath, 'c+b');
        if ($fp === false) {
            throw new RuntimeException('Unable to open CAPTCHA nonce store file.');
        }

        @chmod($this->filePath, 0600);

        try {
            if (!flock($fp, LOCK_EX)) {
                throw new RuntimeException('Unable to acquire exclusive lock on CAPTCHA nonce store.');
            }

            try {
                if (function_exists('posix_geteuid')) {
                    $stat = fstat($fp);
                    if ($stat !== false && (int) $stat['uid'] !== posix_geteuid()) {
                        throw new RuntimeException('CAPTCHA nonce store file is not owned by the current process user.');
                    }
                }

                rewind($fp);
                $raw = stream_get_contents($fp);
                $store = $this->decodeStore($raw !== false ? $raw : '');

                // Prune expired nonces on every mutation
                foreach ($store as $key => $exp) {
                    if ($exp < $now) {
                        unset($store[$key]);
                    }
                }

                if (isset($store[$nonce])) {
                    return false;
                }

                $store[$nonce] = $expiresAt;

                if (count($store) > self::MAX_ENTRIES) {
                    asort($store, SORT_NUMERIC);
                    $store = array_slice($store, -self::MAX_ENTRIES, null, true);
                }

                $encoded = json_encode($store, JSON_THROW_ON_ERROR);
                rewind($fp);
                if (!ftruncate($fp, 0)) {
                    throw new RuntimeException('Unable to truncate CAPTCHA nonce store.');
                }
                if (fwrite($fp, $encoded) === false) {
                    throw new RuntimeException('Unable to persist CAPTCHA nonce store.');
                }
                fflush($fp);

                return true;
            } finally {
                flock($fp, LOCK_UN);
            }
        } finally {
            fclose($fp);
        }
    }

    private static function resolveConfiguredPath(): ?string
    {
        $candidates = [
            $_ENV['CAPTCHA_NONCE_STORE_PATH'] ?? null,
            $_SERVER['CAPTCHA_NONCE_STORE_PATH'] ?? null,
            getenv('CAPTCHA_NONCE_STORE_PATH'),
        ];

        foreach ($candidates as $candidate) {
            if (is_string($candidate) && trim($candidate) !== '') {
                return trim($candidate);
            }
        }

        return null;
    }

    private function ensureSecureStorageDirectory(): void
    {
        $dir = dirname($this->filePath);

        if (is_link($dir)) {
            throw new RuntimeException('Refusing to use symlinked CAPTCHA nonce store directory.');
        }

        if (!is_dir($dir)) {
            if (!@mkdir($dir, 0700, true) && !is_dir($dir)) {
                throw new RuntimeException('Unable to create CAPTCHA nonce store directory.');
            }
        }

        if (function_exists('posix_geteuid')) {
            $owner = @fileowner($dir);
            if ($owner !== false && $owner !== posix_geteuid()) {
                throw new RuntimeException('CAPTCHA nonce store directory is not owned by the current process user.');
            }
        }

        @chmod($dir, 0700);
    }

    /**
     * Decodes persisted nonce JSON and fails closed on corruption so replays cannot slip through.
     *
     * @return array<string, int>
     */
    private function decodeStore(string $raw): array
    {
        $trimmed = trim($raw);
        if ($trimmed === '') {
            return [];
        }

        $decoded = json_decode($trimmed, true);
        if (!is_array($decoded) || json_last_error() !== JSON_ERROR_NONE) {
            throw new RuntimeException('Corrupted CAPTCHA nonce store file.');
        }

        $normalized = [];
        foreach ($decoded as $key => $value) {
            if (is_string($key) && (is_int($value) || (is_string($value) && ctype_digit($value)))) {
                $normalized[$key] = (int) $value;
            }
        }

        return $normalized;
    }
}
