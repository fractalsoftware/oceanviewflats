<?php

declare(strict_types=1);

namespace OceanViewFlats\Infrastructure\Reservation;

use Throwable;
use OceanViewFlats\Domain\Fulfillment\BookingFulfillmentInterface;
use OceanViewFlats\Domain\Fulfillment\CancellationEmailRendererInterface;
use OceanViewFlats\Domain\Fulfillment\EmailSenderInterface;
use OceanViewFlats\Domain\Reservation\Event\ReservationCancelledEvent;
use OceanViewFlats\Domain\Reservation\Event\ReservationConfirmedEvent;
use OceanViewFlats\Domain\Reservation\Port\AuditPort;
use OceanViewFlats\Domain\Reservation\Port\LifecycleEventPublisherPort;

/**
 * Production transactional lifecycle event publisher adapter.
 * Dispatches confirmation emails via booking fulfillment and cancellation notices via email sender.
 */
final class TransactionalLifecycleEventPublisherAdapter implements LifecycleEventPublisherPort
{
    public function __construct(
        private readonly ?CancellationEmailRendererInterface $cancellationRenderer = null,
        private readonly ?EmailSenderInterface $emailSender = null,
        private readonly ?BookingFulfillmentInterface $bookingFulfillment = null,
        private readonly ?AuditPort $auditPort = null
    ) {
    }

    /**
     * @inheritDoc
     */
    public function publishConfirmed(ReservationConfirmedEvent $event): void
    {
        try {
            if ($event->sendConfirmationEmail && $this->bookingFulfillment !== null) {
                $reservation = $event->reservation->registryCompleted
                    ? $event->reservation
                    : $event->reservation->withDoorCode(null);

                $extra = array_filter([
                    'payment_id' => $reservation->mercadopagoPaymentId,
                    'payment_status' => $reservation->paymentStatus,
                ], static fn(?string $v): bool => $v !== null);

                $result = $this->bookingFulfillment->fulfillConfirmation($reservation, $extra);
                if (!$result->isSuccess() && $this->auditPort !== null) {
                    $this->auditPort->record(
                        action: 'booking_fulfillment_failed',
                        entityType: 'reservation',
                        entityId: $reservation->reservationUid,
                        payloadBefore: null,
                        payloadAfter: [
                            'errors' => $result->errors,
                            'guest_email_sent' => $result->isGuestEmailSent,
                            'host_email_sent' => $result->isHostEmailSent,
                            'spreadsheet_synced' => $result->isSpreadsheetSynced,
                        ],
                        actor: $event->actorContext
                    );
                }
            }
        } catch (Throwable) {
            // Best effort post-commit notification resilience
        }
    }

    /**
     * @inheritDoc
     */
    public function publishCancelled(ReservationCancelledEvent $event): void
    {
        try {
            if ($event->cancellationResult->emailSent && $this->cancellationRenderer !== null && $this->emailSender !== null) {
                $reservation = $event->reservation;
                $refundAmount = $event->cancellationResult->refundAmountCop;
                $policyRetention = $event->cancellationResult->policyRetentionCop;

                $trigger = match ($event->actorContext->source) {
                    'webhook' => 'mercadopago_webhook',
                    'admin' => 'admin_cancellation',
                    default => $event->actorContext->source,
                };

                try {
                    $subject = $this->cancellationRenderer->renderGuestSubject($reservation);
                    $htmlBody = $this->cancellationRenderer->renderGuestCancellationHtml(
                        $reservation,
                        $refundAmount,
                        $policyRetention
                    );

                    $sent = $this->emailSender->send($reservation->guestEmail, $subject, $htmlBody);
                    if ($sent) {
                        $this->auditPort?->record(
                            action: 'cancellation_email_sent',
                            entityType: 'reservation',
                            entityId: $reservation->reservationUid,
                            payloadBefore: null,
                            payloadAfter: [
                                'recipient' => $reservation->guestEmail,
                                'refund_amount' => $refundAmount,
                                'policy_retention' => $policyRetention,
                                'trigger' => $trigger,
                            ],
                            actor: $event->actorContext
                        );
                    } else {
                        $this->auditPort?->record(
                            action: 'email_delivery_failed',
                            entityType: 'reservation',
                            entityId: $reservation->reservationUid,
                            payloadBefore: null,
                            payloadAfter: [
                                'recipient' => $reservation->guestEmail,
                                'error' => 'Email sender returned false',
                                'trigger' => $trigger,
                            ],
                            actor: $event->actorContext
                        );
                    }
                } catch (Throwable $e) {
                    $this->auditPort?->record(
                        action: 'email_delivery_failed',
                        entityType: 'reservation',
                        entityId: $reservation->reservationUid,
                        payloadBefore: null,
                        payloadAfter: [
                            'recipient' => $reservation->guestEmail,
                            'error' => $e->getMessage(),
                            'trigger' => $trigger,
                        ],
                        actor: $event->actorContext
                    );
                }
            }
        } catch (Throwable) {
            // Best effort post-commit notification resilience
        }
    }
}
