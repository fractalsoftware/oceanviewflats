<?php

declare(strict_types=1);

namespace OceanViewFlats\Domain\Fulfillment;

use DateTimeImmutable;
use OceanViewFlats\Domain\Reservation\Reservation;
use OceanViewFlats\Domain\Support\PathResolver;
use OceanViewFlats\Domain\Support\PublicUrlBuilder;

final class ConfirmationEmailRenderer implements ConfirmationEmailRendererInterface
{
    /**
     * @var array<string, array<string, mixed>>
     */
    private readonly array $translations;
    private readonly PublicUrlBuilder $urlBuilder;

    /**
     * @param array<string, array<string, mixed>>|null $translations
     */
    public function __construct(
        private readonly string $baseUrl = 'https://www.oceanviewflats.com',
        ?array $translations = null,
        ?PublicUrlBuilder $urlBuilder = null
    ) {
        $this->translations = $translations ?? PathResolver::loadTranslations();
        $this->urlBuilder = $urlBuilder ?? new PublicUrlBuilder($this->baseUrl);
    }

    public function renderGuestConfirmationHtml(Reservation $reservation): string
    {
        $lang = $this->resolveLanguage($reservation);
        $t = $this->translations[$lang]['webhook'] ?? $this->translations['en']['webhook'];

        $nights = $this->calculateNights($reservation->checkIn, $reservation->checkOut);
        $formattedTotal = '$ ' . number_format($reservation->totalPrice, 0, ',', '.') . ' COP';
        
        $registryUrl = $this->buildRegistryUrl($reservation, $lang);

        $safeGuestName = htmlspecialchars($reservation->guestName, ENT_QUOTES, 'UTF-8');
        $safePropertyId = htmlspecialchars($reservation->propertyId, ENT_QUOTES, 'UTF-8');
        $safeUid = htmlspecialchars($reservation->reservationUid, ENT_QUOTES, 'UTF-8');
        $safeCheckIn = htmlspecialchars($reservation->checkIn, ENT_QUOTES, 'UTF-8');
        $safeCheckOut = htmlspecialchars($reservation->checkOut, ENT_QUOTES, 'UTF-8');

        $introText = sprintf($t['intro'], $safeGuestName);
        $nightsText = sprintf($t['nights_val'], $nights);

        return <<<HTML
<!DOCTYPE html>
<html lang="{$lang}">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <style>
    body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif; background-color: #f8fafc; color: #1e293b; margin: 0; padding: 0; line-height: 1.6; }
    .container { max-width: 600px; margin: 32px auto; background-color: #ffffff; border-radius: 16px; overflow: hidden; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05); border: 1px solid #e2e8f0; }
    .header { background-color: #0f172a; padding: 28px 24px; text-align: center; }
    .header h1 { color: #ffffff; font-size: 22px; margin: 0 0 6px 0; font-weight: 800; letter-spacing: -0.5px; }
    .header p { color: #94a3b8; font-size: 14px; margin: 0; }
    .content { padding: 28px 24px; }
    .summary-card { background-color: #f8fafc; padding: 20px; border-radius: 12px; margin-bottom: 24px; border: 1px solid #e2e8f0; }
    .summary-title { font-weight: 700; font-size: 13px; text-transform: uppercase; color: #64748b; letter-spacing: 1px; margin-bottom: 14px; }
    .item-row { display: flex; justify-content: space-between; padding: 9px 0; border-bottom: 1px solid #e2e8f0; font-size: 14px; }
    .item-row:last-child { border-bottom: none; }
    .total-row { display: flex; justify-content: space-between; padding-top: 14px; margin-top: 14px; border-top: 2px solid #cbd5e1; font-size: 17px; font-weight: 800; color: #0f172a; }
    .total-amount { color: #0d9488; }
    .notice-box { background-color: #f0fdf4; border-left: 4px solid #10b981; padding: 14px 16px; border-radius: 6px; margin: 20px 0; font-size: 13px; color: #166534; }
    .cta-container { text-align: center; margin: 24px 0; }
    .btn { display: inline-block; padding: 14px 28px; background-color: #0d9488; color: #ffffff !important; text-decoration: none; border-radius: 8px; font-weight: 700; font-size: 15px; box-shadow: 0 4px 6px -1px rgba(13,148,136,0.25); }
    .footer-note { font-size: 13px; color: #475569; margin-top: 20px; line-height: 1.5; }
    .footer { text-align: center; padding: 24px; background-color: #f8fafc; border-top: 1px solid #e2e8f0; font-size: 12px; color: #94a3b8; }
    code { background-color: #f1f5f9; padding: 2px 6px; border-radius: 4px; font-family: monospace; font-size: 13px; color: #0f172a; }
  </style>
</head>
<body>
  <div class="container">
    <div class="header">
      <h1>{$t['title']}</h1>
      <p>OceanViewFlats Santa Marta</p>
    </div>
    <div class="content">
      <p>{$introText}</p>
      <p>{$t['desc']}</p>
      
      <div class="summary-card">
        <div class="summary-title">{$t['summary']}</div>
        <div class="item-row"><span>{$t['property']}</span><strong>OceanViewFlats {$safePropertyId}</strong></div>
        <div class="item-row"><span>{$t['code']}</span><strong><code>{$safeUid}</code></strong></div>
        <div class="item-row"><span>Check-In</span><strong>{$safeCheckIn}</strong></div>
        <div class="item-row"><span>Check-Out</span><strong>{$safeCheckOut}</strong></div>
        <div class="item-row"><span>{$t['nights']}</span><strong>{$nightsText}</strong></div>
        <div class="total-row"><span>{$t['total']}</span><strong class="total-amount">{$formattedTotal}</strong></div>
      </div>

      <p class="footer-note">{$t['footer']}</p>

      <div class="cta-container">
        <a href="{$registryUrl}" class="btn">{$t['btn_registry']}</a>
      </div>

      <div class="notice-box">
        {$t['access_notice']}
      </div>
    </div>
    <div class="footer">
      &copy; 2026 OceanViewFlats. Calle 26 # 2-80, Playa Salguero, Santa Marta, Colombia.
    </div>
  </div>
</body>
</html>
HTML;
    }

    public function renderGuestSubject(Reservation $reservation): string
    {
        $lang = $this->resolveLanguage($reservation);
        $t = $this->translations[$lang]['webhook'] ?? $this->translations['en']['webhook'];

        return sprintf($t['subject'], $reservation->propertyId);
    }

    public function renderHostNotificationHtml(Reservation $reservation): string
    {
        $lang = $this->resolveLanguage($reservation);
        $nights = $this->calculateNights($reservation->checkIn, $reservation->checkOut);
        $formattedTotal = '$ ' . number_format($reservation->totalPrice, 0, ',', '.') . ' COP';

        $safeGuestName = htmlspecialchars($reservation->guestName, ENT_QUOTES, 'UTF-8');
        $safeGuestEmail = htmlspecialchars($reservation->guestEmail, ENT_QUOTES, 'UTF-8');
        $safeGuestPhone = htmlspecialchars($reservation->guestPhone, ENT_QUOTES, 'UTF-8');
        $safePropertyId = htmlspecialchars($reservation->propertyId, ENT_QUOTES, 'UTF-8');
        $safeUid = htmlspecialchars($reservation->reservationUid, ENT_QUOTES, 'UTF-8');
        $safeCheckIn = htmlspecialchars($reservation->checkIn, ENT_QUOTES, 'UTF-8');
        $safeCheckOut = htmlspecialchars($reservation->checkOut, ENT_QUOTES, 'UTF-8');
        $safePaymentId = htmlspecialchars((string)($reservation->mercadopagoPaymentId ?? 'N/A'), ENT_QUOTES, 'UTF-8');
        $safePaymentMethod = htmlspecialchars((string)($reservation->paymentMethodId ?? 'N/A'), ENT_QUOTES, 'UTF-8');

        return <<<HTML
<!DOCTYPE html>
<html>
<head>
  <meta charset="UTF-8">
  <style>
    body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif; background-color: #f8fafc; color: #1e293b; margin: 0; padding: 0; line-height: 1.6; }
    .container { max-width: 600px; margin: 32px auto; background-color: #ffffff; border-radius: 16px; overflow: hidden; border: 1px solid #e2e8f0; }
    .header { background-color: #047857; padding: 24px; text-align: center; color: #ffffff; }
    .content { padding: 28px 24px; }
    .card { background-color: #f8fafc; padding: 20px; border-radius: 12px; margin-bottom: 20px; border: 1px solid #e2e8f0; }
    .row { display: flex; justify-content: space-between; padding: 8px 0; border-bottom: 1px solid #edf2f7; font-size: 14px; }
    .row:last-child { border-bottom: none; }
    .footer { text-align: center; padding: 20px; font-size: 12px; color: #94a3b8; border-top: 1px solid #e2e8f0; }
  </style>
</head>
<body>
  <div class="container">
    <div class="header">
      <h2 style="margin:0;color:#ffffff;">Direct Booking Paid & Confirmed</h2>
      <p style="margin:4px 0 0 0;font-size:13px;opacity:0.9;">OceanViewFlats Property Management</p>
    </div>
    <div class="content">
      <p>A direct reservation payment has been cleared and verified.</p>
      <div class="card">
        <div class="row"><span>Property</span><strong>OceanViewFlats {$safePropertyId}</strong></div>
        <div class="row"><span>Reservation Code</span><strong><code>{$safeUid}</code></strong></div>
        <div class="row"><span>Primary Guest</span><strong>{$safeGuestName}</strong></div>
        <div class="row"><span>Guest Email</span><strong>{$safeGuestEmail}</strong></div>
        <div class="row"><span>Guest Phone</span><strong>{$safeGuestPhone}</strong></div>
        <div class="row"><span>Check-In</span><strong>{$safeCheckIn}</strong></div>
        <div class="row"><span>Check-Out</span><strong>{$safeCheckOut}</strong></div>
        <div class="row"><span>Nights</span><strong>{$nights} nights</strong></div>
        <div class="row"><span>Amount Paid</span><strong style="color:#047857;">{$formattedTotal}</strong></div>
        <div class="row"><span>Payment ID</span><strong>{$safePaymentId}</strong></div>
        <div class="row"><span>Payment Method</span><strong>{$safePaymentMethod}</strong></div>
        <div class="row"><span>Guest Language</span><strong>{$lang}</strong></div>
      </div>
      <p style="font-size:13px;color:#64748b;">Notice: In compliance with ADR 0001, door access credentials have been withheld until the guest submits the completed Guest Registry.</p>
    </div>
    <div class="footer">
      OceanViewFlats Automated Fulfillment Dispatch
    </div>
  </div>
</body>
</html>
HTML;
    }

    public function renderHostSubject(Reservation $reservation): string
    {
        $lang = strtoupper($this->resolveLanguage($reservation));
        return "DIRECT BOOKING PAID CONFIRMED: Prop {$reservation->propertyId} ({$reservation->guestName}) - [{$lang}]";
    }

    public function buildRegistryUrl(Reservation $reservation, ?string $lang = null): string
    {
        $lang = $lang ?? $this->resolveLanguage($reservation);

        return $this->urlBuilder->buildRegistryUrl($lang, [
            'property' => $reservation->propertyId,
            'check_in' => $reservation->checkIn,
            'check_out' => $reservation->checkOut,
            'code' => $reservation->reservationUid,
        ]);
    }

    private function resolveLanguage(Reservation $reservation): string
    {
        $lang = trim($reservation->lang);

        if (in_array($lang, ['en', 'es', 'fr', 'it', 'de', 'ja'], true)) {
            return $lang;
        }

        $email = strtolower($reservation->guestEmail);
        foreach (['.cl', '.ar', '.co', '.mx', '.es', '.pe', '.ec', '.uy'] as $tld) {
            if (str_ends_with($email, $tld) || str_contains($email, $tld . '/')) {
                return 'es';
            }
        }

        return 'en';
    }

    private function calculateNights(string $checkIn, string $checkOut): int
    {
        try {
            $in = new DateTimeImmutable($checkIn);
            $out = new DateTimeImmutable($checkOut);
            return max(1, (int)$in->diff($out)->days);
        } catch (\Throwable) {
            return 1;
        }
    }
}
