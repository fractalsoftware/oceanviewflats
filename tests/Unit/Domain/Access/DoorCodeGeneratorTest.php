<?php

declare(strict_types=1);

namespace OceanViewFlats\Tests\Unit\Domain\Access;

use OceanViewFlats\Domain\Access\DoorCodeGenerator;
use PHPUnit\Framework\TestCase;

final class DoorCodeGeneratorTest extends TestCase
{
    public function testGenerateProducesValidCryptographicallyRandomPinIgnoringDocumentAndPhone(): void
    {
        $docNum = '1020304050';
        $phone = '+57 300 987 6543';

        $codes = [];
        for ($i = 0; $i < 30; $i++) {
            $code = DoorCodeGenerator::generate($docNum, $phone);
            $this->assertSame(8, strlen($code));
            $this->assertStringStartsWith('0', $code);
            $this->assertStringEndsWith('#', $code);
            $this->assertMatchesRegularExpression('/^0[1-9]\d{5}#$/', $code);
            $codes[] = $code;
        }

        // Must be non-deterministic across invocations
        $this->assertGreaterThan(1, count(array_unique($codes)));

        // Must never deterministically equal the trailing 6 digits of the document or phone
        $uniqueCodes = array_unique($codes);
        $this->assertNotSame(['0304050#'], array_values($uniqueCodes));
        $this->assertNotSame(['0876543#'], array_values($uniqueCodes));
    }

    public function testGenerateWithShortOrEmptyInputsNeverProducesZeroPaddedDocDigits(): void
    {
        $samples = [
            ['1234', ''],
            ['ABC', '99'],
            ['', ''],
            ['N/A', 'NONE'],
            ['CC 1.020.304.050-K', '+57 300 123 4567'],
        ];

        foreach ($samples as [$doc, $phone]) {
            $code = DoorCodeGenerator::generate($doc, $phone);
            $this->assertSame(8, strlen($code));
            $this->assertMatchesRegularExpression('/^0[1-9]\d{5}#$/', $code);
            // random_int(100000, 999999) guarantees first core digit is 1-9, never 0001234# or 0000000#
            $this->assertNotSame('0001234#', $code);
            $this->assertNotSame('0000099#', $code);
            $this->assertNotSame('0000000#', $code);
        }
    }

    public function testAlwaysReturnsSevenDigitsPlusHash(): void
    {
        $samples = [
            ['72345678', ''],
            ['A1B2C3D4E5F6', ''],
            ['555', ''],
            ['', '3001234567'],
            ['SPECIAL-DOC-1', ''],
        ];

        foreach ($samples as [$doc, $phone]) {
            $code = DoorCodeGenerator::generate($doc, $phone);
            $this->assertSame(8, strlen($code), "Code '$code' must be exactly 8 characters");
            $this->assertStringStartsWith('0', $code, "Code '$code' must start with '0'");
            $this->assertStringEndsWith('#', $code, "Code '$code' must end with '#'");
            $this->assertMatchesRegularExpression('/^0\d{6}#$/', $code);
        }
    }

    public function testGenerateRandomProducesValidCodeFormat(): void
    {
        $code = DoorCodeGenerator::generateRandom();
        $this->assertSame(8, strlen($code));
        $this->assertStringStartsWith('0', $code);
        $this->assertStringEndsWith('#', $code);
        $this->assertMatchesRegularExpression('/^0\d{6}#$/', $code);
    }

    public function testGenerateForPropertyProducesValidCodeFormat(): void
    {
        $code = DoorCodeGenerator::generateForProperty('1606');
        $this->assertSame(8, strlen($code));
        $this->assertStringStartsWith('0', $code);
        $this->assertStringEndsWith('#', $code);
        $this->assertMatchesRegularExpression('/^0\d{6}#$/', $code);
    }
}
