<?php

declare(strict_types=1);

namespace OceanViewFlats\Tests\Unit\Domain\Fulfillment;

use DateTimeImmutable;
use OceanViewFlats\Domain\Fulfillment\GuestRegistrySubmission;
use OceanViewFlats\Domain\Fulfillment\HostRegistryEmailRenderer;
use OceanViewFlats\Domain\Fulfillment\OccupantDetails;
use PHPUnit\Framework\TestCase;

final class HostRegistryEmailRendererTest extends TestCase
{
    private DateTimeImmutable $fixedNow;
    private HostRegistryEmailRenderer $renderer;

    protected function setUp(): void
    {
        $this->fixedNow = new DateTimeImmutable('2026-10-01 14:30:00');
        $this->renderer = new HostRegistryEmailRenderer($this->fixedNow);
    }

    private function createSubmission(
        string $propertyId = '1606',
        ?string $carPlates = 'XYZ-123',
        ?string $carModel = 'Toyota RAV4'
    ): GuestRegistrySubmission {
        $occupants = [
            new OccupantDetails(
                index: 1,
                name: 'Jane Doe',
                age: 34,
                docType: 'Passport',
                docNum: 'US12345678',
                phone: '+57 300 123 4567'
            ),
            new OccupantDetails(
                index: 2,
                name: 'John Doe',
                age: 36,
                docType: 'Driver License',
                docNum: 'DL887766',
                phone: '+1 305 555 9876'
            ),
        ];

        return new GuestRegistrySubmission(
            reservationCode: 'res_abc_999',
            propertyId: $propertyId,
            checkIn: '2026-11-01',
            checkOut: '2026-11-05',
            occupants: $occupants,
            primaryGuestEmail: 'jane.doe@example.com',
            carPlates: $carPlates,
            carModel: $carModel,
            ipAddress: '190.24.15.2',
            lang: 'en'
        );
    }

    public function testRenderSubjectWithProperty(): void
    {
        $submission = $this->createSubmission('1606');
        $subject = $this->renderer->renderSubject($submission);

        $this->assertSame('OceanViewFlats Guest Registry Report - OceanViewFlats 1606', $subject);
    }

    public function testRenderSubjectWithoutProperty(): void
    {
        $submission = $this->createSubmission('');
        $subject = $this->renderer->renderSubject($submission);

        $this->assertSame('OceanViewFlats Guest Registry Report - Unspecified', $subject);
    }

    public function testRenderSubjectStripsNewlines(): void
    {
        $submission = $this->createSubmission("1606\r\nInjected Header: evil");
        $subject = $this->renderer->renderSubject($submission);

        $this->assertStringNotContainsString("\r", $subject);
        $this->assertStringNotContainsString("\n", $subject);
        $this->assertSame('OceanViewFlats Guest Registry Report - OceanViewFlats 1606Injected Header: evil', $subject);
    }

