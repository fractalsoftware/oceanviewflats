<?php

declare(strict_types=1);

namespace OceanViewFlats\Tests\Unit\Domain\Payment;

use OceanViewFlats\Domain\Payment\MercadoPagoPaymentGateway;
use OceanViewFlats\Domain\Payment\PaymentDetails;
use OceanViewFlats\Domain\Payment\PaymentGatewayException;
use OceanViewFlats\Domain\Payment\PaymentGatewayResult;
use OceanViewFlats\Domain\Payment\PaymentIntent;
use OceanViewFlats\Domain\Payment\RefundLineItem;
use PHPUnit\Framework\TestCase;

final class MercadoPagoPaymentGatewayTest extends TestCase
{
    public function testPaymentIntentToArrayFormattingForCreditCard(): void
    {
        $intent = new PaymentIntent(
            reservationUid: 'ovf_cc_12345',
            transactionAmount: 1850000.50,
            paymentMethodId: 'visa',
            payerEmail: 'guest@example.com',
            token: 'card_tok_998877',
            installments: 3,
            issuerId: '25',
            identificationType: 'CC',
            identificationNumber: '1020304050',
            description: 'OceanViewFlats Reservation ovf_cc_12345',
            notificationUrl: 'https://example.com/api/mercadopago-webhook.php',
            idempotencyKey: 'idem-cc-12345'
        );

        $payload = $intent->toArray();

        $this->assertSame(1850000.50, $payload['transaction_amount']);
        $this->assertSame('visa', $payload['payment_method_id']);
        $this->assertSame('card_tok_998877', $payload['token']);
        $this->assertSame(3, $payload['installments']);
        $this->assertSame('25', $payload['issuer_id']);
        $this->assertSame('ovf_cc_12345', $payload['external_reference']);
        $this->assertSame('guest@example.com', $payload['payer']['email']);
        $this->assertSame('CC', $payload['payer']['identification']['type']);
        $this->assertSame('1020304050', $payload['payer']['identification']['number']);
        $this->assertSame('https://example.com/api/mercadopago-webhook.php', $payload['notification_url']);
        $this->assertArrayNotHasKey('transaction_details', $payload);
    }

    public function testPaymentIntentToArrayFormattingForPse(): void
    {
        $intent = new PaymentIntent(
            reservationUid: 'ovf_pse_67890',
            transactionAmount: 920000.0,
            paymentMethodId: 'pse',
            payerEmail: 'pse.guest@example.com',
            identificationType: 'NIT',
            identificationNumber: '900123456',
            financialInstitution: '1077',
            description: 'OceanViewFlats PSE Booking',
            ipAddress: '190.145.20.10',
            payerEntityType: 'individual'
        );

        $payload = $intent->toArray();

        $this->assertSame(920000.0, $payload['transaction_amount']);
        $this->assertSame('pse', $payload['payment_method_id']);
        $this->assertSame('ovf_pse_67890', $payload['external_reference']);
        $this->assertSame('pse.guest@example.com', $payload['payer']['email']);
        $this->assertSame('NIT', $payload['payer']['identification']['type']);
        $this->assertSame('900123456', $payload['payer']['identification']['number']);
        $this->assertSame('individual', $payload['payer']['entity_type']);
        $this->assertSame('1077', $payload['transaction_details']['financial_institution']);
        $this->assertSame('190.145.20.10', $payload['additional_info']['ip_address']);
        $this->assertArrayNotHasKey('token', $payload);
    }

