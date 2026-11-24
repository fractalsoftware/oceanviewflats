<?php

declare(strict_types=1);

namespace OceanViewFlats\Domain\Payment;

use JsonException;

/**
 * Production cURL HTTP adapter for Mercado Pago Payment Gateway.
 * Endpoints:
 * - POST /v1/payments (Create payment)
 * - GET  /v1/payments/{id} (Fetch payment details)
 */
final class MercadoPagoPaymentGateway implements PaymentGatewayInterface
{
    /**
     * @param string $accessToken Mercado Pago Bearer token
     * @param string $baseUrl Base API URL
     * @param int $timeoutSeconds Request timeout in seconds
     * @param (callable(string, string, array<int, string>, ?string): array{0: int, 1: string})|null $transport Optional transport for testing
     */
    public function __construct(
        private readonly string $accessToken,
        private readonly string $baseUrl = 'https://api.mercadopago.com',
        private readonly int $timeoutSeconds = 15,
        private readonly mixed $transport = null
    ) {
    }

    /**
     * @inheritDoc
     */
    public function createPayment(PaymentIntent $intent): PaymentGatewayResult
    {
        $cleanToken = trim($this->accessToken);
        if ($cleanToken === '') {
            throw new PaymentGatewayException(
                'Mercado Pago access token is missing or unconfigured',
                500,
                'missing_access_token'
            );
        }

        $url = rtrim($this->baseUrl, '/') . '/v1/payments';
        $payloadArray = $intent->toArray();

        try {
            $jsonPayload = json_encode($payloadArray, JSON_THROW_ON_ERROR);
        } catch (JsonException $e) {
            throw new PaymentGatewayException(
                'Failed to serialize payment intent: ' . $e->getMessage(),
                400,
                'json_encode_error',
                previous: $e
            );
        }

        $headers = [
            'Authorization: Bearer ' . $cleanToken,
            'Content-Type: application/json',
            'Accept: application/json',
            'User-Agent: OceanViewFlats-PaymentGateway/1.0',
        ];

        if ($intent->idempotencyKey !== null && trim($intent->idempotencyKey) !== '') {
            $headers[] = 'X-Idempotency-Key: ' . trim($intent->idempotencyKey);
        }

        [$httpCode, $responseBody] = $this->executeHttpRequest('POST', $url, $headers, $jsonPayload);

        /** @var array<string, mixed> $decoded */
        $decoded = $this->decodeResponseBody($responseBody);

        if ($httpCode >= 200 && $httpCode < 300) {
            return PaymentGatewayResult::fromArray($decoded, $intent);
        }

        $defaultMessage = 'Gateway returned HTTP ' . $httpCode;
        $errorMessage = $this->extractErrorMessage($decoded, $httpCode, $defaultMessage);
        $errorType = 'gateway_error';

        if (isset($decoded['error']) && is_string($decoded['error'])) {
            $errorType = $decoded['error'];
        } elseif (isset($decoded['cause'][0]['code']) && (is_string($decoded['cause'][0]['code']) || is_numeric($decoded['cause'][0]['code']))) {
            $errorType = (string) $decoded['cause'][0]['code'];
        }

        throw new PaymentGatewayException(
            $errorMessage,
            $httpCode,
            $errorType,
            $decoded
        );
    }