    public function testRenderPlainTextMasksDocNumbersAndOmitsPrimaryDocAndGoogleSheet(): void
    {
        $submission = $this->createSubmission('1606', 'XYZ-123', 'Toyota RAV4');
        $text = $this->renderer->renderPlainText(
            submission: $submission,
            doorCode: '0749201#'
        );

        // Header
        $this->assertStringContainsString("OceanViewFlats Official Guest Registry Report\n", $text);

        // Stay information
        $this->assertStringContainsString('STAY INFORMATION', $text);
        $this->assertStringContainsString('Property:      OceanViewFlats 1606', $text);
        $this->assertStringContainsString('Check-in:      2026-11-01', $text);
        $this->assertStringContainsString('Check-out:     2026-11-05', $text);
        $this->assertStringContainsString('Total Guests:  2', $text);
        $this->assertStringContainsString('Primary Email: jane.doe@example.com', $text);

        // Vehicle info
        $this->assertStringContainsString('VEHICLE INFORMATION (OPTIONAL)', $text);
        $this->assertStringContainsString('Plates:        XYZ-123', $text);
        $this->assertStringContainsString('Make & Model:  Toyota RAV4', $text);

        // Registered guests: document numbers and phones must be masked (****5678, ****7766, ****4567, ****9876)
        $this->assertStringContainsString('REGISTERED GUESTS DETAILS', $text);
        $this->assertStringContainsString("Guest #1:\n  Name:     Jane Doe\n  Email:    jane.doe@example.com\n  Phone:    ****4567\n  ID/Doc:   Passport (****5678)\n  Age:      34", $text);
        $this->assertStringContainsString("Guest #2:\n  Name:     John Doe\n  Phone:    ****9876\n  ID/Doc:   Driver License (****7766)\n  Age:      36", $text);
        $this->assertStringNotContainsString('US12345678', $text);
        $this->assertStringNotContainsString('DL887766', $text);
        $this->assertStringNotContainsString('300 123 4567', $text);
        $this->assertStringNotContainsString('305 555 9876', $text);

        // Smart lock access PIN: Primary Guest Doc line must be removed
        $this->assertStringContainsString('SMART LOCK ACCESS PIN (ACTION REQUIRED)', $text);
        $this->assertStringContainsString('Generated Door PIN:  0749201#', $text);
        $this->assertStringNotContainsString('Primary Guest Doc', $text);
        $this->assertStringContainsString('Lock Instructions:   Program this 7-digit code (ending in #)', $text);
        $this->assertStringContainsString('into the apartment smart lock companion app.', $text);

        // System logs: Google Sheet line must be removed
        $this->assertStringContainsString('SYSTEM LOGS', $text);
        $this->assertStringContainsString('Submission IP:  190.24.15.2', $text);
        $this->assertStringContainsString('Timestamp:      2026-10-01 14:30:00', $text);
        $this->assertStringNotContainsString('Google Sheet', $text);
    }

    public function testRenderPlainTextWithoutVehicleInfo(): void
    {
        $submission = $this->createSubmission('1707', null, null);
        $text = $this->renderer->renderPlainText(
            submission: $submission,
            doorCode: '0112233#'
        );

        $this->assertStringNotContainsString('VEHICLE INFORMATION (OPTIONAL)', $text);
        $this->assertStringContainsString('Property:      OceanViewFlats 1707', $text);
        $this->assertStringContainsString('Generated Door PIN:  0112233#', $text);
    }

    public function testRenderHtmlMasksDocNumbersAndOmitsPrimaryDocAndGoogleSheet(): void
    {
        $submission = $this->createSubmission('1606', 'ABC-999', 'Hyundai Tucson');
        $html = $this->renderer->renderHtml(
            submission: $submission,
            doorCode: '0556677#'
        );

        // Structure & Sections
        $this->assertStringContainsString('<!DOCTYPE html>', $html);
        $this->assertStringContainsString('Guest Registry Report', $html);
        $this->assertStringContainsString('Stay Information', $html);
        $this->assertStringContainsString('OceanViewFlats 1606', $html);
        $this->assertStringContainsString('Primary Email', $html);
        $this->assertStringContainsString('jane.doe@example.com', $html);
        $this->assertStringContainsString('Vehicle Information', $html);
        $this->assertStringContainsString('ABC-999', $html);
        $this->assertStringContainsString('Hyundai Tucson', $html);

        // Occupants with masked document numbers and phone numbers
        $this->assertStringContainsString('Registered Guests Details', $html);
        $this->assertStringContainsString('Jane Doe', $html);
        $this->assertStringContainsString('<code>jane.doe@example.com</code>', $html);
        $this->assertStringContainsString('Passport (****5678)', $html);
        $this->assertStringContainsString('Phone: <code>****4567</code>', $html);
        $this->assertStringContainsString('John Doe', $html);
        $this->assertStringContainsString('Driver License (****7766)', $html);
        $this->assertStringContainsString('Phone: <code>****9876</code>', $html);
        $this->assertStringNotContainsString('US12345678', $html);
        $this->assertStringNotContainsString('DL887766', $html);
        $this->assertStringNotContainsString('300 123 4567', $html);
        $this->assertStringNotContainsString('305 555 9876', $html);

        // PIN box without Primary Guest Doc
        $this->assertStringContainsString('Smart Lock Access PIN (Action Required)', $html);
        $this->assertStringContainsString('0556677#', $html);
        $this->assertStringNotContainsString('Primary Guest Doc', $html);
        $this->assertStringContainsString('Program this 7-digit code (ending in #) into the apartment smart lock companion app.', $html);

        // System logs without Google Sheet
        $this->assertStringContainsString('Submission IP', $html);
        $this->assertStringContainsString('190.24.15.2', $html);
        $this->assertStringContainsString('2026-10-01 14:30:00', $html);
        $this->assertStringNotContainsString('Google Sheet', $html);
    }