    public function testCreatePaymentParsesApprovedCardResponse(): void
    {
        $intent = new PaymentIntent(
            reservationUid: 'ovf_approved_1',
            transactionAmount: 1250000.0,
            paymentMethodId: 'master',
            payerEmail: 'approved@example.com',
            token: 'card_tok_111',
            idempotencyKey: 'idem-app-1'
        );

        $mockResponseBody = json_encode([
            'id' => 1234567890,
            'status' => 'approved',
            'status_detail' => 'accredited',
            'payment_method_id' => 'master',
            'transaction_amount' => 1250000.0,
            'external_reference' => 'ovf_approved_1',
            'date_created' => '2026-09-29T14:00:00.000Z',
        ], JSON_THROW_ON_ERROR);

        $recordedRequest = [];
        $transport = function (string $method, string $url, array $headers, ?string $body) use (&$recordedRequest, $mockResponseBody): array {
            $recordedRequest = [
                'method' => $method,
                'url' => $url,
                'headers' => $headers,
                'body' => $body,
            ];
            return [201, $mockResponseBody];
        };

        $gateway = new MercadoPagoPaymentGateway('TEST_TOKEN_XYZ', 'https://api.mercadopago.com', 15, $transport);
        $result = $gateway->createPayment($intent);

        $this->assertSame('POST', $recordedRequest['method']);
        $this->assertSame('https://api.mercadopago.com/v1/payments', $recordedRequest['url']);
        $this->assertIsArray($recordedRequest['headers']);
        $this->assertContains('Authorization: Bearer TEST_TOKEN_XYZ', $recordedRequest['headers']);
        $this->assertContains('X-Idempotency-Key: idem-app-1', $recordedRequest['headers']);

        $this->assertTrue($result->success);
        $this->assertTrue($result->isApproved());
        $this->assertFalse($result->isPending());
        $this->assertFalse($result->isRejected());
        $this->assertSame('1234567890', $result->paymentId);
        $this->assertSame('approved', $result->status);
        $this->assertSame('accredited', $result->statusDetail);
        $this->assertSame('master', $result->paymentMethodId);
        $this->assertSame(1250000.0, $result->transactionAmount);
        $this->assertNull($result->externalResourceUrl);
        $this->assertNull($result->errorMessage);
    }

    public function testCreatePaymentParsesPendingPseResponse(): void
    {
        $intent = new PaymentIntent(
            reservationUid: 'ovf_pse_pending',
            transactionAmount: 750000.0,
            paymentMethodId: 'pse',
            payerEmail: 'pse@example.com',
            financialInstitution: '1007'
        );

        $mockResponseBody = json_encode([
            'id' => 987654321,
            'status' => 'pending',
            'status_detail' => 'pending_waiting_payment',
            'payment_method_id' => 'pse',
            'transaction_amount' => 750000.0,
            'external_reference' => 'ovf_pse_pending',
            'transaction_details' => [
                'external_resource_url' => 'https://www.pse.com.co/redirect/token_xyz123',
            ],
        ], JSON_THROW_ON_ERROR);

        $transport = fn () => [200, $mockResponseBody];
        $gateway = new MercadoPagoPaymentGateway('TEST_TOKEN_XYZ', transport: $transport);

        $result = $gateway->createPayment($intent);

        $this->assertTrue($result->success);
        $this->assertFalse($result->isApproved());
        $this->assertTrue($result->isPending());
        $this->assertFalse($result->isRejected());
        $this->assertSame('987654321', $result->paymentId);
        $this->assertSame('pending', $result->status);
        $this->assertSame('https://www.pse.com.co/redirect/token_xyz123', $result->externalResourceUrl);
    }

    public function testCreatePaymentParsesCashVoucherResponse(): void
    {
        $intent = new PaymentIntent(
            reservationUid: 'ovf_cash_efecty',
            transactionAmount: 300000.0,
            paymentMethodId: 'efecty',
            payerEmail: 'cash@example.com'
        );

        $mockResponseBody = json_encode([
            'id' => 554433221,
            'status' => 'pending',
            'status_detail' => 'pending_waiting_payment',
            'payment_method_id' => 'efecty',
            'transaction_amount' => 300000.0,
            'external_reference' => 'ovf_cash_efecty',
            'transaction_details' => [
                'external_resource_url' => 'https://www.mercadopago.com.co/payments/554433221/ticket',
                'verification_code' => '99887766',
                'barcode' => [
                    'content' => '12345678901234567890',
                ],
            ],
        ], JSON_THROW_ON_ERROR);

        $transport = fn () => [200, $mockResponseBody];
        $gateway = new MercadoPagoPaymentGateway('TEST_TOKEN_XYZ', transport: $transport);

        $result = $gateway->createPayment($intent);

        $this->assertTrue($result->success);
        $this->assertTrue($result->isPending());
        $this->assertSame('https://www.mercadopago.com.co/payments/554433221/ticket', $result->externalResourceUrl);
        $this->assertSame('12345678901234567890', $result->barcode);
        $this->assertSame('99887766', $result->verificationCode);
    }

