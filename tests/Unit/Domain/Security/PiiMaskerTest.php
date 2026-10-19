<?php

declare(strict_types=1);

namespace OceanViewFlats\Tests\Unit\Domain\Security;

use OceanViewFlats\Domain\Security\PiiMasker;
use PHPUnit\Framework\TestCase;

final class PiiMaskerTest extends TestCase
{
    public function testMaskDocNumberPreservesOnlyLastFourCharacters(): void
    {
        $this->assertSame('****5678', PiiMasker::maskDocNumber('US12345678'));
        $this->assertSame('****7766', PiiMasker::maskDocNumber('DL887766'));
        $this->assertSame('****5432', PiiMasker::maskDocNumber('  1098765432  '));
        $this->assertSame('****', PiiMasker::maskDocNumber('1234'));
        $this->assertSame('****', PiiMasker::maskDocNumber('AB'));
        $this->assertSame('****', PiiMasker::maskDocNumber(''));
    }

    public function testMaskPhonePreservesLastFourDigits(): void
    {
        $this->assertSame('****4567', PiiMasker::maskPhone('+57 300 123 4567'));
        $this->assertSame('****3456', PiiMasker::maskPhone('+34 600 123 456'));
        $this->assertSame('****', PiiMasker::maskPhone('1234'));
        $this->assertSame('', PiiMasker::maskPhone(''));
        $this->assertNull(PiiMasker::maskPhone(null));
        $this->assertSame('N/A', PiiMasker::maskPhone('N/A'));
    }

    public function testMaskSubmissionArrayMasksOccupantIdsAndPhones(): void
    {
        $submission = [
            'reservation_code' => 'ovf_123',
            'occupants' => [
                [
                    'index' => 1,
                    'name' => 'Jane Doe',
                    'doc_type' => 'Passport',
                    'doc_num' => 'US12345678',
                    'phone' => '+57 300 123 4567',
                ],
                [
                    'index' => 2,
                    'name' => 'John Doe',
                    'doc_type' => 'Cédula de Ciudadanía',
                    'doc_num' => '1098765432',
                    'phone' => null,
                ],
            ],
        ];

        $masked = PiiMasker::maskSubmissionArray($submission);
        $this->assertSame('****5678', $masked['occupants'][0]['doc_num']);
        $this->assertSame('****4567', $masked['occupants'][0]['phone']);
        $this->assertSame('****5432', $masked['occupants'][1]['doc_num']);
        $this->assertNull($masked['occupants'][1]['phone']);
    }

    public function testIsTruthyConsentEvaluatesAffirmativeValuesAndRejectsOthers(): void
    {
        $this->assertTrue(PiiMasker::isTruthyConsent(true));
        $this->assertTrue(PiiMasker::isTruthyConsent(1));
        $this->assertTrue(PiiMasker::isTruthyConsent('1'));
        $this->assertTrue(PiiMasker::isTruthyConsent('true'));
        $this->assertTrue(PiiMasker::isTruthyConsent(' TRUE '));
        $this->assertTrue(PiiMasker::isTruthyConsent('on'));
        $this->assertTrue(PiiMasker::isTruthyConsent('yes'));

        $this->assertFalse(PiiMasker::isTruthyConsent(false));
        $this->assertFalse(PiiMasker::isTruthyConsent(0));
        $this->assertFalse(PiiMasker::isTruthyConsent('0'));
        $this->assertFalse(PiiMasker::isTruthyConsent('false'));
        $this->assertFalse(PiiMasker::isTruthyConsent(''));
        $this->assertFalse(PiiMasker::isTruthyConsent(null));
        $this->assertFalse(PiiMasker::isTruthyConsent([]));
    }
}
