<?php

declare(strict_types=1);

namespace OceanViewFlats\Tests\Unit\Domain\Security;

use OceanViewFlats\Domain\Security\PayloadEncryptor;
use PHPUnit\Framework\TestCase;

final class PayloadEncryptorTest extends TestCase
{
    public function testEncryptProducesValidJsonEnvelopeAndDecryptRestoresOriginalPayload(): void
    {
        $occupants = [
            [
                'index' => 1,
                'name' => 'Carlos Andrés Mendoza',
                'age' => 35,
                'doc_type' => 'Cédula de Ciudadanía',
                'doc_num' => '1098765432',
                'email' => 'carlos@example.com',
                'phone' => '+57 300 123 4567',
                'country' => 'COLOMBIA',
            ],
            [
                'index' => 2,
                'name' => 'Sofía Mendoza',
                'age' => 9,
                'doc_type' => 'Tarjeta de Identidad',
                'doc_num' => 'TI99887766',
                'email' => null,
                'phone' => null,
                'country' => 'COLOMBIA',
            ],
        ];

        $encryptedJson = PayloadEncryptor::encrypt($occupants, 'custom-secret-key-2026');
        $this->assertTrue(PayloadEncryptor::isEncryptedEnvelope($encryptedJson));
        $this->assertStringNotContainsString('1098765432', $encryptedJson);
        $this->assertStringNotContainsString('TI99887766', $encryptedJson);
        $this->assertStringNotContainsString('Carlos', $encryptedJson);

        $decodedEnvelope = json_decode($encryptedJson, true);
        $this->assertIsArray($decodedEnvelope);
        $this->assertTrue($decodedEnvelope['_encrypted'] ?? false);
        $this->assertSame(1, $decodedEnvelope['v'] ?? null);
        $this->assertSame('xsalsa20-poly1305', $decodedEnvelope['alg'] ?? null);
        $this->assertNotEmpty($decodedEnvelope['nonce'] ?? '');
        $this->assertNotEmpty($decodedEnvelope['ciphertext'] ?? '');

        $decrypted = PayloadEncryptor::decrypt($encryptedJson, 'custom-secret-key-2026');
        $this->assertSame($occupants, $decrypted);
    }

    public function testDecryptSeamlesslyReadsLegacyPlaintextJsonAndEmptyArrays(): void
    {
        $legacyPlaintext = '[{"index":1,"name":"Legacy Guest","doc_type":"Passport","doc_num":"US11223344","age":42}]';
        $this->assertFalse(PayloadEncryptor::isEncryptedEnvelope($legacyPlaintext));

        $decrypted = PayloadEncryptor::decrypt($legacyPlaintext);
        $this->assertCount(1, $decrypted);
        $this->assertSame('Legacy Guest', $decrypted[0]['name']);
        $this->assertSame('US11223344', $decrypted[0]['doc_num']);

        $this->assertSame([], PayloadEncryptor::decrypt('[]'));
        $this->assertSame([], PayloadEncryptor::decrypt([]));
        $this->assertSame([], PayloadEncryptor::decrypt(''));
        $this->assertSame([], PayloadEncryptor::decrypt(null));
    }

    public function testDecryptRejectsTamperedCiphertextOrWrongKey(): void
    {
        $occupants = [['index' => 1, 'name' => 'Alice', 'doc_num' => 'PA123456']];
        $encryptedJson = PayloadEncryptor::encrypt($occupants, 'correct-key');

        // Wrong key fails authentication tag verification and returns empty array
        $this->assertSame([], PayloadEncryptor::decrypt($encryptedJson, 'wrong-key'));

        // Tampered ciphertext fails Poly1305 MAC verification
        $envelope = json_decode($encryptedJson, true);
        $this->assertIsArray($envelope);
        $rawCipher = (string) base64_decode((string) $envelope['ciphertext'], true);
        $tamperedCipher = $rawCipher ^ str_repeat("\x01", strlen($rawCipher));
        $envelope['ciphertext'] = base64_encode($tamperedCipher);

        $this->assertSame([], PayloadEncryptor::decrypt((string) json_encode($envelope), 'correct-key'));
    }

    public function testSupportsBase64Encoded32ByteKey(): void
    {
        $raw32 = random_bytes(SODIUM_CRYPTO_SECRETBOX_KEYBYTES);
        $b64Key = 'base64:' . base64_encode($raw32);

        $payload = [['index' => 1, 'name' => 'Key Test', 'doc_num' => 'CC998877']];
        $encrypted = PayloadEncryptor::encrypt($payload, $b64Key);
        $this->assertSame($payload, PayloadEncryptor::decrypt($encrypted, $b64Key));
    }

    public function testHasConfiguredKeyDetectsExplicitOrEnvironmentKeys(): void
    {
        $prevEnv = $_ENV['APP_ENCRYPTION_KEY'] ?? null;
        $prevGetEnv = getenv('APP_ENCRYPTION_KEY');

        try {
            unset($_ENV['APP_ENCRYPTION_KEY'], $_SERVER['APP_ENCRYPTION_KEY']);
            putenv('APP_ENCRYPTION_KEY');

            $this->assertFalse(PayloadEncryptor::hasConfiguredKey());
            $this->assertFalse(PayloadEncryptor::hasConfiguredKey('   '));
            $this->assertTrue(PayloadEncryptor::hasConfiguredKey('explicit-key'));

            $_ENV['APP_ENCRYPTION_KEY'] = 'env-key-2026';
            $this->assertTrue(PayloadEncryptor::hasConfiguredKey());
        } finally {
            if ($prevEnv !== null) {
                $_ENV['APP_ENCRYPTION_KEY'] = $prevEnv;
            } else {
                unset($_ENV['APP_ENCRYPTION_KEY']);
            }
            if ($prevGetEnv !== false) {
                putenv("APP_ENCRYPTION_KEY={$prevGetEnv}");
            } else {
                putenv('APP_ENCRYPTION_KEY');
            }
        }
    }
}
