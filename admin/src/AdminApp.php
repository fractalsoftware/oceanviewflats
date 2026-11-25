<?php

declare(strict_types=1);

namespace OceanViewFlats\Admin;

use OceanViewFlats\Admin\Audit\AuditLogger;
use OceanViewFlats\Admin\Auth\AuthService;
use OceanViewFlats\Admin\Auth\FileIpRateLimiter;
use OceanViewFlats\Admin\Auth\IpRateLimiterInterface;
use OceanViewFlats\Admin\Controller\AuditLogController;
use OceanViewFlats\Admin\Controller\AuthController;
use OceanViewFlats\Admin\Controller\CalendarBlockController;
use OceanViewFlats\Admin\Controller\ChannelSyncController;
use OceanViewFlats\Admin\Controller\DashboardController;
use OceanViewFlats\Admin\Controller\FinancialReportController;
use OceanViewFlats\Admin\Controller\RateController;
use OceanViewFlats\Admin\Controller\ReservationController;
use OceanViewFlats\Admin\Http\Request;
use OceanViewFlats\Admin\Http\Response;
use OceanViewFlats\Admin\Http\RouteAccess;
use OceanViewFlats\Admin\Http\Router;
use OceanViewFlats\Admin\Middleware\AuthMiddleware;
use OceanViewFlats\Admin\Middleware\CsrfMiddleware;
use OceanViewFlats\Admin\Middleware\RbacMiddleware;
use OceanViewFlats\Admin\Middleware\SessionMiddleware;
use OceanViewFlats\Admin\Repository\AdminAuditLogRepository;
use OceanViewFlats\Admin\Repository\AdminCalendarBlockRepository;
use OceanViewFlats\Admin\Repository\AdminRateRepository;
use OceanViewFlats\Admin\Repository\AdminReservationRepository;
use OceanViewFlats\Admin\Service\DashboardQueryService;
use OceanViewFlats\Admin\Service\MercadoPagoRefundClient;
use OceanViewFlats\Admin\Service\MercadoPagoRefundClientInterface;
use OceanViewFlats\Admin\Service\PublicUrlBuilder;
use OceanViewFlats\Admin\Views\ViewRenderer;
use OceanViewFlats\Domain\Finance\FinancialReportEngine;
use OceanViewFlats\Domain\Finance\FinancialReportEngineInterface;
use OceanViewFlats\Domain\Finance\OperationalExpenseRepositoryInterface;
use OceanViewFlats\Domain\Finance\ParafiscalSettlementRepositoryInterface;
use OceanViewFlats\Domain\Finance\PdoOperationalExpenseRepository;
use OceanViewFlats\Domain\Finance\PdoParafiscalSettlementRepository;
use OceanViewFlats\Domain\Fulfillment\BookingFulfillment;
use OceanViewFlats\Domain\Fulfillment\BookingFulfillmentInterface;
use OceanViewFlats\Domain\Fulfillment\CancellationEmailRenderer;
use OceanViewFlats\Domain\Fulfillment\CancellationEmailRendererInterface;
use OceanViewFlats\Domain\Fulfillment\CondominiumClearanceRepositoryInterface;
use OceanViewFlats\Domain\Fulfillment\CondominiumClearanceSyncInterface;
use OceanViewFlats\Domain\Fulfillment\CurlHttpTransport;
use OceanViewFlats\Domain\Fulfillment\EmailSenderInterface;
use OceanViewFlats\Domain\Fulfillment\GuestLifecycleFulfillmentService;
use OceanViewFlats\Domain\Fulfillment\GuestLifecycleFulfillmentServiceInterface;
use OceanViewFlats\Domain\Fulfillment\HuespedManagerClearanceSync;
use OceanViewFlats\Domain\Fulfillment\PdoCondominiumClearanceRepository;
use OceanViewFlats\Domain\Fulfillment\PhpMailSender;
use OceanViewFlats\Domain\Privacy\PdoPiiRedactionService;
use OceanViewFlats\Domain\Privacy\PiiRedactionServiceInterface;
use OceanViewFlats\Domain\Quote\CsvRateSource;
use OceanViewFlats\Domain\Quote\PdoRateRepository;
use OceanViewFlats\Domain\Quote\PdoRateSource;
use OceanViewFlats\Domain\Quote\PropertyRatesConfig;
use OceanViewFlats\Domain\Quote\QuoteEngine;
use OceanViewFlats\Domain\Quote\QuoteEngineInterface;
use OceanViewFlats\Domain\Quote\RateRepositoryInterface;
use OceanViewFlats\Domain\Quote\RateSourceInterface;
use OceanViewFlats\Domain\Reservation\Dashboard\DashboardQueryServiceInterface;
use OceanViewFlats\Domain\Reservation\Dashboard\OperationalAlertDismissalRepositoryInterface;
use OceanViewFlats\Domain\Reservation\Dashboard\PdoOperationalAlertDismissalRepository;
use OceanViewFlats\Domain\Reservation\InboundChannelSyncService;
use OceanViewFlats\Domain\Reservation\InboundChannelSyncServiceInterface;
use OceanViewFlats\Domain\Reservation\MaintenanceBlockRepositoryInterface;
use OceanViewFlats\Domain\Reservation\PdoMaintenanceBlockRepository;
use OceanViewFlats\Domain\Reservation\PdoReservationRepository;
use OceanViewFlats\Domain\Reservation\Port\AuditPort;
use OceanViewFlats\Domain\Reservation\Port\LifecycleEventPublisherPort;
use OceanViewFlats\Domain\Reservation\Port\PaymentRefundPort;
use OceanViewFlats\Domain\Reservation\Port\ReservationPersistencePort;
use OceanViewFlats\Domain\Reservation\ReservationLifecycleEngine;
use OceanViewFlats\Domain\Reservation\ReservationLifecycleEngineInterface;
use OceanViewFlats\Domain\Reservation\ReservationLedger;
use OceanViewFlats\Domain\Reservation\ReservationLedgerInterface;
use OceanViewFlats\Domain\Reservation\ReservationRepositoryInterface;
use OceanViewFlats\Domain\Reservation\Search\PdoReservationSearchAdapter;
use OceanViewFlats\Domain\Reservation\Search\ReservationSearchInterface;
use OceanViewFlats\Domain\Support\PathResolver;
use OceanViewFlats\Infrastructure\Reservation\MercadoPagoPaymentRefundAdapter;
use OceanViewFlats\Infrastructure\Reservation\PdoAuditAdapter;
use OceanViewFlats\Infrastructure\Reservation\PdoReservationPersistenceAdapter;
use OceanViewFlats\Infrastructure\Reservation\TransactionalLifecycleEventPublisherAdapter;
use PDO;