    public function testRenderHtmlEscapesUserInputs(): void
    {
        $maliciousOccupant = new OccupantDetails(
            index: 1,
            name: '<script>alert("hacked")</script>',
            age: 25,
            docType: 'Other ID',
            docNum: '<img src=x onerror=alert(1)>'
        );

        $submission = new GuestRegistrySubmission(
            reservationCode: 'res_xss',
            propertyId: '<b>1606</b>',
            checkIn: '2026-11-01',
            checkOut: '2026-11-05',
            occupants: [$maliciousOccupant],
            primaryGuestEmail: 'malicious@example.com',
            carPlates: '<script>',
            carModel: '<b>Model</b>',
            ipAddress: '<test-ip>',
            lang: 'es'
        );

        $html = $this->renderer->renderHtml(
            submission: $submission,
            doorCode: '0123456#'
        );

        $this->assertStringNotContainsString('<script>alert("hacked")</script>', $html);
        $this->assertStringNotContainsString('<img src=x onerror=alert(1)>', $html);
        $this->assertStringNotContainsString('<b>1606</b>', $html);
        $this->assertStringNotContainsString('<test-ip>', $html);

        $this->assertStringContainsString('&lt;script&gt;alert(&quot;hacked&quot;)&lt;/script&gt;', $html);
        $this->assertStringContainsString('****(1)&gt;', $html);
        $this->assertStringContainsString('OceanViewFlats &lt;b&gt;1606&lt;/b&gt;', $html);
        $this->assertStringContainsString('&lt;test-ip&gt;', $html);
    }

    public function testRenderOmitsCompanionEmailInBothTextAndHtml(): void
    {
        $occupants = [
            new OccupantDetails(1, 'Primary Person', 35, 'Passport', 'P111', 'primary.person@example.com'),
            new OccupantDetails(2, 'Companion Person', 30, 'Passport', 'P222'),
        ];

        $submission = new GuestRegistrySubmission(
            reservationCode: 'res_companion_email_check',
            propertyId: '1606',
            checkIn: '2026-11-01',
            checkOut: '2026-11-05',
            occupants: $occupants,
            primaryGuestEmail: 'primary.person@example.com'
        );

        $text = $this->renderer->renderPlainText($submission, '0123456#');
        $html = $this->renderer->renderHtml($submission, '0123456#');

        // Plain text checks (short doc numbers <= 4 chars mask to ****)
        $this->assertStringContainsString("Guest #1:\n  Name:     Primary Person\n  Email:    primary.person@example.com", $text);
        $this->assertStringContainsString("Guest #2:\n  Name:     Companion Person\n  ID/Doc:   Passport (****)", $text);
        $this->assertStringNotContainsString("Guest #2:\n  Name:     Companion Person\n  Email:", $text);

        // HTML checks
        $this->assertStringContainsString('Primary Email', $html);
        $this->assertStringContainsString('<code>primary.person@example.com</code>', $html);
        $this->assertStringContainsString('Guest #2: <strong>Companion Person</strong>', $html);
        $this->assertSame(1, substr_count($html, 'Email: <code>'));
    }
}