    public function testCreatePaymentParsesRejectedCardWithoutThrowing(): void
    {
        $intent = new PaymentIntent(
            reservationUid: 'ovf_rejected_1',
            transactionAmount: 500000.0,
            paymentMethodId: 'visa',
            payerEmail: 'declined@example.com'
        );

        $mockResponseBody = json_encode([
            'id' => 11223344,
            'status' => 'rejected',
            'status_detail' => 'cc_rejected_high_risk',
            'payment_method_id' => 'visa',
            'transaction_amount' => 500000.0,
            'message' => 'Payment rejected by risk engine',
        ], JSON_THROW_ON_ERROR);

        $transport = fn () => [200, $mockResponseBody];
        $gateway = new MercadoPagoPaymentGateway('TEST_TOKEN_XYZ', transport: $transport);

        $result = $gateway->createPayment($intent);

        $this->assertFalse($result->success);
        $this->assertFalse($result->isApproved());
        $this->assertFalse($result->isPending());
        $this->assertTrue($result->isRejected());
        $this->assertSame('rejected', $result->status);
        $this->assertSame('cc_rejected_high_risk', $result->statusDetail);
        $this->assertSame('Payment rejected by risk engine', $result->errorMessage);
    }

    public function testGetPaymentParsesApprovedDetails(): void
    {
        $mockResponseBody = json_encode([
            'id' => 888777666,
            'status' => 'approved',
            'status_detail' => 'accredited',
            'external_reference' => 'ovf_get_123',
            'transaction_amount' => 2100000.0,
            'transaction_amount_refunded' => 0.0,
            'payment_method_id' => 'visa',
            'refunds' => [],
        ], JSON_THROW_ON_ERROR);

        $transport = fn () => [200, $mockResponseBody];
        $gateway = new MercadoPagoPaymentGateway('TEST_TOKEN_XYZ', transport: $transport);

        $details = $gateway->getPayment('888777666');

        $this->assertSame('888777666', $details->paymentId);
        $this->assertSame('approved', $details->status);
        $this->assertSame('accredited', $details->statusDetail);
        $this->assertSame('ovf_get_123', $details->externalReference);
        $this->assertSame(2100000.0, $details->transactionAmount);
        $this->assertSame(0.0, $details->totalRefundedAmount);
        $this->assertSame('visa', $details->paymentMethodId);
        $this->assertEmpty($details->refunds);
        $this->assertTrue($details->isApproved());
        $this->assertFalse($details->isRefunded());
        $this->assertFalse($details->isPartiallyRefunded());
        $this->assertFalse($details->isCancelledOrRejected());
    }

    public function testGetPaymentParsesRefundedDetailsWithLineItems(): void
    {
        $mockResponseBody = json_encode([
            'id' => 999111222,
            'status' => 'refunded',
            'status_detail' => 'refunded',
            'external_reference' => 'ovf_refund_full',
            'transaction_amount' => 1500000.0,
            'transaction_amount_refunded' => 1500000.0,
            'payment_method_id' => 'master',
            'refunds' => [
                [
                    'id' => 771122,
                    'payment_id' => 999111222,
                    'amount' => 1500000.0,
                    'status' => 'approved',
                    'date_created' => '2026-09-29T16:30:00.000Z',
                ],
            ],
        ], JSON_THROW_ON_ERROR);

        $transport = fn () => [200, $mockResponseBody];
        $gateway = new MercadoPagoPaymentGateway('TEST_TOKEN_XYZ', transport: $transport);

        $details = $gateway->getPayment('999111222');

        $this->assertSame('999111222', $details->paymentId);
        $this->assertSame('refunded', $details->status);
        $this->assertSame(1500000.0, $details->totalRefundedAmount);
        $this->assertCount(1, $details->refunds);
        $this->assertInstanceOf(RefundLineItem::class, $details->refunds[0]);
        $this->assertSame('771122', $details->refunds[0]->refundId);
        $this->assertSame(1500000.0, $details->refunds[0]->amount);
        $this->assertSame('approved', $details->refunds[0]->status);
        $this->assertSame('2026-09-29T16:30:00.000Z', $details->refunds[0]->createdAt);
        $this->assertTrue($details->isRefunded());
        $this->assertFalse($details->isPartiallyRefunded());
    }