/**
 * OceanViewFlats Authoritative Admin Application Kernel.
 *
 * Encapsulates dependency assembly, security middlewares, and the full
 * administrative route table behind a high-level factory and request-handling interface.
 */
final class AdminApp
{
    public const VERSION = '1.0.0';

    private function __construct(
        private readonly Router $router
    ) {
    }

    public static function getVersion(): string
    {
        return self::VERSION;
    }

    /**
     * Dispatches an incoming HTTP request through the kernel's router and middleware pipeline.
     *
     * @param array<string, mixed> $session
     */
    public function handle(Request $request, array &$session): Response
    {
        if (!headers_sent()) {
            header_remove('X-Powered-By');
        }

        return $this->router->dispatch($request, $session);
    }

    /**
     * Assembles an authoritative AdminApp kernel instance with default dependencies
     * or provided overrides for testing and isolated execution.
     *
     * @param array<string, mixed> $options
     */
    public static function createDefault(PDO $pdo, array $options = []): self
    {
        // 1. Audit Logging
        AuditLogger::setDefaultPdo($pdo);
        /** @var AuditLogger $auditLogger */
        $auditLogger = $options['audit_logger'] ?? new AuditLogger($pdo);

        // 2. Authentication & Rate Limiting
        /** @var IpRateLimiterInterface $rateLimiter */
        $rateLimiter = $options['rate_limiter'] ?? FileIpRateLimiter::createDefault();
        /** @var AuthService $authService */
        $authService = $options['auth_service'] ?? new AuthService($pdo, $rateLimiter);

        // 3. Views & Rendering
        $viewsPath = (string) ($options['views_path'] ?? (__DIR__ . '/Views'));
        /** @var ViewRenderer $viewRenderer */
        $viewRenderer = $options['view_renderer'] ?? new ViewRenderer($viewsPath);

        // 4. Controllers: Auth, Dashboard & Channel Sync
        /** @var InboundChannelSyncServiceInterface $channelSyncService */
        $channelSyncService = $options['channel_sync_service'] ?? InboundChannelSyncService::createDefault();

        $authController = new AuthController(
            auditLogger: $auditLogger,
            authService: $authService,
            viewRenderer: $viewRenderer
        );

        // 5. Reservation Dependencies & Controller
        $publicSiteUrl = (string) ($options['public_site_url'] ?? (getenv('PUBLIC_SITE_URL') ?: 'https://oceanviewflats.com'));
        /** @var PropertyRatesConfig $ratesConfig */
        $ratesConfig = $options['rates_config'] ?? PropertyRatesConfig::createDefault();
        $csvPath = (string) ($options['csv_path'] ?? (PathResolver::resolveIfExists('data/prices.csv') ?? 'public/data/prices.csv'));
        $csvRateSource = isset($options['csv_path'])
            ? new CsvRateSource((string) $options['csv_path'])
            : new CsvRateSource();
        /** @var RateSourceInterface $rateSource */
        $rateSource = $options['rate_source'] ?? new PdoRateSource($pdo, $csvRateSource);
        /** @var QuoteEngineInterface $quoteEngine */
        $quoteEngine = $options['quote_engine'] ?? new QuoteEngine(rateSource: $rateSource, config: $ratesConfig);
        /** @var CancellationEmailRendererInterface $cancellationEmailRenderer */
        $cancellationEmailRenderer = $options['cancellation_email_renderer'] ?? new CancellationEmailRenderer($publicSiteUrl);
        /** @var EmailSenderInterface $emailSender */
        $emailSender = $options['email_sender'] ?? new PhpMailSender();
        $mpAccessToken = (string) ($options['mp_access_token'] ?? ($_ENV['MERCADOPAGO_ACCESS_TOKEN'] ?? $_SERVER['MERCADOPAGO_ACCESS_TOKEN'] ?? getenv('MERCADOPAGO_ACCESS_TOKEN') ?: ''));
        /** @var MercadoPagoRefundClientInterface $refundClient */
        $refundClient = $options['refund_client'] ?? new MercadoPagoRefundClient($mpAccessToken);
        /** @var MaintenanceBlockRepositoryInterface $calendarBlockRepo */
        $calendarBlockRepo = $options['maintenance_block_repository'] ?? new PdoMaintenanceBlockRepository($pdo);
        /** @var ReservationRepositoryInterface $reservationRepository */
        $reservationRepository = $options['reservation_repository'] ?? new PdoReservationRepository($pdo);
        /** @var ReservationSearchInterface $reservationSearch */
        $reservationSearch = $options['reservation_search'] ?? new PdoReservationSearchAdapter($pdo);
        /** @var ReservationLedgerInterface $ledger */
        $ledger = $options['ledger'] ?? ReservationLedger::createDefault(
            pdo: $pdo,
            maintenanceBlockSource: $calendarBlockRepo,
            repository: $reservationRepository
        );

        /** @var PublicUrlBuilder $urlBuilder */
        $urlBuilder = $options['public_url_builder'] ?? new PublicUrlBuilder($publicSiteUrl);

        /** @var CondominiumClearanceRepositoryInterface $clearanceRepo */
        $clearanceRepo = $options['condominium_clearance_repository']
            ?? new PdoCondominiumClearanceRepository($pdo);

        /** @var CondominiumClearanceSyncInterface $clearanceSync */
        $clearanceSync = $options['condominium_clearance_sync']
            ?? $options['clearance_sync']
            ?? HuespedManagerClearanceSync::createDefault(
                $pdo,
                $options['condominium_clearance_transport'] ?? null
            );

        /** @var GuestLifecycleFulfillmentServiceInterface $lifecycleService */
        $lifecycleService = $options['lifecycle_service'] ?? GuestLifecycleFulfillmentService::createDefault($pdo, [
            'email_sender' => $emailSender,
            'public_site_url' => $publicSiteUrl,
            'reservation_repository' => $reservationRepository,
            'clearance_sync' => $clearanceSync,
        ]);

        /** @var ReservationPersistencePort $persistencePort */
        $persistencePort = $options['reservation_persistence_port'] ?? new PdoReservationPersistenceAdapter($pdo, $reservationRepository);
        /** @var PaymentRefundPort $paymentRefundPort */
        $paymentRefundPort = $options['payment_refund_port'] ?? new MercadoPagoPaymentRefundAdapter($refundClient);
        /** @var AuditPort $auditPort */
        $auditPort = $options['audit_port'] ?? new PdoAuditAdapter($pdo);

        $recipientEmail = isset($options['recipient_email']) && is_string($options['recipient_email'])
            ? $options['recipient_email']
            : (isset($options['host_email']) && is_string($options['host_email']) ? $options['host_email'] : null);

        /** @var BookingFulfillmentInterface $bookingFulfillment */
        $bookingFulfillment = $options['booking_fulfillment'] ?? BookingFulfillment::createDefault(
            hostEmail: $recipientEmail ?? null,
            emailSender: $emailSender
        );

        /** @var LifecycleEventPublisherPort $eventPublisherPort */
        $eventPublisherPort = $options['lifecycle_event_publisher_port'] ?? new TransactionalLifecycleEventPublisherAdapter(
            cancellationRenderer: $cancellationEmailRenderer,
            emailSender: $emailSender,
            bookingFulfillment: $bookingFulfillment,
            auditPort: $auditPort
        );

        /** @var ReservationLifecycleEngineInterface $lifecycleEngine */
        $lifecycleEngine = $options['lifecycle_engine'] ?? new ReservationLifecycleEngine(
            persistencePort: $persistencePort,
            paymentRefundPort: $paymentRefundPort,
            eventPublisherPort: $eventPublisherPort,
            auditPort: $auditPort,
            ledger: $ledger,
            quoteEngine: $quoteEngine
        );

        /** @var OperationalAlertDismissalRepositoryInterface $dismissalRepo */
        $dismissalRepo = $options['alert_dismissal_repository']
            ?? new PdoOperationalAlertDismissalRepository($pdo);

        /** @var PiiRedactionServiceInterface $piiRedactionService */
        $piiRedactionService = $options['pii_redaction_service']
            ?? new PdoPiiRedactionService($pdo, $reservationRepository);

        $reservationController = new ReservationController(
            repository: $reservationRepository,
            search: $reservationSearch,
            viewRenderer: $viewRenderer,
            auditLogger: $auditLogger,
            ledger: $ledger,
            quoteEngine: $quoteEngine,
            emailSender: $emailSender,
            lifecycleService: $lifecycleService,
            publicSiteUrl: $publicSiteUrl,
            refundClient: $refundClient,
            cancellationEmailRenderer: $cancellationEmailRenderer,
            pdo: $pdo,
            urlBuilder: $urlBuilder,
            clearanceRepo: $clearanceRepo,
            clearanceSync: $clearanceSync,
            lifecycleEngine: $lifecycleEngine,
            bookingFulfillment: $bookingFulfillment,
            alertDismissalRepo: $dismissalRepo,
            piiRedactionService: $piiRedactionService
        );

        // 6. Rates Repository & Controller
        /** @var RateRepositoryInterface $rateRepo */
        $rateRepo = $options['rate_repository']
            ?? ($rateSource instanceof RateRepositoryInterface
                ? $rateSource
                : new PdoRateRepository($pdo, $ratesConfig, $rateSource));

        $cacheDir = isset($options['cache_dir']) && is_string($options['cache_dir'])
            ? $options['cache_dir']
            : (isset($options['cacheDir']) && is_string($options['cacheDir']) ? $options['cacheDir'] : null);

        $rateController = new RateController(
            rateRepository: $rateRepo,
            viewRenderer: $viewRenderer,
            auditLogger: $auditLogger,
            ratesConfig: $ratesConfig,
            csvPath: $csvPath,
            cacheDir: $cacheDir
        );

        // 7. Channel Sync & Calendar Blocks Controllers
        $channelSyncController = new ChannelSyncController(
            syncService: $channelSyncService,
            viewRenderer: $viewRenderer,
            auditLogger: $auditLogger,
            ledger: $ledger,
            reservationRepository: $reservationRepository
        );

        $calendarBlockController = new CalendarBlockController(
            blockRepository: $calendarBlockRepo,
            ledger: $ledger,
            viewRenderer: $viewRenderer,
            auditLogger: $auditLogger,
            syncService: $channelSyncService,
            reservationRepository: $reservationRepository
        );

        // 8. Dashboard Dependencies & Controller
        $adminReservationRepo = $options['admin_reservation_repository']
            ?? new AdminReservationRepository($pdo, $reservationSearch, $reservationRepository);
        $adminCalendarBlockRepo = $options['admin_calendar_block_repository']
            ?? new AdminCalendarBlockRepository($calendarBlockRepo instanceof PdoMaintenanceBlockRepository ? $calendarBlockRepo : $pdo);
        $adminRateRepo = $options['admin_rate_repository']
            ?? new AdminRateRepository($pdo, $ratesConfig, $rateRepo);

        /** @var OperationalExpenseRepositoryInterface $expenseRepository */
        $expenseRepository = $options['operational_expense_repository']
            ?? new PdoOperationalExpenseRepository($pdo);
        /** @var ParafiscalSettlementRepositoryInterface $parafiscalRepository */
        $parafiscalRepository = $options['parafiscal_settlement_repository']
            ?? new PdoParafiscalSettlementRepository($pdo);

        /** @var DashboardQueryServiceInterface $dashboardQueryService */
        $dashboardQueryService = $options['dashboard_query_service'] ?? new DashboardQueryService(
            reservationRepo: $adminReservationRepo,
            calendarBlockRepo: $adminCalendarBlockRepo,
            rateRepo: $adminRateRepo,
            channelSyncService: $channelSyncService,
            ledger: $ledger,
            clearanceRepo: $clearanceRepo,
            dismissalRepo: $dismissalRepo,
            parafiscalRepo: $parafiscalRepository,
            expenseRepo: $expenseRepository,
            domainReservationRepo: $reservationRepository,
            ratesConfig: $ratesConfig
        );

        $dashboardController = new DashboardController(
            viewRenderer: $viewRenderer,
            syncService: $channelSyncService,
            dashboardQueryService: $dashboardQueryService,
            dismissalRepo: $dismissalRepo,
            auditLogger: $auditLogger,
            reservationSearch: $reservationSearch,
            clearanceRepo: $clearanceRepo,
            publicSiteUrl: $publicSiteUrl,
            urlBuilder: $urlBuilder
        );

        // 9. Audit Log & Financial Report Repositories & Controllers
        $adminAuditLogRepo = $options['admin_audit_log_repository']
            ?? new AdminAuditLogRepository($pdo);
        $auditLogController = new AuditLogController(
            repository: $adminAuditLogRepo,
            viewRenderer: $viewRenderer
        );

        /** @var FinancialReportEngineInterface $financialReportEngine */
        $financialReportEngine = $options['financial_report_engine']
            ?? new FinancialReportEngine(
                reservationRepository: $reservationRepository,
                maintenanceBlockSource: $calendarBlockRepo,
                expenseRepository: $expenseRepository,
                ratesConfig: $ratesConfig,
                parafiscalRepository: $parafiscalRepository
            );
        $financialReportNow = isset($options['financial_report_now']) && $options['financial_report_now'] instanceof \DateTimeImmutable
            ? $options['financial_report_now']
            : null;
        $financialReportController = new FinancialReportController(
            expenseRepository: $expenseRepository,
            reservationRepository: $reservationRepository,
            viewRenderer: $viewRenderer,
            auditLogger: $auditLogger,
            now: $financialReportNow,
            reportEngine: $financialReportEngine,
            parafiscalRepository: $parafiscalRepository,
            pdo: $pdo
        );

        // 10. Security Middlewares & Router (immutable internal security pipeline)
        $sessionMiddleware = new SessionMiddleware();
        $csrfMiddleware = new CsrfMiddleware();
        $authMiddleware = new AuthMiddleware();
        $rbacMiddleware = new RbacMiddleware($viewRenderer);

        $router = new Router(
            sessionMiddleware: $sessionMiddleware,
            csrfMiddleware: $csrfMiddleware,
            authMiddleware: $authMiddleware,
            rbacMiddleware: $rbacMiddleware
        );

        // 11. Register Declarative Administrative Routes with Single-Source-of-Truth RBAC Access Tiers
        $router->get('/login', [$authController, 'showLogin'], RouteAccess::PUBLIC)
            ->post('/login', [$authController, 'login'], RouteAccess::PUBLIC)
            ->post('/logout', [$authController, 'logout'], RouteAccess::PUBLIC)
            ->get('/', [$dashboardController, 'index'], RouteAccess::AUTHENTICATED)
            ->get('/dashboard/hub', [$dashboardController, 'hub'], RouteAccess::AUTHENTICATED)
            ->get('/dashboard/day', [$dashboardController, 'dayDrawer'], RouteAccess::AUTHENTICATED)
            ->post('/dashboard/alerts/dismiss', [$dashboardController, 'dismissAlert'], RouteAccess::MANAGER)
            ->get('/channel-sync/card', [$channelSyncController, 'card'], RouteAccess::AUTHENTICATED)
            ->get('/channel-sync/panel', [$channelSyncController, 'panel'], RouteAccess::AUTHENTICATED)
            ->post('/channel-sync', [$channelSyncController, 'sync'], RouteAccess::MANAGER)
            ->get('/reservations', [$reservationController, 'list'], RouteAccess::AUTHENTICATED)
            ->get('/reservations/new', [$reservationController, 'newReservation'], RouteAccess::MANAGER)
            ->post('/reservations/quote-preview', [$reservationController, 'quotePreview'], RouteAccess::MANAGER)
            ->post('/reservations/create-manual', [$reservationController, 'createManual'], RouteAccess::MANAGER)
            ->get('/reservations/{uid}', [$reservationController, 'show'], RouteAccess::AUTHENTICATED)
            ->get('/reservations/{uid}/edit', [$reservationController, 'editReservation'], RouteAccess::MANAGER)
            ->post('/reservations/{uid}/edit', [$reservationController, 'updateReservation'], RouteAccess::MANAGER)
            ->get('/reservations/{uid}/registry', [$reservationController, 'showRegistry'], RouteAccess::AUTHENTICATED)
            ->post('/reservations/{uid}/registry/complete', [$reservationController, 'completeRegistry'], RouteAccess::MANAGER)
            ->post('/reservations/{uid}/clearance-retry', [$reservationController, 'retryClearance'], RouteAccess::MANAGER)
            ->post('/reservations/{uid}/door-code/override', [$reservationController, 'overrideDoorCode'], RouteAccess::PRIVILEGED)
            ->post('/reservations/{uid}/door-code/regenerate', [$reservationController, 'regenerateDoorCode'], RouteAccess::PRIVILEGED)
            ->post('/reservations/{uid}/redact-pii', [$reservationController, 'redactPii'], RouteAccess::PRIVILEGED)
            ->get('/reservations/{uid}/cancel-modal', [$reservationController, 'cancelModal'], RouteAccess::PRIVILEGED)
            ->post('/reservations/{uid}/cancel', [$reservationController, 'cancel'], RouteAccess::PRIVILEGED)
            ->get('/rates', [$rateController, 'index'], RouteAccess::AUTHENTICATED)
            ->get('/rates/new', [$rateController, 'newTier'], RouteAccess::PRIVILEGED)
            ->post('/rates', [$rateController, 'create'], RouteAccess::PRIVILEGED)
            ->get('/rates/{id}/edit', [$rateController, 'edit'], RouteAccess::PRIVILEGED)
            ->post('/rates/{id}', [$rateController, 'update'], RouteAccess::PRIVILEGED)
            ->delete('/rates/{id}', [$rateController, 'delete'], RouteAccess::PRIVILEGED)
            ->post('/rates/seed-from-csv', [$rateController, 'seedFromCsv'], RouteAccess::PRIVILEGED)
            ->get('/calendar-blocks', [$calendarBlockController, 'index'], RouteAccess::AUTHENTICATED)
            ->get('/calendar/blocks', [$calendarBlockController, 'index'], RouteAccess::AUTHENTICATED)
            ->get('/calendar-blocks/new', [$calendarBlockController, 'newHold'], RouteAccess::MANAGER)
            ->get('/calendar/blocks/new', [$calendarBlockController, 'newHold'], RouteAccess::MANAGER)
            ->post('/calendar-blocks', [$calendarBlockController, 'create'], RouteAccess::MANAGER)
            ->post('/calendar/blocks', [$calendarBlockController, 'create'], RouteAccess::MANAGER)
            ->delete('/calendar-blocks/{id}', [$calendarBlockController, 'delete'], RouteAccess::MANAGER)
            ->post('/calendar-blocks/{id}/delete', [$calendarBlockController, 'delete'], RouteAccess::MANAGER)
            ->post('/calendar-blocks/{id}/release', [$calendarBlockController, 'delete'], RouteAccess::MANAGER)
            ->get('/financial-report', [$financialReportController, 'index'], RouteAccess::PRIVILEGED)
            ->get('/financial-report/export/expenses.csv', [$financialReportController, 'exportExpensesCsv'], RouteAccess::PRIVILEGED)
            ->get('/financial-report/export/reservations.csv', [$financialReportController, 'exportReservationsCsv'], RouteAccess::PRIVILEGED)
            ->get('/financial-report/expenses/new', [$financialReportController, 'newExpense'], RouteAccess::PRIVILEGED)
            ->post('/financial-report/expenses', [$financialReportController, 'createExpense'], RouteAccess::PRIVILEGED)
            ->get('/financial-report/expenses/{id}/edit', [$financialReportController, 'editExpense'], RouteAccess::PRIVILEGED)
            ->post('/financial-report/expenses/{id}', [$financialReportController, 'updateExpense'], RouteAccess::PRIVILEGED)
            ->delete('/financial-report/expenses/{id}', [$financialReportController, 'deleteExpense'], RouteAccess::PRIVILEGED)
            ->post('/financial-report/expenses/{id}/delete', [$financialReportController, 'deleteExpense'], RouteAccess::PRIVILEGED)
            ->get('/financial-report/parafiscal/{periodKey}/{propertyId}/modal', [$financialReportController, 'parafiscalModal'], RouteAccess::PRIVILEGED)
            ->get('/financial-report/parafiscal/{periodKey}/{propertyId}/settle', [$financialReportController, 'parafiscalModal'], RouteAccess::PRIVILEGED)
            ->post('/financial-report/parafiscal/{periodKey}/{propertyId}/settle', [$financialReportController, 'settleParafiscal'], RouteAccess::PRIVILEGED)
            ->post('/financial-report/parafiscal/{periodKey}/{propertyId}/revert', [$financialReportController, 'revertParafiscal'], RouteAccess::PRIVILEGED)
            ->get('/audit-logs', [$auditLogController, 'index'], RouteAccess::PRIVILEGED)
            ->get('/audit-logs/{id}', [$auditLogController, 'show'], RouteAccess::PRIVILEGED);

        return new self($router);
    }
}
