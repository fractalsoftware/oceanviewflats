<?php

declare(strict_types=1);

namespace OceanViewFlats\Tests\Unit\Domain\Fulfillment;

use OceanViewFlats\Domain\Fulfillment\MaskedLogEmailSender;
use PHPUnit\Framework\TestCase;

final class MaskedLogEmailSenderTest extends TestCase
{
    private string $tempLogFile;
    private string|false $previousErrorLog;

    protected function setUp(): void
    {
        parent::setUp();

        $tmpFile = tempnam(sys_get_temp_dir(), 'ovf_masked_mail_log_');
        $this->assertIsString($tmpFile);
        $this->tempLogFile = $tmpFile;
        $this->previousErrorLog = ini_get('error_log');
        ini_set('error_log', $this->tempLogFile);
    }

    protected function tearDown(): void
    {
        ini_set('error_log', $this->previousErrorLog !== false ? $this->previousErrorLog : '');
        if (file_exists($this->tempLogFile)) {
            @unlink($this->tempLogFile);
        }

        parent::tearDown();
    }

    public function testSendMasksRecipientBeforeLoggingAndReturnsTrue(): void
    {
        $sender = new MaskedLogEmailSender();

        $result = $sender->send(
            'guest.user@example.com',
            'Reservation Confirmation',
            '<p>Secret body content: PIN 012345#</p>',
            ['Reply-To' => 'rentals@oceanviewflats.com']
        );

        $this->assertTrue($result);

        $logContents = (string) file_get_contents($this->tempLogFile);
        $this->assertStringContainsString('[Local Mock Email] To: g****@example.com, Subject: Reservation Confirmation', $logContents);
        $this->assertStringNotContainsString('guest.user@example.com', $logContents);
        $this->assertStringNotContainsString('012345#', $logContents);
    }

    public function testSendMasksMultibyteUtf8RecipientAddress(): void
    {
        $sender = new MaskedLogEmailSender();

        $result = $sender->send('álvaro@example.com', 'Access Dispatch', '<p>Welcome</p>');

        $this->assertTrue($result);

        $logContents = (string) file_get_contents($this->tempLogFile);
        $this->assertStringContainsString('[Local Mock Email] To: á****@example.com, Subject: Access Dispatch', $logContents);
        $this->assertStringNotContainsString('álvaro@example.com', $logContents);
    }

    public function testSendMasksMalformedRecipientsAndReturnsTrue(): void
    {
        $sender = new MaskedLogEmailSender();

        $this->assertTrue($sender->send('test@', 'Subject One', 'Body'));
        $this->assertTrue($sender->send('not-an-email', 'Subject Two', 'Body'));

        $logContents = (string) file_get_contents($this->tempLogFile);
        $this->assertStringContainsString('[Local Mock Email] To: ****, Subject: Subject One', $logContents);
        $this->assertStringContainsString('[Local Mock Email] To: ****, Subject: Subject Two', $logContents);
        $this->assertStringNotContainsString('test@', $logContents);
        $this->assertStringNotContainsString('not-an-email', $logContents);
    }
}
