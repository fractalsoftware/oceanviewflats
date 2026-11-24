<?php

declare(strict_types=1);

namespace OceanViewFlats\Domain\Payment;

use JsonSerializable;

/**
 * Immutable typed value object representing authoritative payment state retrieved from gateway.
 */
final class PaymentDetails implements JsonSerializable
{
    /**
     * @param array<int, RefundLineItem> $refunds
     * @param array<string, mixed> $rawResponse
     */
    public function __construct(
        public readonly string $paymentId,
        public readonly string $status,
        public readonly string $statusDetail,
        public readonly string $externalReference,
        public readonly float $transactionAmount,
        public readonly float $totalRefundedAmount,
        public readonly string $paymentMethodId,
        public readonly array $refunds = [],
        public readonly array $rawResponse = [],
        public readonly string $currencyId = 'COP'
    ) {
    }

    public function isApproved(): bool
    {
        return $this->status === 'approved';
    }

    public function isRefunded(): bool
    {
        return $this->status === 'refunded' || (
            $this->totalRefundedAmount >= $this->transactionAmount &&
            $this->transactionAmount > 0.0 &&
            $this->totalRefundedAmount > 0.0
        );
    }

    public function isPartiallyRefunded(): bool
    {
        return $this->status === 'partially_refunded' || (
            $this->totalRefundedAmount > 0.0 &&
            $this->totalRefundedAmount < $this->transactionAmount
        );
    }

    public function isCancelledOrRejected(): bool
    {
        return in_array($this->status, ['cancelled', 'rejected'], true);
    }

    /**
     * @param array<string, mixed> $data
     * @return self
     */
    public static function fromArray(array $data): self
    {
        $refunds = [];
        if (isset($data['refunds']) && is_array($data['refunds'])) {
            foreach ($data['refunds'] as $item) {
                if ($item instanceof RefundLineItem) {
                    $refunds[] = $item;
                } elseif (is_array($item)) {
                    $refunds[] = RefundLineItem::fromArray($item);
                }
            }
        }

        $paymentId = '';
        if (isset($data['id'])) {
            $paymentId = (string) $data['id'];
        } elseif (isset($data['payment_id'])) {
            $paymentId = (string) $data['payment_id'];
        }

        $totalRefunded = 0.0;
        if (isset($data['transaction_amount_refunded'])) {
            $totalRefunded = (float) $data['transaction_amount_refunded'];
        } elseif (isset($data['total_refunded_amount'])) {
            $totalRefunded = (float) $data['total_refunded_amount'];
        } elseif (!empty($refunds)) {
            foreach ($refunds as $refund) {
                if ($refund->status === 'approved') {
                    $totalRefunded += $refund->amount;
                }
            }
        }

        return new self(
            paymentId: $paymentId,
            status: (string) ($data['status'] ?? ''),
            statusDetail: (string) ($data['status_detail'] ?? ''),
            externalReference: (string) ($data['external_reference'] ?? ''),
            transactionAmount: (float) ($data['transaction_amount'] ?? 0.0),
            totalRefundedAmount: $totalRefunded,
            paymentMethodId: (string) ($data['payment_method_id'] ?? ''),
            refunds: $refunds,
            rawResponse: $data,
            currencyId: strtoupper(trim((string) ($data['currency_id'] ?? '')))
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        return [
            'payment_id' => $this->paymentId,
            'status' => $this->status,
            'status_detail' => $this->statusDetail,
            'external_reference' => $this->externalReference,
            'transaction_amount' => $this->transactionAmount,
            'total_refunded_amount' => $this->totalRefundedAmount,
            'payment_method_id' => $this->paymentMethodId,
            'refunds' => array_map(static fn (RefundLineItem $r) => $r->jsonSerialize(), $this->refunds),
        ];
    }
}
