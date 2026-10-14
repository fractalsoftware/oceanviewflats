<?php

declare(strict_types=1);

namespace OceanViewFlats\Domain\Reservation;

use DateTimeImmutable;
use DateTimeZone;
use InvalidArgumentException;
use OceanViewFlats\Domain\Access\DoorCodeGenerator;
use OceanViewFlats\Domain\Quote\Quote;
use OceanViewFlats\Domain\Quote\QuoteEngine;
use OceanViewFlats\Domain\Quote\QuoteEngineInterface;
use OceanViewFlats\Domain\Reservation\Event\ReservationCancelledEvent;
use OceanViewFlats\Domain\Reservation\Event\ReservationConfirmedEvent;
use OceanViewFlats\Domain\Reservation\Port\AuditPort;
use OceanViewFlats\Domain\Reservation\Port\LifecycleEventPublisherPort;
use OceanViewFlats\Domain\Reservation\Port\PaymentRefundPort;
use OceanViewFlats\Domain\Reservation\Port\ReservationPersistencePort;

/**
 * Authoritative, deep Reservation Lifecycle Engine domain service.
 * Enforces calendar availability, dynamic hold windows, seasonal minimum stays,
 * channel block absorption (ADR 0007), pre-transaction gateway refund dispatch (ADR 0011),
 * resurrection defense against cancelled/concluded bookings (ADR 0009),
 * and statutory access credential withholding (ADR 0001).
 */
final class ReservationLifecycleEngine implements ReservationLifecycleEngineInterface
{
    private readonly ReservationLedgerInterface $ledger;
    private readonly QuoteEngineInterface $quoteEngine;

    public function __construct(
        private readonly ReservationPersistencePort $persistencePort,
        private readonly PaymentRefundPort $paymentRefundPort,
        private readonly LifecycleEventPublisherPort $eventPublisherPort,
        private readonly AuditPort $auditPort,
        ?ReservationLedgerInterface $ledger = null,
        ?QuoteEngineInterface $quoteEngine = null
    ) {
        $this->ledger = $ledger ?? ReservationLedger::createDefault();
        $this->quoteEngine = $quoteEngine ?? QuoteEngine::createDefault();
    }

    /**
     * @inheritDoc
     */
    public function holdDirect(DirectHoldRequest $request): DirectHoldResult
    {
        $this->validateDates($request->checkIn, $request->checkOut);

        // 1. Authoritative Quote & seasonal minimum stay validation (ADR 0004)
        $quote = $this->quoteEngine->quote($request->propertyId, $request->checkIn, $request->checkOut);
        if (!$quote->isValid()) {
            throw ReservationValidationException::minimumStayViolated(
                $quote->minimumStayRequired(),
                $quote->nightsCount()
            );
        }

        // 2. Calendar availability check (ADR 0002 & ADR 0003)
        $conflicts = $this->ledger->getConflictReasons($request->propertyId, $request->checkIn, $request->checkOut);
        if (count($conflicts) > 0) {
            throw ReservationConflictException::forDates(
                $request->propertyId,
                $request->checkIn,
                $request->checkOut,
                implode('; ', $conflicts)
            );
        }

        // 3. Compute hold duration and expiration (ADR 0003)
        $tz = new DateTimeZone('America/Bogota');
        $now = new DateTimeImmutable('now', $tz);
        $payment = $request->payment;
        $paymentMethodId = $payment !== null ? $payment->paymentMethodId : $request->paymentMethodId;
        $mercadopagoPreferenceId = $payment?->mercadopagoPreferenceId;
        $mercadopagoPaymentId = $payment?->mercadopagoPaymentId;
        $paymentStatus = $payment?->paymentStatus;
        $paymentDetail = $payment?->paymentDetail;

        $isEfecty = $paymentMethodId !== null && strtolower($paymentMethodId) === 'efecty';

        if ($isEfecty) {
            $holdExpiresAt = $now->modify('+' . Reservation::DEFAULT_VOUCHER_HOLD_HOURS . ' hours');
        } else {
            $minutes = $request->customHoldMinutes ?? Reservation::DEFAULT_STANDARD_HOLD_MINUTES;
            $holdExpiresAt = $now->modify('+' . $minutes . ' minutes');
        }

        $uid = $request->reservationUid ?? ('ovf_' . bin2hex(random_bytes(6)));
        $doorCode = DoorCodeGenerator::generateRandom();

        $reservation = Reservation::create(
            reservationUid: $uid,
            propertyId: $request->propertyId,
            guestName: $request->primaryGuest->name,
            guestEmail: $request->primaryGuest->email,
            guestPhone: $request->primaryGuest->phone,
            checkIn: $request->checkIn,
            checkOut: $request->checkOut,
            totalPrice: $quote->totalCop(),
            status: ReservationStatus::PENDING_PAYMENT,
            paymentMethodId: $paymentMethodId,
            mercadopagoPreferenceId: $mercadopagoPreferenceId,
            mercadopagoPaymentId: $mercadopagoPaymentId,
            paymentStatus: $paymentStatus,
            paymentDetail: $paymentDetail,
            lang: $request->primaryGuest->lang,
            createdAt: $now,
            registryCompleted: false,
            doorCode: $doorCode,
            source: 'web',
            notes: $request->notes
        );

        // 4. Concurrency-safe atomic hold persistence
        $persisted = $this->persistencePort->holdAtomic($reservation, $now);

        // 5. Immutable audit logging
        $this->auditPort->record(
            action: 'direct_hold_created',
            entityType: 'reservation',
            entityId: $persisted->reservationUid,
            payloadBefore: null,
            payloadAfter: [
                'property_id' => $persisted->propertyId,
                'check_in' => $persisted->checkIn,
                'check_out' => $persisted->checkOut,
                'total_price' => $persisted->totalPrice,
                'payment_method_id' => $persisted->paymentMethodId,
                'mercadopago_payment_id' => $persisted->mercadopagoPaymentId,
                'payment_status' => $persisted->paymentStatus,
                'hold_expires_at' => $holdExpiresAt->format('c'),
            ],
            actor: $request->actor ?? ActorContext::guest()
        );

        return new DirectHoldResult(
            reservation: $persisted,
            quote: $quote,
            holdExpiresAt: $holdExpiresAt
        );
    }

