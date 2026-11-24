<?php

declare(strict_types=1);

namespace OceanViewFlats\Admin\Service;

use JsonException;

/**
 * Production cURL HTTP client for Mercado Pago Refund API.
 * Endpoint: POST /v1/payments/{payment_id}/refunds
 */
final class MercadoPagoRefundClient implements MercadoPagoRefundClientInterface
{
    public function __construct(
        private readonly string $accessToken,
        private readonly string $baseUrl = 'https://api.mercadopago.com',
        private readonly int $timeoutSeconds = 15
    ) {
    }

    /**
     * @inheritDoc
     */
    public function refundPayment(string $paymentId, ?float $amount, string $idempotencyKey): array
    {
        $cleanPaymentId = trim($paymentId);
        if ($cleanPaymentId === '') {
            throw new MercadoPagoRefundException('Payment ID cannot be empty', 400, 'invalid_payment_id');
        }

        $cleanIdempotencyKey = trim($idempotencyKey);
        if ($cleanIdempotencyKey === '') {
            throw new MercadoPagoRefundException('Idempotency key cannot be empty', 400, 'missing_idempotency_key');
        }

        $url = rtrim($this->baseUrl, '/') . '/v1/payments/' . rawurlencode($cleanPaymentId) . '/refunds';

        $payload = [];
        if ($amount !== null && $amount > 0) {
            $payload['amount'] = round($amount, 2);
        }

        try {
            $jsonPayload = !empty($payload) ? json_encode($payload, JSON_THROW_ON_ERROR) : '{}';
        } catch (JsonException $e) {
            throw new MercadoPagoRefundException('Failed to serialize refund payload: ' . $e->getMessage(), 400, 'json_encode_error', previous: $e);
        }

        $headers = [
            'Authorization: Bearer ' . $this->accessToken,
            'X-Idempotency-Key: ' . $cleanIdempotencyKey,
            'Content-Type: application/json',
            'Accept: application/json',
            'User-Agent: OceanViewFlats-PMS/1.0',
        ];

        $ch = curl_init($url);
        if ($ch === false) {
            throw new MercadoPagoRefundException('Failed to initialize cURL session', 500, 'curl_init_failed');
        }

        $curlOptions = [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $jsonPayload,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => $this->timeoutSeconds,
            CURLOPT_CONNECTTIMEOUT => 5,
        ] + \OceanViewFlats\Domain\Support\CurlSecurityOptions::build($url);
        curl_setopt_array($ch, $curlOptions);

        /** @var string|false $responseBody */
        $responseBody = curl_exec($ch);
        $curlErrno = curl_errno($ch);
        $curlError = curl_error($ch);
        /** @var int $httpCode */
        $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($responseBody === false || $curlErrno !== 0) {
            throw new MercadoPagoRefundException(
                'cURL communication failure: ' . ($curlError !== '' ? $curlError : 'Connection error'),
                504,
                'network_timeout',
                ['curl_errno' => $curlErrno, 'curl_error' => $curlError]
            );
        }

        /** @var array<string, mixed>|null $decoded */
        $decoded = null;
        try {
            $parsed = json_decode($responseBody, true, 512, JSON_THROW_ON_ERROR);
            if (is_array($parsed)) {
                /** @var array<string, mixed> $decoded */
                $decoded = $parsed;
            }
        } catch (JsonException) {
            $decoded = ['raw' => $responseBody];
        }

        if ($httpCode >= 200 && $httpCode < 300 && is_array($decoded) && isset($decoded['id'])) {
            return [
                'id' => $decoded['id'],
                'payment_id' => $decoded['payment_id'] ?? $cleanPaymentId,
                'amount' => (float) ($decoded['amount'] ?? ($amount ?? 0.0)),
                'status' => (string) ($decoded['status'] ?? 'approved'),
                'date_created' => (string) ($decoded['date_created'] ?? date('c')),
            ];
        }

        // Handle error responses
        $errorMessage = 'Gateway returned HTTP ' . $httpCode;
        $errorCode = null;

        if (is_array($decoded)) {
            if (isset($decoded['message']) && is_string($decoded['message'])) {
                $errorMessage = $decoded['message'];
            }
            if (isset($decoded['error']) && is_string($decoded['error'])) {
                $errorCode = $decoded['error'];
            }
        }

        throw new MercadoPagoRefundException(
            $errorMessage,
            $httpCode,
            $errorCode,
            $decoded ?? []
        );
    }
}
