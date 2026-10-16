<?php

declare(strict_types=1);

namespace OceanViewFlats\Admin\Tests\Controller;

use Exception;
use OceanViewFlats\Admin\Audit\AuditLogger;
use OceanViewFlats\Admin\Controller\ReservationController;
use OceanViewFlats\Admin\Http\Request;
use OceanViewFlats\Admin\Service\InMemoryMercadoPagoRefundClient;
use OceanViewFlats\Admin\Tests\Support\AdminDatabaseTestHelper;
use OceanViewFlats\Admin\Views\ViewRenderer;
use OceanViewFlats\Domain\Fulfillment\CancellationEmailRenderer;
use OceanViewFlats\Domain\Fulfillment\CancellationEmailRendererInterface;
use OceanViewFlats\Domain\Fulfillment\ConfirmationEmailRendererInterface;
use OceanViewFlats\Domain\Fulfillment\GuestLifecycleFulfillmentService;
use OceanViewFlats\Domain\Fulfillment\GuestLifecycleFulfillmentServiceInterface;
use OceanViewFlats\Domain\Fulfillment\InMemoryEmailSender;
use OceanViewFlats\Domain\Quote\QuoteEngineInterface;
use OceanViewFlats\Domain\Reservation\PdoReservationRepository;
use OceanViewFlats\Domain\Reservation\ReservationLedgerInterface;
use OceanViewFlats\Domain\Reservation\ReservationRepositoryInterface;
use OceanViewFlats\Domain\Reservation\Search\PdoReservationSearchAdapter;
use OceanViewFlats\Domain\Reservation\Search\ReservationSearchInterface;
use PDO;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

final class ReservationCancellationEmailTest extends TestCase
{
    private PDO $pdo;
    private ReservationRepositoryInterface $repository;
    private ReservationSearchInterface $search;
    private ViewRenderer $viewRenderer;
    private AuditLogger $auditLogger;
    /** @var MockObject&ReservationLedgerInterface */
    private MockObject $ledger;
    /** @var MockObject&QuoteEngineInterface */
    private MockObject $quoteEngine;
    /** @var MockObject&ConfirmationEmailRendererInterface */
    private MockObject $confirmationEmailRenderer;
    private InMemoryEmailSender $emailSender;
    private InMemoryMercadoPagoRefundClient $refundClient;
    private CancellationEmailRenderer $cancellationEmailRenderer;
    private GuestLifecycleFulfillmentServiceInterface $lifecycleService;
    private ReservationController $controller;

    /**
     * @var array<string, mixed>
     */
    private array $session;

    protected function setUp(): void
    {
        $this->pdo = AdminDatabaseTestHelper::createDatabase();

        $this->seedDatabase();

        $this->repository = new PdoReservationRepository($this->pdo);
        $this->search = new PdoReservationSearchAdapter($this->pdo);
        $this->viewRenderer = new ViewRenderer(dirname(__DIR__, 2) . '/src/Views');
        $this->auditLogger = new AuditLogger($this->pdo);
        $this->ledger = $this->createMock(ReservationLedgerInterface::class);
        $this->quoteEngine = $this->createMock(QuoteEngineInterface::class);
        $this->confirmationEmailRenderer = $this->createMock(ConfirmationEmailRendererInterface::class);
        $this->emailSender = new InMemoryEmailSender();
        $this->refundClient = new InMemoryMercadoPagoRefundClient();
        $this->cancellationEmailRenderer = new CancellationEmailRenderer('https://oceanviewflats.com');

        $this->lifecycleService = GuestLifecycleFulfillmentService::createDefault($this->pdo, [
            'email_sender' => $this->emailSender,
            'public_site_url' => 'https://oceanviewflats.com',
            'confirmation_email_renderer' => $this->confirmationEmailRenderer,
            'reservation_repository' => $this->repository,
        ]);

        $this->controller = new ReservationController(
            repository: $this->repository,
            search: $this->search,
            viewRenderer: $this->viewRenderer,
            auditLogger: $this->auditLogger,
            ledger: $this->ledger,
            quoteEngine: $this->quoteEngine,
            emailSender: $this->emailSender,
            lifecycleService: $this->lifecycleService,
            publicSiteUrl: 'https://oceanviewflats.com',
            refundClient: $this->refundClient,
            cancellationEmailRenderer: $this->cancellationEmailRenderer,
            pdo: $this->pdo
        );

        $this->session = [
            'admin_user_id' => 1,
            'admin_user_name' => 'Operator Manuel',
            'admin_user_email' => 'operator@oceanviewflats.com',
            'admin_user_role' => 'admin',
            'csrf_token' => 'test-csrf-token-xyz',
        ];
    }