    /**
     * @inheritDoc
     */
    public function confirmOrRecord(ReservationDraft $draft): ReservationResult
    {
        $this->validateDates($draft->checkIn, $draft->checkOut);

        // 1. Existing reservation retrieval & Resurrection Defense (ADR 0009 & ADR 0011)
        $existing = null;
        if ($draft->reservationUid !== null) {
            $existing = $this->persistencePort->getReservation($draft->reservationUid);
            if ($existing !== null) {
                if ($existing->status === ReservationStatus::CANCELLED) {
                    throw InvalidReservationStateException::resurrectionRejected(
                        $existing->reservationUid,
                        'cancelled',
                        'confirm'
                    );
                }
                if ($existing->isConcluded()) {
                    throw InvalidReservationStateException::resurrectionRejected(
                        $existing->reservationUid,
                        'concluded',
                        'confirm'
                    );
                }
            }
        }

        // 2. Authoritative Quote Calculation and Price Resolution
        $quote = null;
        if ($draft->isDirect()) {
            if ($existing === null) {
                $quote = $this->quoteEngine->quote($draft->propertyId, $draft->checkIn, $draft->checkOut);
                if (!$quote->isValid()) {
                    throw ReservationValidationException::minimumStayViolated(
                        $quote->minimumStayRequired(),
                        $quote->nightsCount()
                    );
                }
                $totalPrice = $draft->totalPrice ?? $quote->totalCop();
            } else {
                $totalPrice = $draft->totalPrice ?? $existing->totalPrice;
                try {
                    $quote = $this->quoteEngine->quote($draft->propertyId, $draft->checkIn, $draft->checkOut);
                } catch (\Throwable) {
                    $quote = null;
                }
            }
        } elseif ($draft->totalPrice !== null) {
            $totalPrice = $draft->totalPrice;
        } else {
            try {
                $quote = $this->quoteEngine->quote($draft->propertyId, $draft->checkIn, $draft->checkOut);
                $totalPrice = $quote->totalCop();
            } catch (\Throwable) {
                $totalPrice = 0.0;
            }
        }

        // 3. Channel Block Absorption and Calendar Availability Check (ADR 0007)
        $wasChannelBlockAbsorbed = false;
        $absorbedChannelBlockUid = null;
        $absorbingSource = $draft->isAirbnb() ? 'airbnb' : null;

        if ($existing === null) {
            if ($draft->isAirbnb()) {
                if ($draft->channelBlockUid !== null) {
                    $wasChannelBlockAbsorbed = true;
                    $absorbedChannelBlockUid = $draft->channelBlockUid;
                } else {
                    $channelConflict = $this->ledger->findChannelConflict(
                        $draft->propertyId,
                        $draft->checkIn,
                        $draft->checkOut,
                        absorbingSource: null
                    );
                    if ($channelConflict !== null && strtolower($channelConflict->source) === 'airbnb') {
                        $wasChannelBlockAbsorbed = true;
                        $absorbedChannelBlockUid = $channelConflict->summary;
                    }
                }
            }

            if (!$this->ledger->isAvailable($draft->propertyId, $draft->checkIn, $draft->checkOut, null, $absorbingSource)) {
                $conflicts = $this->ledger->getConflictReasons(
                    propertyId: $draft->propertyId,
                    checkIn: $draft->checkIn,
                    checkOut: $draft->checkOut,
                    absorbingSource: $absorbingSource
                );
                throw ReservationConflictException::forDates(
                    $draft->propertyId,
                    $draft->checkIn,
                    $draft->checkOut,
                    !empty($conflicts) ? implode('; ', $conflicts) : 'with an existing reservation or channel block.'
                );
            }
        } else {
            // Disregard self-conflict when transitioning an existing reservation
            $otherConflict = $this->ledger->findReservationConflict(
                $draft->propertyId,
                $draft->checkIn,
                $draft->checkOut
            );
            if ($otherConflict !== null && $otherConflict->reservationUid !== $existing->reservationUid) {
                throw ReservationConflictException::forDates(
                    $draft->propertyId,
                    $draft->checkIn,
                    $draft->checkOut,
                    sprintf('Dates overlap active direct reservation %s (%s to %s)', $otherConflict->reservationUid, $otherConflict->checkIn, $otherConflict->checkOut)
                );
            }
        }

        // 4. UID and Smart Lock Access PIN Generation (ADR 0001)
        if ($draft->reservationUid !== null) {
            $uid = $draft->reservationUid;
        } elseif ($draft->isAirbnb()) {
            $uid = 'res-abnb-' . bin2hex(random_bytes(4));
        } elseif ($draft->isManual()) {
            $uid = 'res-man-' . bin2hex(random_bytes(6));
        } else {
            $uid = 'ovf_' . bin2hex(random_bytes(6));
        }

        $doorCode = $existing->doorCode ?? DoorCodeGenerator::generateRandom();
        $registryCompleted = $draft->preMarkRegistry || ($existing->registryCompleted ?? false);
        $registryCompletedAt = $registryCompleted ? ($existing->registryCompletedAt ?? new DateTimeImmutable()) : null;

        $tz = new DateTimeZone('America/Bogota');
        $now = new DateTimeImmutable('now', $tz);
        $payment = $draft->payment ?? DraftPaymentDetails::empty();

        $maxGuests = $draft->maxGuests !== Reservation::DEFAULT_MAX_GUESTS
            ? $draft->maxGuests
            : ($existing->maxGuests ?? $draft->maxGuests);

        $reservation = Reservation::create(
            reservationUid: $uid,
            propertyId: $draft->propertyId,
            guestName: $draft->primaryGuest->name,
            guestEmail: $draft->primaryGuest->email,
            guestPhone: $draft->primaryGuest->phone,
            checkIn: $draft->checkIn,
            checkOut: $draft->checkOut,
            totalPrice: $totalPrice,
            status: ReservationStatus::CONFIRMED,
            paymentMethodId: $payment->paymentMethodId ?? $existing?->paymentMethodId,
            id: $existing?->id,
            mercadopagoPreferenceId: $payment->mercadopagoPreferenceId ?? $existing?->mercadopagoPreferenceId,
            mercadopagoPaymentId: $payment->mercadopagoPaymentId ?? $existing?->mercadopagoPaymentId,
            paymentStatus: $payment->paymentStatus ?? $existing->paymentStatus ?? 'approved',
            paymentDetail: $payment->paymentDetail ?? $existing?->paymentDetail,
            lang: $draft->primaryGuest->lang,
            createdAt: $existing->createdAt ?? $now,
            updatedAt: $now,
            registryCompleted: $registryCompleted,
            registryCompletedAt: $registryCompletedAt,
            doorCode: $doorCode,
            source: $draft->source,
            notes: $draft->notes ?? $existing?->notes,
            refundedAmount: $existing->refundedAmount ?? 0.0,
            externalConfirmationCode: $draft->externalConfirmationCode ?? $existing?->externalConfirmationCode,
            channelBlockUid: $absorbedChannelBlockUid ?? $draft->channelBlockUid ?? $existing?->channelBlockUid,
            maxGuests: $maxGuests
        );

        // 5. Transactional Persistence and Audit Logging
        $saved = $this->persistencePort->executeInTransaction(function () use ($reservation, $draft, $existing) {
            $saved = $this->persistencePort->save($reservation);

            $action = match (true) {
                $draft->actor?->source === 'webhook' => 'payment_approved_webhook',
                $draft->isAirbnb() => 'airbnb_reservation_created',
                $draft->isManual() => 'manual_reservation_created',
                default => 'reservation_confirmed',
            };

            $this->auditPort->record(
                action: $action,
                entityType: 'reservation',
                entityId: $saved->reservationUid,
                payloadBefore: $existing?->toArray(),
                payloadAfter: $saved->toArray(),
                actor: $draft->actor
            );

            return $saved;
        });

        // 6. Post-Commit Domain Event Dispatch
        $this->eventPublisherPort->publishConfirmed(
            new ReservationConfirmedEvent(
                reservation: $saved,
                actorContext: $draft->actor ?? ActorContext::system(),
                wasChannelBlockAbsorbed: $wasChannelBlockAbsorbed,
                sendConfirmationEmail: $draft->sendConfirmationEmail
            )
        );

        return new ReservationResult(
            reservation: $saved,
            quote: $quote,
            wasChannelBlockAbsorbed: $wasChannelBlockAbsorbed,
            absorbedChannelBlockUid: $absorbedChannelBlockUid,
            accessPinAllocated: true,
            accessPinReleasedToGuest: $saved->registryCompleted,
            doorCode: $doorCode
        );
    }

