<?php

declare(strict_types=1);

namespace OceanViewFlats\Domain\Fulfillment;

use OceanViewFlats\Domain\Reservation\Reservation;
use Throwable;

final class BookingFulfillment implements BookingFulfillmentInterface
{
    public function __construct(
        private readonly EmailSenderInterface $emailSender,
        private readonly ConfirmationEmailRendererInterface $renderer,
        private readonly string $hostEmail = 'rentals@oceanviewflats.com'
    ) {}

    public static function createDefault(?string $hostEmail = null, ?EmailSenderInterface $emailSender = null): self
    {
        $resolvedHost = $hostEmail
            ?? $_ENV['RECIPIENT_EMAIL']
            ?? $_SERVER['RECIPIENT_EMAIL']
            ?? getenv('RECIPIENT_EMAIL')
            ?: (defined('RECIPIENT_EMAIL') ? constant('RECIPIENT_EMAIL') : 'rentals@oceanviewflats.com');

        return new self(
            emailSender: $emailSender ?? new PhpMailSender(),
            renderer: new ConfirmationEmailRenderer(),
            hostEmail: (string)$resolvedHost
        );
    }

    public function fulfillConfirmation(Reservation $reservation, array $extra = []): FulfillmentResult
    {
        $errors = [];

        // 1. Localized Reservation Confirmation Email to Primary Guest
        $guestEmailSent = false;
        try {
            $guestSubject = $this->renderer->renderGuestSubject($reservation);
            $guestHtml = $this->renderer->renderGuestConfirmationHtml($reservation);
            $guestEmailSent = $this->emailSender->send($reservation->guestEmail, $guestSubject, $guestHtml);
            if (!$guestEmailSent) {
                $errors[] = 'Failed to dispatch confirmation email to Primary Guest.';
            }
        } catch (Throwable $e) {
            $errors[] = 'Primary Guest confirmation email error: ' . $e->getMessage();
        }

        // 2. Host Notification Email
        $hostEmailSent = false;
        try {
            $hostSubject = $this->renderer->renderHostSubject($reservation);
            $hostHtml = $this->renderer->renderHostNotificationHtml($reservation);
            $hostEmailSent = $this->emailSender->send($this->hostEmail, $hostSubject, $hostHtml);
            if (!$hostEmailSent) {
                $errors[] = 'Failed to dispatch notification email to Host.';
            }
        } catch (Throwable $e) {
            $errors[] = 'Host notification email error: ' . $e->getMessage();
        }

        return new FulfillmentResult(
            isGuestEmailSent: $guestEmailSent,
            isHostEmailSent: $hostEmailSent,
            errors: $errors
        );
    }
}