    /**
     * @inheritDoc
     */
    public function getPayment(string $paymentId): PaymentDetails
    {
        $cleanToken = trim($this->accessToken);
        if ($cleanToken === '') {
            throw new PaymentGatewayException(
                'Mercado Pago access token is missing or unconfigured',
                500,
                'missing_access_token'
            );
        }

        $cleanPaymentId = trim($paymentId);
        if ($cleanPaymentId === '') {
            throw new PaymentGatewayException(
                'Payment ID cannot be empty',
                400,
                'invalid_payment_id'
            );
        }

        $url = rtrim($this->baseUrl, '/') . '/v1/payments/' . rawurlencode($cleanPaymentId);
        $headers = [
            'Authorization: Bearer ' . $cleanToken,
            'Accept: application/json',
            'User-Agent: OceanViewFlats-PaymentGateway/1.0',
        ];

        [$httpCode, $responseBody] = $this->executeHttpRequest('GET', $url, $headers, null);

        /** @var array<string, mixed> $decoded */
        $decoded = $this->decodeResponseBody($responseBody);

        if ($httpCode >= 200 && $httpCode < 300) {
            return PaymentDetails::fromArray($decoded);
        }

        $defaultMessage = 'Gateway returned HTTP ' . $httpCode;
        $errorMessage = $this->extractErrorMessage($decoded, $httpCode, $defaultMessage);
        $errorType = 'gateway_error';

        if (isset($decoded['error']) && is_string($decoded['error'])) {
            $errorType = $decoded['error'];
        }

        throw new PaymentGatewayException(
            $errorMessage,
            $httpCode,
            $errorType,
            $decoded
        );
    }

    /**
     * Extracts a descriptive error message from a decoded gateway response.
     *
     * @param array<string, mixed> $decoded
     * @param int $httpCode
     * @param string $defaultMessage
     * @return string
     */
    private function extractErrorMessage(array $decoded, int $httpCode, string $defaultMessage): string
    {
        if (isset($decoded['message']) && is_string($decoded['message']) && trim($decoded['message']) !== '') {
            return trim($decoded['message']);
        }

        if (isset($decoded['cause'][0]['description']) && is_string($decoded['cause'][0]['description']) && trim($decoded['cause'][0]['description']) !== '') {
            return trim($decoded['cause'][0]['description']);
        }

        return $defaultMessage !== '' ? $defaultMessage : 'Gateway returned HTTP ' . $httpCode;
    }

    /**
     * @param string $method
     * @param string $url
     * @param array<int, string> $headers
     * @param string|null $body
     * @return array{0: int, 1: string}
     */
    private function executeHttpRequest(string $method, string $url, array $headers, ?string $body): array
    {
        if ($this->transport !== null) {
            /** @var array{0: int, 1: string} $res */
            $res = ($this->transport)($method, $url, $headers, $body);
            return $res;
        }

        $ch = curl_init($url);
        if ($ch === false) {
            throw new PaymentGatewayException('Failed to initialize cURL session', 500, 'curl_init_failed');
        }

        /** @var array<int, mixed> $options */
        $options = [
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => $this->timeoutSeconds,
            CURLOPT_CONNECTTIMEOUT => 5,
        ] + \OceanViewFlats\Domain\Support\CurlSecurityOptions::build($url);

        if ($method === 'POST') {
            $options[CURLOPT_POST] = true;
            $options[CURLOPT_POSTFIELDS] = $body ?? '';
        } elseif ($method === 'GET') {
            $options[CURLOPT_HTTPGET] = true;
        }

        curl_setopt_array($ch, $options);

        /** @var string|false $responseBody */
        $responseBody = curl_exec($ch);
        $curlErrno = curl_errno($ch);
        $curlError = curl_error($ch);
        /** @var int $httpCode */
        $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($responseBody === false || $curlErrno !== 0) {
            throw new PaymentGatewayException(
                'cURL communication failure: ' . ($curlError !== '' ? $curlError : 'Connection error'),
                504,
                'network_timeout',
                ['curl_errno' => $curlErrno, 'curl_error' => $curlError]
            );
        }

        return [$httpCode, $responseBody];
    }

    /**
     * @param string $responseBody
     * @return array<string, mixed>
     */
    private function decodeResponseBody(string $responseBody): array
    {
        try {
            $decoded = json_decode($responseBody, true, 512, JSON_THROW_ON_ERROR);
            if (!is_array($decoded)) {
                throw new PaymentGatewayException(
                    'Invalid JSON payload from gateway',
                    502,
                    'invalid_gateway_response',
                    ['raw' => $responseBody]
                );
            }
            return $decoded;
        } catch (JsonException $e) {
            throw new PaymentGatewayException(
                'Failed to parse gateway response JSON: ' . $e->getMessage(),
                502,
                'json_parse_error',
                ['raw' => $responseBody],
                $e
            );
        }
    }
}