    public function testGetPaymentParsesPartiallyRefundedDetails(): void
    {
        $mockResponseBody = json_encode([
            'id' => 999333444,
            'status' => 'partially_refunded',
            'status_detail' => 'refunded',
            'external_reference' => 'ovf_refund_partial',
            'transaction_amount' => 2000000.0,
            'transaction_amount_refunded' => 500000.0,
            'payment_method_id' => 'visa',
            'refunds' => [
                [
                    'id' => 881122,
                    'payment_id' => 999333444,
                    'amount' => 500000.0,
                    'status' => 'approved',
                    'date_created' => '2026-09-29T17:00:00.000Z',
                ],
            ],
        ], JSON_THROW_ON_ERROR);

        $transport = fn () => [200, $mockResponseBody];
        $gateway = new MercadoPagoPaymentGateway('TEST_TOKEN_XYZ', transport: $transport);

        $details = $gateway->getPayment('999333444');

        $this->assertSame('999333444', $details->paymentId);
        $this->assertTrue($details->isPartiallyRefunded());
        $this->assertFalse($details->isRefunded());
        $this->assertCount(1, $details->refunds);
    }

    public function testEmptyAccessTokenThrowsException(): void
    {
        $gateway = new MercadoPagoPaymentGateway('   ');

        $this->expectException(PaymentGatewayException::class);
        $this->expectExceptionMessage('access token is missing');

        $gateway->createPayment(new PaymentIntent(
            reservationUid: 'ovf_1',
            transactionAmount: 100.0,
            paymentMethodId: 'visa',
            payerEmail: 'test@example.com'
        ));
    }

    public function testEmptyPaymentIdInGetPaymentThrowsException(): void
    {
        $gateway = new MercadoPagoPaymentGateway('VALID_TOKEN');

        $this->expectException(PaymentGatewayException::class);
        $this->expectExceptionMessage('Payment ID cannot be empty');

        $gateway->getPayment('   ');
    }

    public function testHttp400BadRequestThrowsPaymentGatewayException(): void
    {
        $mockResponseBody = json_encode([
            'message' => 'Invalid card token',
            'error' => 'bad_request',
            'status' => 400,
            'cause' => [
                ['code' => 2001, 'description' => 'Invalid card token supplied'],
            ],
        ], JSON_THROW_ON_ERROR);

        $transport = fn () => [400, $mockResponseBody];
        $gateway = new MercadoPagoPaymentGateway('TEST_TOKEN_XYZ', transport: $transport);

        $this->expectException(PaymentGatewayException::class);
        $this->expectExceptionCode(400);

        try {
            $gateway->createPayment(new PaymentIntent(
                reservationUid: 'ovf_bad_tok',
                transactionAmount: 500000.0,
                paymentMethodId: 'visa',
                payerEmail: 'bad@example.com',
                token: 'invalid_token'
            ));
        } catch (PaymentGatewayException $e) {
            $this->assertSame(400, $e->getHttpCode());
            $this->assertSame('bad_request', $e->getErrorType());
            $this->assertStringContainsString('Invalid card token', $e->getMessage());
            throw $e;
        }
    }

    public function testHttp500ServerErrorThrowsPaymentGatewayException(): void
    {
        $mockResponseBody = json_encode([
            'message' => 'Internal server error from payment processor',
            'error' => 'internal_server_error',
            'status' => 500,
        ], JSON_THROW_ON_ERROR);

        $transport = fn () => [500, $mockResponseBody];
        $gateway = new MercadoPagoPaymentGateway('TEST_TOKEN_XYZ', transport: $transport);

        $this->expectException(PaymentGatewayException::class);
        $this->expectExceptionCode(500);

        try {
            $gateway->getPayment('123456');
        } catch (PaymentGatewayException $e) {
            $this->assertSame(500, $e->getHttpCode());
            $this->assertSame('internal_server_error', $e->getErrorType());
            throw $e;
        }
    }

    public function testInvalidNonJsonGatewayResponseThrows502(): void
    {
        $transport = fn () => [200, '<html><head><title>Bad Gateway</title></head></html>'];
        $gateway = new MercadoPagoPaymentGateway('TEST_TOKEN_XYZ', transport: $transport);

        $this->expectException(PaymentGatewayException::class);
        $this->expectExceptionCode(502);

        $gateway->getPayment('123456');
    }

    public function testCurlNetworkTimeoutFailureThrows504(): void
    {
        // Pointing to unroutable port on 127.0.0.1 triggers cURL failure
        $gateway = new MercadoPagoPaymentGateway('TEST_TOKEN_XYZ', 'http://127.0.0.1:1', timeoutSeconds: 1);

        $this->expectException(PaymentGatewayException::class);
        $this->expectExceptionCode(504);

        try {
            $gateway->getPayment('999999');
        } catch (PaymentGatewayException $e) {
            $this->assertSame('network_timeout', $e->getErrorType());
            $this->assertArrayHasKey('curl_errno', $e->getContext());
            throw $e;
        }
    }
}