    /**
     * @inheritDoc
     */
    public function previewCancellation(string $reservationUid): CancellationPreview
    {
        $reservation = $this->persistencePort->getReservation($reservationUid);
        if ($reservation === null) {
            throw new InvalidArgumentException(sprintf('Reservation not found: %s', $reservationUid));
        }

        // Resurrection & Terminal Status Defense (ADR 0009 & ADR 0011)
        if ($reservation->status === ReservationStatus::CANCELLED) {
            throw InvalidReservationStateException::alreadyCancelled($reservationUid, 'preview_cancellation');
        }
        if ($reservation->isConcluded()) {
            throw InvalidReservationStateException::alreadyConcluded($reservationUid, 'preview_cancellation');
        }

        $totalPrice = $reservation->totalPrice;
        $alreadyRefunded = $reservation->refundedAmount;
        $refundableBalance = max(0.0, round($totalPrice - $alreadyRefunded, 2));
        $isOnlinePayment = !empty($reservation->mercadopagoPaymentId);
        $mpPaymentId = $isOnlinePayment ? $reservation->mercadopagoPaymentId : null;

        // Days until check-in calculation
        $tz = new DateTimeZone('America/Bogota');
        $today = new DateTimeImmutable('today', $tz);
        $checkInDate = new DateTimeImmutable($reservation->checkIn, $tz);
        $diff = $today->diff($checkInDate);
        $daysUntilCheckIn = (int) $diff->format('%r%a');

        // Suggested policy retention & max refund calculation
        if ($daysUntilCheckIn >= 14) {
            $suggestedPolicyRetention = 0.0;
            $suggestedMaxRefund = $refundableBalance;
        } elseif ($daysUntilCheckIn >= 7) {
            $suggestedPolicyRetention = round($totalPrice * 0.50, 2);
            $suggestedMaxRefund = min($refundableBalance, max(0.0, round($totalPrice - $suggestedPolicyRetention - $alreadyRefunded, 2)));
        } else {
            $suggestedPolicyRetention = $totalPrice;
            $suggestedMaxRefund = 0.0;
        }

        return new CancellationPreview(
            reservationUid: $reservationUid,
            totalPrice: $totalPrice,
            alreadyRefundedCop: $alreadyRefunded,
            refundableBalanceCop: $refundableBalance,
            daysUntilCheckIn: $daysUntilCheckIn,
            suggestedPolicyRetentionCop: $suggestedPolicyRetention,
            suggestedMaxRefundCop: $suggestedMaxRefund,
            isOnlinePayment: $isOnlinePayment,
            mercadopagoPaymentId: $mpPaymentId,
            reservationStatus: $reservation->status
        );
    }