    private function seedDatabase(): void
    {
        $esCheckIn = (new \DateTimeImmutable('+30 days'))->format('Y-m-d');
        $esCheckOut = (new \DateTimeImmutable('+35 days'))->format('Y-m-d');
        $enCheckIn = (new \DateTimeImmutable('+60 days'))->format('Y-m-d');
        $enCheckOut = (new \DateTimeImmutable('+65 days'))->format('Y-m-d');

        $this->pdo->exec("
            INSERT INTO admin_users (id, email, password_hash, name, role)
            VALUES (1, 'operator@oceanviewflats.com', 'hash', 'Operator Manuel', 'admin');

            INSERT INTO reservations (
                id, reservation_uid, property_id, guest_name, guest_email, guest_phone,
                check_in, check_out, total_price, refunded_amount, status, payment_status,
                mercadopago_payment_id, lang
            ) VALUES (
                1, 'res-online-es', 'ocean-view-penthouse', 'Sofia Vergara', 'sofia@example.com', '+573001234567',
                '{$esCheckIn}', '{$esCheckOut}', 1500000.00, 0.00, 'confirmed', 'approved',
                'pay-mp-123456', 'es'
            );

            INSERT INTO reservations (
                id, reservation_uid, property_id, guest_name, guest_email, guest_phone,
                check_in, check_out, total_price, refunded_amount, status, payment_status,
                mercadopago_payment_id, lang
            ) VALUES (
                2, 'res-online-en', 'ocean-breeze-suite', 'John Doe', 'john@example.com', '+15551234567',
                '{$enCheckIn}', '{$enCheckOut}', 1000000.00, 0.00, 'confirmed', 'approved',
                'pay-mp-789012', 'en'
            );
        ");
    }

    public function testCancelModalRendersCheckedCheckboxByDefault(): void
    {
        $request = (new Request('GET', '/reservations/res-online-es/cancel-modal', server: ['HTTP_HX_REQUEST' => 'true']))
            ->withAttribute('uid', 'res-online-es');

        $response = $this->controller->cancelModal($request, $this->session);

        $this->assertSame(200, $response->getStatusCode());
        $body = $response->getBody();
        $this->assertStringContainsString('name="send_cancellation_email"', $body);
        $this->assertStringContainsString('checked', $body);
        $this->assertStringContainsString('sofia@example.com', $body);
    }

    public function testCancellationDispatchesGuestEmailWhenCheckboxChecked(): void
    {
        $post = [
            'csrf_token' => 'test-csrf-token-xyz',
            'reason' => 'Guest schedule change',
            'refund_type' => 'full',
            'send_cancellation_email' => '1',
        ];
        $request = (new Request('POST', '/reservations/res-online-es/cancel', post: $post, server: ['HTTP_HX_REQUEST' => 'true']))
            ->withAttribute('uid', 'res-online-es');

        $response = $this->controller->cancel($request, $this->session);

        $this->assertSame(200, $response->getStatusCode());

        // Verify email dispatched
        $sentMessages = $this->emailSender->getSentMessages();
        $this->assertCount(1, $sentMessages);
        $this->assertSame('sofia@example.com', $sentMessages[0]['to']);
        $this->assertStringContainsString('Reserva Cancelada', $sentMessages[0]['subject']);
        $this->assertStringContainsString('ocean-view-penthouse', $sentMessages[0]['subject']);
        $this->assertStringContainsString('Sofia Vergara', $sentMessages[0]['htmlBody']);
        $this->assertStringContainsString('1.500.000', $sentMessages[0]['htmlBody']);

        // Assert audit logs contain cancellation_email_sent
        $stmt = $this->pdo->prepare('SELECT action, payload_after FROM admin_audit_logs WHERE entity_id = :uid ORDER BY id ASC');
        $stmt->execute(['uid' => 'res-online-es']);
        $logs = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $actions = array_column($logs, 'action');
        $this->assertContains('reservation_cancelled', $actions);
        $this->assertContains('refund_issued', $actions);
        $this->assertContains('cancellation_email_sent', $actions);

        $emailLog = null;
        foreach ($logs as $log) {
            if ($log['action'] === 'cancellation_email_sent') {
                $emailLog = json_decode((string) $log['payload_after'], true);
                break;
            }
        }
        $this->assertNotNull($emailLog);
        $this->assertSame('sofia@example.com', $emailLog['recipient']);
        $this->assertEquals(1500000.0, (float) $emailLog['refund_amount']);
        $this->assertEquals(0.0, (float) $emailLog['policy_retention']);
    }

    public function testCancellationDoesNotDispatchEmailWhenCheckboxUnchecked(): void
    {
        $post = [
            'csrf_token' => 'test-csrf-token-xyz',
            'reason' => 'Guest opted out of email notification',
            'refund_type' => 'full',
            // send_cancellation_email omitted
        ];
        $request = (new Request('POST', '/reservations/res-online-en/cancel', post: $post, server: ['HTTP_HX_REQUEST' => 'true']))
            ->withAttribute('uid', 'res-online-en');

        $response = $this->controller->cancel($request, $this->session);

        $this->assertSame(200, $response->getStatusCode());

        // Verify NO email dispatched
        $this->assertCount(0, $this->emailSender->getSentMessages());

        // Verify audit logs do NOT contain cancellation_email_sent
        $stmt = $this->pdo->prepare('SELECT action FROM admin_audit_logs WHERE entity_id = :uid ORDER BY id ASC');
        $stmt->execute(['uid' => 'res-online-en']);
        $actions = $stmt->fetchAll(PDO::FETCH_COLUMN);

        $this->assertContains('reservation_cancelled', $actions);
        $this->assertNotContains('cancellation_email_sent', $actions);
    }

    public function testCancellationHandlesEmailSenderFailureGracefullyWithoutRollback(): void
    {
        // Configure email sender to simulate failure
        $this->emailSender->setShouldFail(true);

        $post = [
            'csrf_token' => 'test-csrf-token-xyz',
            'reason' => 'Testing email sender failure resiliency',
            'refund_type' => 'full',
            'send_cancellation_email' => '1',
        ];
        $request = (new Request('POST', '/reservations/res-online-es/cancel', post: $post, server: ['HTTP_HX_REQUEST' => 'true']))
            ->withAttribute('uid', 'res-online-es');

        $response = $this->controller->cancel($request, $this->session);

        // Cancellation should still succeed with 200
        $this->assertSame(200, $response->getStatusCode());

        // Database reservation must be cancelled
        $res = $this->repository->findByUid('res-online-es');
        $this->assertNotNull($res);
        $this->assertSame('cancelled', $res->status->value);
        $this->assertSame('refunded', $res->paymentStatus);

        // Audit log should capture email_delivery_failed
        $stmt = $this->pdo->prepare('SELECT action, payload_after FROM admin_audit_logs WHERE entity_id = :uid ORDER BY id ASC');
        $stmt->execute(['uid' => 'res-online-es']);
        $logs = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $actions = array_column($logs, 'action');
        $this->assertContains('reservation_cancelled', $actions);
        $this->assertContains('email_delivery_failed', $actions);

        $failureLog = null;
        foreach ($logs as $log) {
            if ($log['action'] === 'email_delivery_failed') {
                $failureLog = json_decode((string) $log['payload_after'], true);
                break;
            }
        }
        $this->assertNotNull($failureLog);
        $this->assertStringContainsString('Email sender returned false', $failureLog['error']);
    }

    public function testCancellationHandlesRendererExceptionGracefully(): void
    {
        $mockRenderer = $this->createMock(CancellationEmailRendererInterface::class);
        $mockRenderer->expects($this->once())
            ->method('renderGuestSubject')
            ->willThrowException(new Exception('Template rendering malfunction'));

        $controller = new ReservationController(
            repository: $this->repository,
            search: $this->search,
            viewRenderer: $this->viewRenderer,
            auditLogger: $this->auditLogger,
            ledger: $this->ledger,
            quoteEngine: $this->quoteEngine,
            emailSender: $this->emailSender,
            lifecycleService: $this->lifecycleService,
            publicSiteUrl: 'https://oceanviewflats.com',
            refundClient: $this->refundClient,
            cancellationEmailRenderer: $mockRenderer,
            pdo: $this->pdo
        );

        $post = [
            'csrf_token' => 'test-csrf-token-xyz',
            'reason' => 'Renderer exception test',
            'refund_type' => 'none',
            'send_cancellation_email' => '1',
        ];
        $request = (new Request('POST', '/reservations/res-online-es/cancel', post: $post, server: ['HTTP_HX_REQUEST' => 'true']))
            ->withAttribute('uid', 'res-online-es');

        $response = $controller->cancel($request, $this->session);

        $this->assertSame(200, $response->getStatusCode());

        // Reservation still cancelled
        $res = $this->repository->findByUid('res-online-es');
        $this->assertNotNull($res);
        $this->assertSame('cancelled', $res->status->value);

        // Audit log recorded error
        $stmt = $this->pdo->prepare('SELECT action, payload_after FROM admin_audit_logs WHERE entity_id = :uid AND action = "email_delivery_failed"');
        $stmt->execute(['uid' => 'res-online-es']);
        $log = $stmt->fetch(PDO::FETCH_ASSOC);

        $this->assertNotEmpty($log);
        $payload = json_decode((string) $log['payload_after'], true);
        $this->assertStringContainsString('Template rendering malfunction', $payload['error']);
    }

    public function testCancelErrorPreservesCheckboxState(): void
    {
        // 1. Missing reason with checkbox checked
        $postChecked = [
            'csrf_token' => 'test-csrf-token-xyz',
            'reason' => '',
            'refund_type' => 'none',
            'send_cancellation_email' => '1',
        ];
        $requestChecked = (new Request('POST', '/reservations/res-online-es/cancel', post: $postChecked, server: ['HTTP_HX_REQUEST' => 'true']))
            ->withAttribute('uid', 'res-online-es');

        $responseChecked = $this->controller->cancel($requestChecked, $this->session);
        $this->assertSame(422, $responseChecked->getStatusCode());
        $bodyChecked = $responseChecked->getBody();
        $this->assertStringContainsString('Cancellation reason is required.', $bodyChecked);
        $this->assertMatchesRegularExpression('/name="send_cancellation_email"[^>]*checked/', $bodyChecked);

        // 2. Missing reason with checkbox unchecked
        $postUnchecked = [
            'csrf_token' => 'test-csrf-token-xyz',
            'reason' => '',
            'refund_type' => 'none',
        ];
        $requestUnchecked = (new Request('POST', '/reservations/res-online-es/cancel', post: $postUnchecked, server: ['HTTP_HX_REQUEST' => 'true']))
            ->withAttribute('uid', 'res-online-es');

        $responseUnchecked = $this->controller->cancel($requestUnchecked, $this->session);
        $this->assertSame(422, $responseUnchecked->getStatusCode());
        $bodyUnchecked = $responseUnchecked->getBody();
        $this->assertStringContainsString('Cancellation reason is required.', $bodyUnchecked);
        $this->assertDoesNotMatchRegularExpression('/name="send_cancellation_email"[^>]*checked/', $bodyUnchecked);
    }
}