    /**
     * @inheritDoc
     */
    public function cancel(string $reservationUid, CancellationRequest $request): CancellationResult
    {
        $reason = trim($request->reason);
        if ($reason === '') {
            throw new InvalidArgumentException('Cancellation reason cannot be empty');
        }

        $reservation = $this->persistencePort->getReservation($reservationUid);
        if ($reservation === null) {
            throw new InvalidArgumentException(sprintf('Reservation not found: %s', $reservationUid));
        }

        // 1. Resurrection & Terminal Status Defense (ADR 0009 & ADR 0011)
        if ($reservation->status === ReservationStatus::CANCELLED) {
            throw InvalidReservationStateException::alreadyCancelled($reservationUid, 'cancel');
        }
        if ($reservation->isConcluded()) {
            throw InvalidReservationStateException::alreadyConcluded($reservationUid, 'cancel');
        }

        // 2. Refund Amount Resolution and Balance Validation
        $totalPrice = $reservation->totalPrice;
        $alreadyRefunded = $reservation->refundedAmount;
        $refundableBalance = max(0.0, round($totalPrice - $alreadyRefunded, 2));

        if ($request->refundInstruction->isNone()) {
            $refundAmount = 0.0;
        } elseif ($request->refundInstruction->isFull()) {
            $refundAmount = $refundableBalance;
        } elseif ($request->refundInstruction->isPartial()) {
            $partial = (float) $request->refundInstruction->amountCop;
            if ($partial > $refundableBalance) {
                throw ExcessiveRefundException::forAmount($reservationUid, $partial, $refundableBalance);
            }
            $refundAmount = round($partial, 2);
        } else {
            $refundAmount = 0.0;
        }

        // 3. Pre-Transaction Gateway Refund Dispatch (CRITICAL INVARIANT per ADR 0011)
        // Dispatches remote HTTP call BEFORE opening database transaction to prevent row lock contention.
        $isOnlinePayment = !empty($reservation->mercadopagoPaymentId);
        $mpPaymentId = $isOnlinePayment ? $reservation->mercadopagoPaymentId : null;
        $receipt = null;
        $mpRefundId = null;

        $dispatchGatewayRefund = $request->dispatchGatewayRefund;
        if ($isOnlinePayment && $refundAmount > 0.0 && $dispatchGatewayRefund) {
            $idempotencyKey = $request->idempotencyKey ?? sprintf('ref_%s_%d', $reservationUid, (int) $refundAmount);
            $receipt = $this->paymentRefundPort->issueRefund((string) $mpPaymentId, $refundAmount, $idempotencyKey);
            $mpRefundId = $receipt->refundId;
        } elseif (!$dispatchGatewayRefund && $request->externalRefundId !== null) {
            $mpRefundId = $request->externalRefundId;
        }

        // 4. Database Transaction for Local State Mutation
        $tz = new DateTimeZone('America/Bogota');
        $now = new DateTimeImmutable('now', $tz);

        /** @var array{0: Reservation, 1: int|null} $transactionResult */
        $transactionResult = $this->persistencePort->executeInTransaction(function () use (
            $reservation,
            $reservationUid,
            $reason,
            $refundAmount,
            $alreadyRefunded,
            $totalPrice,
            $isOnlinePayment,
            $mpPaymentId,
            $mpRefundId,
            $request,
            $now
        ): array {
            $dateStr = $now->format('Y-m-d H:i');
            $cancelNote = sprintf('[Cancelled %s] %s', $dateStr, $reason);
            if ($refundAmount > 0.0) {
                $cancelNote .= sprintf(
                    ' (Refund: COP %s, type: %s)',
                    number_format($refundAmount, 2, '.', ''),
                    $request->refundInstruction->type
                );
            } else {
                $cancelNote .= ' (Policy retention: No refund)';
            }

            $existingNotes = $reservation->notes !== null ? trim($reservation->notes) : '';
            $updatedNotes = $existingNotes !== '' ? $existingNotes . "\n" . $cancelNote : $cancelNote;

            $newRefundedAmount = round($alreadyRefunded + $refundAmount, 2);
            $newPaymentStatus = $reservation->paymentStatus ?? 'pending_payment';
            if ($newRefundedAmount >= $totalPrice && $totalPrice > 0.0) {
                $newPaymentStatus = 'refunded';
            } elseif ($newRefundedAmount > 0.0) {
                $newPaymentStatus = 'partially_refunded';
            }

            $cancelledReservation = $reservation->withRefund(
                additionalRefundAmount: $refundAmount,
                notes: $updatedNotes,
                status: ReservationStatus::CANCELLED,
                paymentStatus: $newPaymentStatus,
                updatedAt: $now
            );

            $saved = $this->persistencePort->save($cancelledReservation);

            $isWebhook = $request->actor?->source === 'webhook';
            $source = match (true) {
                $isWebhook => 'mercadopago_webhook',
                $isOnlinePayment => 'admin_pms',
                default => 'admin_manual',
            };
            if ($refundAmount > 0.0) {
                $this->persistencePort->recordRefund([
                    'reservation_uid' => $reservationUid,
                    'mercadopago_refund_id' => $mpRefundId,
                    'mercadopago_payment_id' => $mpPaymentId ?? 'offline',
                    'amount' => $refundAmount,
                    'status' => 'approved',
                    'reason' => $reason,
                    'source' => $source,
                    'admin_user_id' => $request->actor?->adminUserId,
                ]);
            }

            $auditAction = $isWebhook ? 'refund_cancellation' : 'reservation_cancelled';

            $auditLogId = $this->auditPort->record(
                action: $auditAction,
                entityType: 'reservation',
                entityId: $reservationUid,
                payloadBefore: [
                    'status' => $reservation->status->value,
                    'payment_status' => $reservation->paymentStatus,
                    'refunded_amount' => $alreadyRefunded,
                ],
                payloadAfter: [
                    'status' => 'cancelled',
                    'reason' => $reason,
                    'refund_type' => $request->refundInstruction->type,
                    'refund_amount' => $refundAmount,
                    'new_refunded_amount' => $newRefundedAmount,
                ],
                actor: $request->actor
            );

            if ($refundAmount > 0.0) {
                $this->auditPort->record(
                    action: 'refund_issued',
                    entityType: 'reservation',
                    entityId: $reservationUid,
                    payloadBefore: [
                        'refunded_amount' => $alreadyRefunded,
                    ],
                    payloadAfter: [
                        'refunded_amount' => $newRefundedAmount,
                        'refund_amount' => $refundAmount,
                        'mercadopago_refund_id' => $mpRefundId,
                        'refund_type' => $request->refundInstruction->type,
                        'source' => $source,
                    ],
                    actor: $request->actor
                );
            }

            return [$saved, $auditLogId];
        });

        [$savedReservation, $auditLogId] = $transactionResult;
        $policyRetention = max(0.0, round($totalPrice - ($alreadyRefunded + $refundAmount), 2));

        $cancellationResult = new CancellationResult(
            reservation: $savedReservation,
            refundReceipt: $receipt,
            refundAmountCop: $refundAmount,
            policyRetentionCop: $policyRetention,
            emailSent: $request->sendCancellationEmail,
            auditLogId: $auditLogId
        );

        // 5. Post-Commit Domain Event Dispatch
        $this->eventPublisherPort->publishCancelled(
            new ReservationCancelledEvent(
                reservation: $savedReservation,
                actorContext: $request->actor ?? ActorContext::system(),
                cancellationResult: $cancellationResult
            )
        );

        return $cancellationResult;
    }

    private function validateDates(string $checkIn, string $checkOut): void
    {
        $inTime = strtotime($checkIn);
        $outTime = strtotime($checkOut);

        if ($inTime === false || $outTime === false || $checkIn >= $checkOut) {
            throw ReservationValidationException::invalidDates(
                sprintf('Check-out date (%s) must be strictly after check-in date (%s)', $checkOut, $checkIn)
            );
        }
    }
}
