<?php

declare(strict_types=1);

namespace OceanViewFlats\Admin\Tests\Controller;

use OceanViewFlats\Admin\Audit\AuditLogger;
use OceanViewFlats\Admin\Controller\ReservationController;
use OceanViewFlats\Admin\Http\Request;
use OceanViewFlats\Admin\Http\Response;
use OceanViewFlats\Admin\Service\InMemoryMercadoPagoRefundClient;
use OceanViewFlats\Admin\Service\MercadoPagoRefundException;
use OceanViewFlats\Admin\Tests\Support\AdminDatabaseTestHelper;
use OceanViewFlats\Admin\Views\ViewRenderer;
use OceanViewFlats\Domain\Fulfillment\BookingFulfillment;
use OceanViewFlats\Domain\Fulfillment\BookingFulfillmentInterface;
use OceanViewFlats\Domain\Fulfillment\ConfirmationEmailRendererInterface;
use OceanViewFlats\Domain\Fulfillment\GuestLifecycleFulfillmentService;
use OceanViewFlats\Domain\Fulfillment\GuestLifecycleFulfillmentServiceInterface;
use OceanViewFlats\Domain\Fulfillment\InMemoryEmailSender;
use OceanViewFlats\Domain\Quote\Quote;
use OceanViewFlats\Domain\Quote\QuoteEngineInterface;
use OceanViewFlats\Domain\Reservation\PdoReservationRepository;
use OceanViewFlats\Domain\Reservation\ReservationLedgerInterface;
use OceanViewFlats\Domain\Reservation\ReservationLifecycleEngine;
use OceanViewFlats\Domain\Reservation\ReservationRepositoryInterface;
use OceanViewFlats\Domain\Reservation\Search\PdoReservationSearchAdapter;
use OceanViewFlats\Domain\Reservation\Search\ReservationSearchInterface;
use OceanViewFlats\Infrastructure\Reservation\TransactionalLifecycleEventPublisherAdapter;
use PDO;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

final class ReservationControllerTest extends TestCase
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
    private MockObject $emailRenderer;
    private InMemoryEmailSender $emailSender;
    private InMemoryMercadoPagoRefundClient $refundClient;
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
        $this->emailRenderer = $this->createMock(ConfirmationEmailRendererInterface::class);
        $this->emailSender = new InMemoryEmailSender();
        $this->refundClient = new InMemoryMercadoPagoRefundClient();

        $this->lifecycleService = GuestLifecycleFulfillmentService::createDefault($this->pdo, [
            'email_sender' => $this->emailSender,
            'public_site_url' => 'https://oceanviewflats.com',
            'confirmation_email_renderer' => $this->emailRenderer,
            'reservation_repository' => $this->repository,
            'clearance_sync' => null,
        ]);

        $mockClearanceTransport = new class implements \OceanViewFlats\Domain\Fulfillment\HttpTransportInterface {
            public function post(string $url, array|string $data = [], array $headers = [], array $options = []): array
            {
                return [
                    'statusCode' => 503,
                    'body' => 'Mock transport disabled in unit test',
                    'headers' => [],
                    'cookies' => [],
                    'error' => null,
                ];
            }
        };

        $clearanceRepo = new \OceanViewFlats\Domain\Fulfillment\PdoCondominiumClearanceRepository($this->pdo);
        $defaultClearanceSync = new \OceanViewFlats\Domain\Fulfillment\HuespedManagerClearanceSync(
            repository: $clearanceRepo,
            reservationRepository: $this->repository,
            searchAdapter: $this->search,
            transport: $mockClearanceTransport,
            auditLogger: $this->auditLogger
        );

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
            pdo: $this->pdo,
            clearanceRepo: $clearanceRepo,
            clearanceSync: $defaultClearanceSync
        );

        $this->session = [
            'admin_user_id' => 1,
            'admin_user_name' => 'Operator Manuel',
            'admin_user_email' => 'operator@oceanviewflats.com',
            'admin_user_role' => 'admin',
            'csrf_token' => 'test-csrf-token-xyz',
        ];
    }

    /**
     * @param array<string, mixed>|null $session
     */
    private function executeIndex(?Request $request = null, ?array $session = null): Response
    {
        $req = $request ?? new Request('GET', '/reservations');
        if ($session !== null) {
            $sess = $session;
            return $this->controller->list($req, $sess);
        }
        return $this->controller->list($req, $this->session);
    }

    private function executeShow(string $uid, bool $isHtmx = false, ?string $hxTarget = null): Response
    {
        $server = $isHtmx ? ['HTTP_HX_REQUEST' => 'true'] : [];
        if ($hxTarget !== null) {
            $server['HTTP_HX_TARGET'] = $hxTarget;
        }
        $request = (new Request('GET', '/reservations/' . $uid, server: $server))
            ->withAttribute('uid', $uid);
        return $this->controller->show($request, $this->session);
    }

    private function executeRegistry(string $uid, bool $isHtmx = true): Response
    {
        $server = $isHtmx ? ['HTTP_HX_REQUEST' => 'true'] : [];
        $request = (new Request('GET', '/reservations/' . $uid . '/registry', server: $server))
            ->withAttribute('uid', $uid);
        return $this->controller->showRegistry($request, $this->session);
    }

    public function testIndexReturnsFullPageForStandardBrowserRequest(): void
    {
        $response = $this->executeIndex();
        $this->assertSame(200, $response->getStatusCode());
        $html = $response->getBody();

        $this->assertStringContainsString('<!DOCTYPE html>', $html);
        $this->assertStringContainsString('Reservations Management', $html);
        $this->assertStringContainsString('id="search-input"', $html);
        $this->assertStringContainsString('Alice Smith', $html);
        $this->assertStringContainsString('Bob Jones', $html);
    }

    public function testIndexReturnsTablePartialForHtmxRequest(): void
    {
        $partial = $this->executeIndex(new Request('GET', '/reservations', server: ['HTTP_HX_REQUEST' => 'true']));

        $this->assertSame(200, $partial->getStatusCode());
        $content = $partial->getBody();
        $this->assertStringNotContainsString('<!DOCTYPE html>', $content);
        $this->assertStringNotContainsString('<header', $content);
        $this->assertStringContainsString('Alice Smith', $content);
        $this->assertStringContainsString('Bob Jones', $content);
        $this->assertStringContainsString('Showing', $content);
    }

    public function testIndexAppliesFiltersCorrectly(): void
    {
        $filteredReq = new Request(
            'GET',
            '/reservations',
            query: ['property_id' => '1707'],
            server: ['HTTP_HX_REQUEST' => 'true']
        );
        $html = $this->executeIndex($filteredReq)->getBody();

        $this->assertStringContainsString('Carlos Gomez', $html);
        $this->assertStringNotContainsString('Alice Smith', $html);
    }

    public function testShowReturnsDrawerPartialForHtmxDrawerContainerTarget(): void
    {
        $html = $this->executeShow('res-1', isHtmx: true, hxTarget: 'drawer-container')->getBody();

        $this->assertStringNotContainsString('<!DOCTYPE html>', $html);
        $this->assertStringNotContainsString('Reservations Management', $html);
        $this->assertStringContainsString('slide-over-title', $html);
        $this->assertStringContainsString('Alice Smith', $html);
        $this->assertStringContainsString('1234#', $html);
        $this->assertStringContainsString('Operational Audit Trail', $html);
        $this->assertStringContainsString('pin_override', $html);
    }

    public function testShowReturnsFullDashboardForHtmxNonDrawerTarget(): void
    {
        $html = $this->executeShow('res-1', isHtmx: true, hxTarget: 'body')->getBody();

        $this->assertStringContainsString('<!DOCTYPE html>', $html);
        $this->assertStringContainsString('Reservations Management', $html);
        $this->assertStringContainsString('slide-over-title', $html);
        $this->assertStringContainsString('Alice Smith', $html);
    }

    public function testShowReturnsFullPageWithOpenDrawerForBrowserRequest(): void
    {
        $html = $this->executeShow('res-1', isHtmx: false)->getBody();

        $this->assertStringContainsString('<!DOCTYPE html>', $html);
        $this->assertStringContainsString('Reservations Management', $html);
        $this->assertStringContainsString('slide-over-title', $html);
        $this->assertStringContainsString('Alice Smith', $html);
    }

    public function testShowReturns404AlertForHtmxNonExistentReservation(): void
    {
        $response = $this->executeShow('unknown-uid', isHtmx: true);

        $this->assertSame(404, $response->getStatusCode());
        $this->assertStringContainsString('Reservation not found', $response->getBody());
    }

    public function testShowRedirectsForBrowserNonExistentReservation(): void
    {
        $response = $this->executeShow('unknown-uid', isHtmx: false);

        $this->assertSame(302, $response->getStatusCode());
        $this->assertSame('/reservations', $response->getHeaders()['Location'] ?? null);
    }

    public function testShowRegistryReturnsCompletedModal(): void
    {
        $html = $this->executeRegistry('res-1')->getBody();

        $this->assertStringContainsString('Guest Registry Dossier', $html);
        $this->assertStringContainsString('Bob Smith', $html);
        $this->assertStringContainsString('ABC-123', $html);
        $this->assertStringContainsString('192.168.1.1', $html);
    }

    public function testShowRegistryReturnsPendingModalWithCopyableLink(): void
    {
        $html = $this->executeRegistry('res-2')->getBody();

        $this->assertStringContainsString('Guest Registry Pending', $html);
        $this->assertStringContainsString('https://oceanviewflats.com/registry/index.html?property=1606&amp;check_in=2026-10-10&amp;check_out=2026-10-15&amp;code=res-2', $html);
        $this->assertStringContainsString('https://oceanviewflats.com/guide/index.html?code=res-2', $html);
        $this->assertStringContainsString('Copy Link', $html);
        $this->assertStringContainsString('Copy Guide Link', $html);
    }

    public function testShowRegistryReturns404WhenReservationMissing(): void
    {
        $response = $this->executeRegistry('nonexistent');
        $this->assertSame(404, $response->getStatusCode());
    }

    public function testNewReservationHtmxReturnsModalPartial(): void
    {
        $request = new Request('GET', '/reservations/new', server: ['HTTP_HX_REQUEST' => 'true']);
        $response = $this->controller->newReservation($request, $this->session);
        $this->assertSame(200, $response->getStatusCode());
        $this->assertStringContainsString('Create Manual Reservation', $response->getBody());
        $this->assertStringNotContainsString('<!DOCTYPE html>', $response->getBody());
    }

    public function testNewReservationDirectBrowserReturnsFullPageWithModal(): void
    {
        $request = new Request('GET', '/reservations/new');
        $response = $this->controller->newReservation($request, $this->session);
        $this->assertSame(200, $response->getStatusCode());
        $this->assertStringContainsString('Create Manual Reservation', $response->getBody());
        $this->assertStringContainsString('Reservations Management', $response->getBody());
        $this->assertStringContainsString('<!DOCTYPE html>', $response->getBody());
    }

    public function testQuotePreviewReturnsAvailabilityAndQuoteBreakdown(): void
    {
        $this->ledger->expects($this->once())
            ->method('isAvailable')
            ->with('1707', '2026-11-01', '2026-11-04')
            ->willReturn(true);

        $quote = new Quote(
            propertyId: '1707',
            checkIn: '2026-11-01',
            checkOut: '2026-11-04',
            nights: [],
            nightsCount: 3,
            accommodationTotalCop: 1500000.0,
            cleaningFeeCop: 150000.0,
            resortFeeCop: 100000.0,
            totalCop: 1750000.0,
            minimumStayRequired: 2,
            isValid: true
        );

        $this->quoteEngine->expects($this->once())
            ->method('quote')
            ->with('1707', '2026-11-01', '2026-11-04')
            ->willReturn($quote);

        $request = new Request('POST', '/reservations/quote-preview', post: [
            'property_id' => '1707',
            'check_in' => '2026-11-01',
            'check_out' => '2026-11-04',
            'source' => 'bank_transfer',
        ]);
        $response = $this->controller->quotePreview($request);

        $this->assertSame(200, $response->getStatusCode());
        $body = $response->getBody();
        $this->assertStringContainsString('Available (3 Nights)', $body);
        $this->assertStringContainsString('$1,750,000 COP', $body);
        $this->assertStringContainsString('$1,500,000', $body);
        $this->assertStringContainsString('data-available="true"', $body);
        $this->assertStringContainsString('availabilityChecked', $body);
        $this->assertStringContainsString('"available":true', $body);
        $this->assertStringContainsString('"defaultPrice":1750000', $body);
    }

    public function testQuotePreviewForOwnerStayZeroesTotal(): void
    {
        $this->ledger->method('isAvailable')->willReturn(true);
        $quote = new Quote(
            propertyId: '1707',
            checkIn: '2026-11-01',
            checkOut: '2026-11-04',
            nights: [],
            nightsCount: 3,
            accommodationTotalCop: 1500000.0,
            cleaningFeeCop: 150000.0,
            resortFeeCop: 100000.0,
            totalCop: 1750000.0,
            minimumStayRequired: 2,
            isValid: true
        );
        $this->quoteEngine->method('quote')->willReturn($quote);

        $request = new Request('POST', '/reservations/quote-preview', post: [
            'property_id' => '1707',
            'check_in' => '2026-11-01',
            'check_out' => '2026-11-04',
            'source' => 'owner_stay',
        ]);
        $response = $this->controller->quotePreview($request);

        $this->assertSame(200, $response->getStatusCode());
        $body = $response->getBody();
        $this->assertStringContainsString('$0 COP', $body);
        $this->assertStringContainsString('Owner Stay automatically zeroes total pricing', $body);
        $this->assertStringContainsString('data-available="true"', $body);
        $this->assertStringContainsString('availabilityChecked', $body);
        $this->assertStringContainsString('"available":true', $body);
        $this->assertStringContainsString('"defaultPrice":0', $body);
    }

    public function testQuotePreviewReturnsConflictWarningWhenUnavailable(): void
    {
        $this->ledger->method('isAvailable')->willReturn(false);
        $this->ledger->method('getConflictReasons')
            ->willReturn(['Direct reservation conflict: res-1']);

        $request = new Request('POST', '/reservations/quote-preview', post: [
            'property_id' => '1606',
            'check_in' => '2026-10-01',
            'check_out' => '2026-10-05',
        ]);
        $response = $this->controller->quotePreview($request);

        $this->assertSame(200, $response->getStatusCode());
        $body = $response->getBody();
        $this->assertStringContainsString('Selected Dates Are Unavailable', $body);
        $this->assertStringContainsString('href="/reservations/res-1"', $body);
        $this->assertStringContainsString('data-available="false"', $body);
        $this->assertStringContainsString('availabilityChecked', $body);
        $this->assertStringContainsString('"available":false', $body);
        $this->assertStringContainsString('Direct reservation conflict: res-1', $body);
    }

    public function testQuotePreviewReturnsDataAvailableFalseWhenDatesInvalid(): void
    {
        $request = new Request('POST', '/reservations/quote-preview', post: [
            'property_id' => '1606',
            'check_in' => '2026-11-05',
            'check_out' => '2026-11-01',
        ]);
        $response = $this->controller->quotePreview($request);

        $this->assertSame(200, $response->getStatusCode());
        $body = $response->getBody();
        $this->assertStringContainsString('data-available="false"', $body);
        $this->assertStringContainsString('availabilityChecked', $body);
        $this->assertStringContainsString('"available":false', $body);
        $this->assertStringContainsString('check-out must be after check-in', $body);
    }

    public function testCreateModalRendersDateSynchronizationAndHtmxLifecycleScript(): void
    {
        $request = new Request('GET', '/reservations/new', server: ['HTTP_HX_REQUEST' => 'true']);
        $response = $this->controller->newReservation($request, $this->session);

        $this->assertSame(200, $response->getStatusCode());
        $body = $response->getBody();

        // Date inputs and submit button presence
        $this->assertStringContainsString('id="create-check-in"', $body);
        $this->assertStringContainsString('id="create-check-out"', $body);
        $this->assertStringContainsString('id="create-submit-btn"', $body);
        $this->assertStringContainsString('data-default-text="Create Reservation"', $body);

        // Date synchronization logic
        $this->assertStringContainsString('syncMinEndDate', $body);
        $this->assertStringContainsString('checkOutInput.min = minEndStr', $body);

        // HTMX lifecycle listeners for quote preview
        $this->assertStringContainsString('htmx:beforeRequest', $body);
        $this->assertStringContainsString('htmx:afterRequest', $body);
        $this->assertStringContainsString('Checking availability...', $body);
    }

    public function testCreateModalWithPrefilledCheckInSetsMinCheckOut(): void
    {
        $request = new Request('GET', '/reservations/new', query: ['check_in' => '2026-11-10'], server: ['HTTP_HX_REQUEST' => 'true']);
        $response = $this->controller->newReservation($request, $this->session);

        $this->assertSame(200, $response->getStatusCode());
        $body = $response->getBody();

        $this->assertStringContainsString('value="2026-11-10"', $body);
        $this->assertStringContainsString('min="2026-11-11"', $body);
    }

    public function testCreateModalRendersSubmitButtonInitiallyDisabledWithHelper(): void
    {
        // 1. Default / Direct manual reservation modal
        $request = new Request('GET', '/reservations/new', server: ['HTTP_HX_REQUEST' => 'true']);
        $response = $this->controller->newReservation($request, $this->session);

        $this->assertSame(200, $response->getStatusCode());
        $body = $response->getBody();

        // Submit button initially disabled with aria-disabled and Tailwind disabled styling
        $this->assertMatchesRegularExpression('/id="create-submit-btn"[^>]*\bdisabled\b/', $body);
        $this->assertMatchesRegularExpression('/id="create-submit-btn"[^>]*aria-disabled="true"/', $body);
        $this->assertMatchesRegularExpression('/id="create-submit-btn"[^>]*opacity-50 cursor-not-allowed/', $body);

        // Submit helper element initially instructs date selection
        $this->assertStringContainsString('id="create-submit-helper"', $body);
        $this->assertStringContainsString('Select available dates to enable creation', $body);

        // 2. Airbnb onboarding modal variant
        $airbnbRequest = new Request('GET', '/reservations/new', query: ['source' => 'airbnb'], server: ['HTTP_HX_REQUEST' => 'true']);
        $airbnbResponse = $this->controller->newReservation($airbnbRequest, $this->session);

        $this->assertSame(200, $airbnbResponse->getStatusCode());
        $airbnbBody = $airbnbResponse->getBody();

        $this->assertMatchesRegularExpression('/id="create-submit-btn"[^>]*\bdisabled\b/', $airbnbBody);
        $this->assertMatchesRegularExpression('/id="create-submit-btn"[^>]*aria-disabled="true"/', $airbnbBody);
        $this->assertMatchesRegularExpression('/id="create-submit-btn"[^>]*opacity-50 cursor-not-allowed/', $airbnbBody);
        $this->assertStringContainsString('data-default-text="Onboard &amp; Generate Dispatch"', $airbnbBody);
        $this->assertStringContainsString('id="create-submit-helper"', $airbnbBody);
        $this->assertStringContainsString('Select available dates to enable creation', $airbnbBody);
    }

    public function testCreateModalIncludesReactiveValidationMatrixScript(): void
    {
        $request = new Request('GET', '/reservations/new', server: ['HTTP_HX_REQUEST' => 'true']);
        $response = $this->controller->newReservation($request, $this->session);

        $this->assertSame(200, $response->getStatusCode());
        $body = $response->getBody();

        // Matrix runner functions
        $this->assertStringContainsString('function getUnmetRequirement()', $body);
        $this->assertStringContainsString('function validateForm()', $body);

        // Property validation (1606 or 1707)
        $this->assertStringContainsString("propVal !== '1606' && propVal !== '1707'", $body);
        $this->assertStringContainsString('Select a valid property', $body);

        // Date range validation
        $this->assertStringContainsString('!checkInVal || !checkOutVal', $body);
        $this->assertStringContainsString('Select available dates to enable creation', $body);
        $this->assertStringContainsString('checkOutVal <= checkInVal', $body);
        $this->assertStringContainsString('Check-out date must be after check-in date', $body);

        // Availability check & conflict guidance
        $this->assertStringContainsString('!isAvailable', $body);
        $this->assertStringContainsString('Resolve date conflict above to proceed', $body);

        // Total price validation
        $this->assertStringContainsString('priceVal === \'\' || isNaN(Number(priceVal)) || Number(priceVal) < 0', $body);
        $this->assertStringContainsString('Total price cannot be negative', $body);

        // Source-dependent validation rules
        // Universal guest name requirement
        $this->assertStringContainsString('Guest name is required', $body);
        $this->assertStringContainsString('Airbnb (External Reservation)', $body);
        $this->assertStringContainsString('var airbnbContainer =', $body);

        // Airbnb: external confirmation code required and conditional email validation
        $this->assertStringContainsString("currentSource === 'airbnb'", $body);
        $this->assertStringContainsString('!airbnbCode', $body);
        $this->assertStringContainsString('Airbnb confirmation code required', $body);
        $this->assertStringContainsString('Valid guest email required when sending confirmation', $body);

        // Direct stays: guest name, email regex, phone length (7-25 chars)
        $this->assertStringContainsString('Guest name and email required', $body);
        $this->assertStringContainsString('phoneVal.length < 7 || phoneVal.length > 25', $body);
        $this->assertStringContainsString('Guest phone number must be 7-25 characters', $body);

        // Unlocking when valid / locking when invalid
        $this->assertStringContainsString("btn.setAttribute('aria-disabled', 'false')", $body);
        $this->assertStringContainsString("btn.classList.remove('opacity-50', 'cursor-not-allowed')", $body);
        $this->assertStringContainsString("btn.setAttribute('aria-disabled', 'true')", $body);
        $this->assertStringContainsString("btn.classList.add('opacity-50', 'cursor-not-allowed')", $body);

        // Event listeners: availabilityChecked, htmx:afterSwap, form input & change
        $this->assertStringContainsString("addEventListener('availabilityChecked'", $body);
        $this->assertStringContainsString("addEventListener('htmx:afterSwap'", $body);
        $this->assertStringContainsString("addEventListener('input'", $body);
        $this->assertStringContainsString("addEventListener('change'", $body);

        // Enter keypress and submit event prevention
        $this->assertStringContainsString("addEventListener('keydown'", $body);
        $this->assertStringContainsString("evt.key === 'Enter'", $body);
        $this->assertStringContainsString("addEventListener('submit'", $body);
        $this->assertStringContainsString('evt.preventDefault()', $body);

        // Immediate initial run
        $this->assertStringContainsString('syncAvailabilityFromDom()', $body);
        $this->assertStringContainsString('validateForm()', $body);
    }

    public function testCreateManualReservationValidatesInputAndReturns422(): void
    {
        // Bad dates: checkout <= checkin
        $request1 = new Request('POST', '/reservations/create-manual', post: [
            'property_id' => '1606',
            'check_in' => '2026-11-05',
            'check_out' => '2026-11-01',
            'total_price' => 1000000,
            'guest_name' => 'John Doe',
            'guest_email' => 'john@example.com',
            'guest_phone' => '+573001234567',
        ]);
        $response = $this->controller->createManual($request1, $this->session);
        $this->assertSame(422, $response->getStatusCode());
        $this->assertStringContainsString('Check-out date must be strictly after check-in date', $response->getBody());

        // Bad email
        $request2 = new Request('POST', '/reservations/create-manual', post: [
            'property_id' => '1606',
            'check_in' => '2026-11-01',
            'check_out' => '2026-11-05',
            'total_price' => 1000000,
            'guest_name' => 'John Doe',
            'guest_email' => 'invalid-email',
            'guest_phone' => '+573001234567',
        ]);
        $response2 = $this->controller->createManual($request2, $this->session);
        $this->assertSame(422, $response2->getStatusCode());
        $this->assertStringContainsString('valid guest email', $response2->getBody());
    }

    public function testCreateManualReservationRejectsLedgerConflictWith422(): void
    {
        $this->ledger->method('isAvailable')->willReturn(false);

        $request = new Request('POST', '/reservations/create-manual', post: [
            'property_id' => '1606',
            'check_in' => '2026-10-01',
            'check_out' => '2026-10-05',
            'total_price' => 1200000,
            'guest_name' => 'Overlapping Stay',
            'guest_email' => 'overlap@example.com',
            'guest_phone' => '+573009998877',
        ]);
        $response = $this->controller->createManual($request, $this->session);

        $this->assertSame(422, $response->getStatusCode());
        $this->assertStringContainsString('Selected dates conflict', $response->getBody());
    }

    public function testCreateManualReservationRejectsInvalidDateFormatWith422(): void
    {
        // Invalid check-in format
        $request1 = new Request('POST', '/reservations/create-manual', post: [
            'property_id' => '1606',
            'check_in' => '2026/11/01',
            'check_out' => '2026-11-05',
            'total_price' => 1000000,
            'guest_name' => 'John Doe',
            'guest_email' => 'john@example.com',
            'guest_phone' => '+573001234567',
        ]);
        $response1 = $this->controller->createManual($request1, $this->session);
        $this->assertSame(422, $response1->getStatusCode());
        $this->assertStringContainsString('valid dates in Y-m-d format', $response1->getBody());

        // Non-existent calendar day (e.g. Feb 30)
        $request2 = new Request('POST', '/reservations/create-manual', post: [
            'property_id' => '1606',
            'check_in' => '2026-02-30',
            'check_out' => '2026-03-05',
            'total_price' => 1000000,
            'guest_name' => 'John Doe',
            'guest_email' => 'john@example.com',
            'guest_phone' => '+573001234567',
        ]);
        $response2 = $this->controller->createManual($request2, $this->session);
        $this->assertSame(422, $response2->getStatusCode());
        $this->assertStringContainsString('valid dates in Y-m-d format', $response2->getBody());

        // Invalid check-out format
        $request3 = new Request('POST', '/reservations/create-manual', post: [
            'property_id' => '1606',
            'check_in' => '2026-11-01',
            'check_out' => 'not-a-date',
            'total_price' => 1000000,
            'guest_name' => 'John Doe',
            'guest_email' => 'john@example.com',
            'guest_phone' => '+573001234567',
        ]);
        $response3 = $this->controller->createManual($request3, $this->session);
        $this->assertSame(422, $response3->getStatusCode());
        $this->assertStringContainsString('valid dates in Y-m-d format', $response3->getBody());
    }

    public function testCreateManualReservationRejectsInvalidSourceWith422(): void
    {
        $request = new Request('POST', '/reservations/create-manual', post: [
            'property_id' => '1606',
            'check_in' => '2026-11-01',
            'check_out' => '2026-11-05',
            'source' => 'unknown_source',
            'total_price' => 1000000,
            'guest_name' => 'John Doe',
            'guest_email' => 'john@example.com',
            'guest_phone' => '+573001234567',
        ]);
        $response = $this->controller->createManual($request, $this->session);
        $this->assertSame(422, $response->getStatusCode());
        $this->assertStringContainsString('Invalid reservation source', $response->getBody());
    }

    public function testCreateManualReservationRejectsInvalidPhoneLengthForNonAirbnbWith422(): void
    {
        // Too short (< 7 characters)
        $request1 = new Request('POST', '/reservations/create-manual', post: [
            'property_id' => '1606',
            'check_in' => '2026-11-01',
            'check_out' => '2026-11-05',
            'source' => 'bank_transfer',
            'total_price' => 1000000,
            'guest_name' => 'John Doe',
            'guest_email' => 'john@example.com',
            'guest_phone' => '12345',
        ]);
        $response1 = $this->controller->createManual($request1, $this->session);
        $this->assertSame(422, $response1->getStatusCode());
        $this->assertStringContainsString('Guest phone number must be between 7 and 25 characters', $response1->getBody());

        // Too long (> 25 characters)
        $request2 = new Request('POST', '/reservations/create-manual', post: [
            'property_id' => '1606',
            'check_in' => '2026-11-01',
            'check_out' => '2026-11-05',
            'source' => 'cash',
            'total_price' => 1000000,
            'guest_name' => 'John Doe',
            'guest_email' => 'john@example.com',
            'guest_phone' => str_repeat('9', 26),
        ]);
        $response2 = $this->controller->createManual($request2, $this->session);
        $this->assertSame(422, $response2->getStatusCode());
        $this->assertStringContainsString('Guest phone number must be between 7 and 25 characters', $response2->getBody());
    }

    public function testCreateManualReservationRejectsLedgerConflictWithItemizedReasons(): void
    {
        $this->ledger->method('isAvailable')->willReturn(false);
        $this->ledger->method('getConflictReasons')->willReturn([
            'Direct reservation conflict: res-man-abc123',
            'Channel block: airbnb-block-xyz789',
        ]);

        $request = new Request('POST', '/reservations/create-manual', post: [
            'property_id' => '1606',
            'check_in' => '2026-10-01',
            'check_out' => '2026-10-05',
            'total_price' => 1200000,
            'guest_name' => 'Overlapping Stay',
            'guest_email' => 'overlap@example.com',
            'guest_phone' => '+573009998877',
        ]);
        $response = $this->controller->createManual($request, $this->session);

        $this->assertSame(422, $response->getStatusCode());
        $body = $response->getBody();
        $this->assertStringContainsString('Selected dates conflict: Direct reservation conflict: res-man-abc123; Channel block: airbnb-block-xyz789', $body);
    }

    public function testCreateManualReservation422PreservesAllSubmittedFormFields(): void
    {
        $request = new Request('POST', '/reservations/create-manual', post: [
            'property_id' => '1707',
            'source' => 'bank_transfer',
            'check_in' => '2026-11-01',
            'check_out' => '2026-11-05',
            'total_price' => '1850000',
            'guest_name' => 'Alejandro Morales',
            'guest_email' => 'invalid-email-syntax', // Triggers 422
            'guest_phone' => '+573001234567',
            'notes' => 'VIP guest requesting early check-in at 1pm',
            'pre_mark_registry' => '1',
            'send_confirmation_email' => '1',
            'external_confirmation_code' => 'CONF-777',
            'channel_block_uid' => 'BLOCK-888',
            'max_guests' => '11',
        ]);

        $response = $this->controller->createManual($request, $this->session);

        $this->assertSame(422, $response->getStatusCode());
        $body = $response->getBody();

        // Property 1707 selected
        $this->assertStringContainsString('<option value="1707" selected>', $body);
        // Source bank_transfer selected
        $this->assertStringContainsString('<option value="bank_transfer" selected>', $body);
        // Dates preserved
        $this->assertStringContainsString('value="2026-11-01"', $body);
        $this->assertStringContainsString('value="2026-11-05"', $body);
        // Total price preserved
        $this->assertStringContainsString('value="1850000"', $body);
        // Guest contact info preserved
        $this->assertStringContainsString('value="Alejandro Morales"', $body);
        $this->assertStringContainsString('value="invalid-email-syntax"', $body);
        $this->assertStringContainsString('value="+573001234567"', $body);
        // Notes preserved in textarea
        $this->assertStringContainsString('VIP guest requesting early check-in at 1pm', $body);
        // Checkboxes preserved as checked
        $this->assertMatchesRegularExpression('/name="pre_mark_registry"[^>]*checked/', $body);
        $this->assertMatchesRegularExpression('/name="send_confirmation_email"[^>]*checked/', $body);
        // External platform codes preserved
        $this->assertStringContainsString('value="CONF-777"', $body);
        $this->assertStringContainsString('value="BLOCK-888"', $body);
        // Max guests preserved
        $this->assertStringContainsString('value="11"', $body);
    }

    public function testCreateManualReservation422PreservesUncheckedCheckboxesAndPropertyDefault(): void
    {
        $request = new Request('POST', '/reservations/create-manual', post: [
            'property_id' => '',
            'check_in' => '2026-11-01',
            'check_out' => '2026-11-05',
            'guest_name' => 'Carlos Santana',
            'guest_email' => 'invalid-email-syntax', // Triggers 422
            'guest_phone' => '+573001234567',
            // Checkboxes explicitly omitted
        ]);

        $response = $this->controller->createManual($request, $this->session);

        $this->assertSame(422, $response->getStatusCode());
        $body = $response->getBody();

        // Property cleanly defaults to 1606
        $this->assertStringContainsString('<option value="1606" selected>', $body);
        // Checkboxes preserved as unchecked
        $this->assertDoesNotMatchRegularExpression('/name="pre_mark_registry"[^>]*checked/', $body);
        $this->assertDoesNotMatchRegularExpression('/name="send_confirmation_email"[^>]*checked/', $body);
    }

    public function testCreateManualReservationSuccessDispatchesEmailRendersOobDrawerAndClosesModal(): void
    {
        $this->ledger->method('isAvailable')->willReturn(true);
        $this->emailRenderer->method('renderGuestSubject')->willReturn('Your Stay Confirmation');
        $this->emailRenderer->method('renderGuestConfirmationHtml')->willReturn('<p>Welcome</p>');

        $request = new Request('POST', '/reservations/create-manual', post: [
            'property_id' => '1707',
            'check_in' => '2026-12-01',
            'check_out' => '2026-12-05',
            'source' => 'bank_transfer',
            'total_price' => 2400000,
            'guest_name' => 'Elena Rostova',
            'guest_email' => 'elena@example.com',
            'guest_phone' => '+573008889900',
            'notes' => 'Transfer receipt Bancolombia #12345',
            'pre_mark_registry' => '1',
            'send_confirmation_email' => '1',
        ], server: ['HTTP_HX_REQUEST' => 'true']);
        $response = $this->controller->createManual($request, $this->session);

        $this->assertSame(200, $response->getStatusCode());
        $headers = $response->getHeaders();
        $this->assertSame('reservationUpdated', $headers['HX-Trigger'] ?? null);
        $this->assertMatchesRegularExpression('#^/reservations/res-man-[a-f0-9]+$#', (string) ($headers['HX-Push-Url'] ?? ''));
        $this->assertStringContainsString('modal-container', $response->getBody());
        $this->assertStringContainsString('drawer-container', $response->getBody());
        $this->assertStringContainsString('Elena Rostova', $response->getBody());

        // Verify database row
        $stmt = $this->pdo->prepare('SELECT * FROM reservations WHERE guest_email = :email');
        $stmt->execute(['email' => 'elena@example.com']);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        $this->assertNotFalse($row);
        $this->assertSame('1707', $row['property_id']);
        $this->assertSame('Elena Rostova', $row['guest_name']);
        $this->assertSame('bank_transfer', $row['source']);
        $this->assertSame(1, (int) $row['registry_completed']);
        $this->assertNotNull($row['registry_completed_at']);
        $this->assertMatchesRegularExpression('/^0[0-9]{6}#$/', $row['door_code']);

        // Verify audit log
        $logStmt = $this->pdo->prepare('SELECT * FROM admin_audit_logs WHERE action = :action AND entity_id = :uid');
        $logStmt->execute(['action' => 'manual_reservation_created', 'uid' => $row['reservation_uid']]);
        $log = $logStmt->fetch(PDO::FETCH_ASSOC);
        $this->assertNotFalse($log);

        // Verify emails were sent (guest confirmation + host notification)
        $sent = $this->emailSender->getSentMessages();
        $this->assertCount(2, $sent);
        $this->assertSame('elena@example.com', $sent[0]['to']);
        $this->assertSame('rentals@oceanviewflats.com', $sent[1]['to']);
    }

    public function testCreateManualReservationEmailFailureDoesNotRollback(): void
    {
        $this->ledger->method('isAvailable')->willReturn(true);
        $this->emailRenderer->method('renderGuestSubject')->willReturn('Subject');
        $this->emailRenderer->method('renderGuestConfirmationHtml')->willReturn('<p>Body</p>');
        $this->emailSender->setShouldFail(true);

        $request = new Request('POST', '/reservations/create-manual', post: [
            'property_id' => '1606',
            'check_in' => '2026-12-10',
            'check_out' => '2026-12-12',
            'total_price' => 800000,
            'guest_name' => 'Failing Email Guest',
            'guest_email' => 'fail@example.com',
            'guest_phone' => '+573001110000',
            'send_confirmation_email' => '1',
        ], server: ['HTTP_HX_REQUEST' => 'true']);
        $response = $this->controller->createManual($request, $this->session);

        $this->assertSame(200, $response->getStatusCode());

        // DB row still created despite fulfillment email failure
        $stmt = $this->pdo->prepare('SELECT * FROM reservations WHERE guest_email = :email');
        $stmt->execute(['email' => 'fail@example.com']);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        $this->assertNotFalse($row);
    }

    public function testCompleteRegistryMarksCompletedGeneratesDoorCodeIfNullAndEmitsHxTrigger(): void
    {
        // res-2 currently has registry_completed = 0 and door_code = NULL
        $request = (new Request('POST', '/reservations/res-2/registry/complete'))
            ->withAttribute('uid', 'res-2');
        $response = $this->controller->completeRegistry($request, $this->session);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('reservationUpdated', $response->getHeaders()['HX-Trigger'] ?? null);

        // Check updated DB
        $res = $this->repository->findByUid('res-2');
        $this->assertNotNull($res);
        $this->assertTrue($res->registryCompleted);
        $this->assertNotNull($res->registryCompletedAt);
        $this->assertMatchesRegularExpression('/^0[0-9]{6}#$/', (string) $res->doorCode);

        // Check audit log
        $logStmt = $this->pdo->prepare('SELECT * FROM admin_audit_logs WHERE action = :action AND entity_id = :uid');
        $logStmt->execute(['action' => 'registry_manual_complete', 'uid' => 'res-2']);
        $log = $logStmt->fetch(PDO::FETCH_ASSOC);
        $this->assertNotFalse($log);
    }

    public function testOverrideDoorCodeRequiresConfirmedStatus(): void
    {
        // res-2 has status = 'pending_payment'
        $request = (new Request('POST', '/reservations/res-2/door-code/override', post: ['door_code' => '0123456#']))
            ->withAttribute('uid', 'res-2');
        $response = $this->controller->overrideDoorCode($request, $this->session);

        $this->assertSame(422, $response->getStatusCode());
        $this->assertStringContainsString('strictly restricted to confirmed reservations', $response->getBody());
    }

    public function testOverrideDoorCodeUpdatesPinAndAuditLog(): void
    {
        // res-1 has status = 'confirmed'
        $request = (new Request('POST', '/reservations/res-1/door-code/override', post: ['door_code' => '0887766#']))
            ->withAttribute('uid', 'res-1');
        $response = $this->controller->overrideDoorCode($request, $this->session);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('reservationUpdated', $response->getHeaders()['HX-Trigger'] ?? null);

        $res = $this->repository->findByUid('res-1');
        $this->assertNotNull($res);
        $this->assertSame('0887766#', $res->doorCode);

        // Check audit log
        $logStmt = $this->pdo->prepare('SELECT * FROM admin_audit_logs WHERE action = :action AND entity_id = :uid ORDER BY id DESC LIMIT 1');
        $logStmt->execute(['action' => 'pin_override', 'uid' => 'res-1']);
        $log = $logStmt->fetch(PDO::FETCH_ASSOC);
        $this->assertNotFalse($log);
        $this->assertStringContainsString('0887766#', (string) $log['payload_after']);
    }

    public function testRegenerateDoorCodeGeneratesNewPin(): void
    {
        // res-1 has status = 'confirmed' and door_code = '1234#'
        $request = (new Request('POST', '/reservations/res-1/door-code/regenerate'))
            ->withAttribute('uid', 'res-1');
        $response = $this->controller->regenerateDoorCode($request, $this->session);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('reservationUpdated', $response->getHeaders()['HX-Trigger'] ?? null);

        $res = $this->repository->findByUid('res-1');
        $this->assertNotNull($res);
        $this->assertNotSame('1234#', $res->doorCode);
        $this->assertMatchesRegularExpression('/^0[0-9]{6}#$/', (string) $res->doorCode);

        // Check audit log
        $logStmt = $this->pdo->prepare('SELECT * FROM admin_audit_logs WHERE action = :action AND entity_id = :uid ORDER BY id DESC LIMIT 1');
        $logStmt->execute(['action' => 'pin_regenerate', 'uid' => 'res-1']);
        $log = $logStmt->fetch(PDO::FETCH_ASSOC);
        $this->assertNotFalse($log);
    }

    public function testCompleteRegistryNotFoundReturns404(): void
    {
        $request = (new Request('POST', '/reservations/res-nonexistent/registry/complete'))
            ->withAttribute('uid', 'res-nonexistent');
        $response = $this->controller->completeRegistry($request, $this->session);

        $this->assertSame(404, $response->getStatusCode());
        $this->assertStringContainsString('Reservation not found', $response->getBody());
    }

    public function testCompleteRegistryFailureReturns400(): void
    {
        $mockLifecycle = $this->createMock(GuestLifecycleFulfillmentServiceInterface::class);
        $mockLifecycle->method('completeRegistryManually')->willReturn(
            \OceanViewFlats\Domain\Fulfillment\RegistryFulfillmentResult::validationFailure(['Custom registry error'])
        );

        $controller = new ReservationController(
            repository: $this->repository,
            search: $this->search,
            viewRenderer: $this->viewRenderer,
            auditLogger: $this->auditLogger,
            ledger: $this->ledger,
            quoteEngine: $this->quoteEngine,
            emailSender: $this->emailSender,
            lifecycleService: $mockLifecycle,
            publicSiteUrl: 'https://oceanviewflats.com',
            refundClient: $this->refundClient,
            pdo: $this->pdo
        );

        $request = (new Request('POST', '/reservations/res-1/registry/complete'))
            ->withAttribute('uid', 'res-1');
        $response = $controller->completeRegistry($request, $this->session);

        $this->assertSame(400, $response->getStatusCode());
        $this->assertStringContainsString('Custom registry error', $response->getBody());
    }

    public function testCompleteRegistryTriggersCondominiumClearanceSyncAndReflectsInDrawer(): void
    {
        $clearanceRepo = new \OceanViewFlats\Domain\Fulfillment\PdoCondominiumClearanceRepository($this->pdo);

        $mockSync = $this->createMock(\OceanViewFlats\Domain\Fulfillment\CondominiumClearanceSyncInterface::class);
        $mockSync->expects($this->once())
            ->method('syncForReservation')
            ->with(
                'res-3',
                $this->callback(function ($admin) {
                    return $admin instanceof \OceanViewFlats\Domain\Fulfillment\AdminContext
                        && $admin->adminUserId === 1;
                })
            )
            ->willReturnCallback(function (string $uid, ?\OceanViewFlats\Domain\Fulfillment\AdminContext $admin) use ($clearanceRepo) {
                $clearance = new \OceanViewFlats\Domain\Fulfillment\CondominiumClearance(
                    reservationUid: $uid,
                    propertyId: '1707',
                    status: \OceanViewFlats\Domain\Fulfillment\CondominiumClearance::STATUS_SYNCED,
                    clearanceNumber: 'CLR-MANUAL-888',
                    syncedAt: '2026-10-09 18:00:00'
                );
                $clearanceRepo->save($clearance);
                return $clearance;
            });

        $lifecycleService = GuestLifecycleFulfillmentService::createDefault($this->pdo, [
            'email_sender' => $this->emailSender,
            'public_site_url' => 'https://oceanviewflats.com',
            'confirmation_email_renderer' => $this->emailRenderer,
            'reservation_repository' => $this->repository,
            'clearance_sync' => $mockSync,
        ]);

        $controller = new ReservationController(
            repository: $this->repository,
            search: $this->search,
            viewRenderer: $this->viewRenderer,
            auditLogger: $this->auditLogger,
            ledger: $this->ledger,
            quoteEngine: $this->quoteEngine,
            emailSender: $this->emailSender,
            lifecycleService: $lifecycleService,
            publicSiteUrl: 'https://oceanviewflats.com',
            refundClient: $this->refundClient,
            pdo: $this->pdo,
            clearanceRepo: $clearanceRepo,
            clearanceSync: $mockSync
        );

        $request = (new Request(
            method: 'POST',
            uri: '/reservations/res-3/registry/complete',
            server: ['HTTP_HX_REQUEST' => 'true']
        ))->withAttribute('uid', 'res-3');

        $response = $controller->completeRegistry($request, $this->session);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('reservationUpdated', $response->getHeader('HX-Trigger'));

        // Verify DB update
        $res = $this->repository->findByUid('res-3');
        $this->assertNotNull($res);
        $this->assertTrue($res->registryCompleted);

        // Verify clearance reflected in drawer HTML
        $body = $response->getBody();
        $this->assertStringContainsString('Condominium Clearance', $body);
        $this->assertStringContainsString('Synced', $body);
        $this->assertStringContainsString('#CLR-MANUAL-888', $body);
    }

    public function testCompleteRegistryNonBlockingWhenClearanceSyncFails(): void
    {
        $mockSync = $this->createMock(\OceanViewFlats\Domain\Fulfillment\CondominiumClearanceSyncInterface::class);
        $mockSync->expects($this->once())
            ->method('syncForReservation')
            ->willThrowException(new \RuntimeException('Huesped Manager portal timeout'));

        $lifecycleService = GuestLifecycleFulfillmentService::createDefault($this->pdo, [
            'email_sender' => $this->emailSender,
            'public_site_url' => 'https://oceanviewflats.com',
            'confirmation_email_renderer' => $this->emailRenderer,
            'reservation_repository' => $this->repository,
            'clearance_sync' => $mockSync,
        ]);

        $controller = new ReservationController(
            repository: $this->repository,
            search: $this->search,
            viewRenderer: $this->viewRenderer,
            auditLogger: $this->auditLogger,
            ledger: $this->ledger,
            quoteEngine: $this->quoteEngine,
            emailSender: $this->emailSender,
            lifecycleService: $lifecycleService,
            publicSiteUrl: 'https://oceanviewflats.com',
            refundClient: $this->refundClient,
            pdo: $this->pdo
        );

        $request = (new Request(
            method: 'POST',
            uri: '/reservations/res-3/registry/complete',
            server: ['HTTP_HX_REQUEST' => 'true']
        ))->withAttribute('uid', 'res-3');

        $response = $controller->completeRegistry($request, $this->session);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('reservationUpdated', $response->getHeader('HX-Trigger'));

        // Verify reservation completed successfully despite sync failure
        $res = $this->repository->findByUid('res-3');
        $this->assertNotNull($res);
        $this->assertTrue($res->registryCompleted);
    }

    public function testOverrideDoorCodeNotFoundReturns404(): void
    {
        $request = (new Request('POST', '/reservations/res-nonexistent/door-code/override', post: ['door_code' => '123456#']))
            ->withAttribute('uid', 'res-nonexistent');
        $response = $this->controller->overrideDoorCode($request, $this->session);

        $this->assertSame(404, $response->getStatusCode());
        $this->assertStringContainsString('Reservation not found', $response->getBody());
    }

    public function testOverrideDoorCodeInvalidFormatReturns422(): void
    {
        // res-1 is confirmed, but code is too short
        $request = (new Request('POST', '/reservations/res-1/door-code/override', post: ['door_code' => '12#']))
            ->withAttribute('uid', 'res-1');
        $response = $this->controller->overrideDoorCode($request, $this->session);

        $this->assertSame(422, $response->getStatusCode());
        $this->assertStringContainsString('Invalid PIN format', $response->getBody());
    }

    public function testRegenerateDoorCodeNotFoundReturns404(): void
    {
        $request = (new Request('POST', '/reservations/res-nonexistent/door-code/regenerate'))
            ->withAttribute('uid', 'res-nonexistent');
        $response = $this->controller->regenerateDoorCode($request, $this->session);

        $this->assertSame(404, $response->getStatusCode());
        $this->assertStringContainsString('Reservation not found', $response->getBody());
    }

    public function testCancelModalRendersSuccessfullyForConfirmedReservation(): void
    {
        $server = ['HTTP_HX_REQUEST' => 'true'];
        $request = (new Request('GET', '/reservations/res-1/cancel-modal', server: $server))
            ->withAttribute('uid', 'res-1');

        $response = $this->controller->cancelModal($request, $this->session);

        $this->assertSame(200, $response->getStatusCode());
        $body = $response->getBody();
        $this->assertStringContainsString('Cancel Reservation', $body);
        $this->assertStringContainsString('res-1', $body);
        $this->assertStringContainsString('1,200,000', $body);
        $this->assertStringContainsString('Full Refund', $body);
        $this->assertStringContainsString('Partial Refund', $body);
        $this->assertStringContainsString('No Refund (Policy Retention)', $body);
    }

    public function testCancelModalDisablesHiddenPartialRefundInputAndAvoidsStepMismatch(): void
    {
        $server = ['HTTP_HX_REQUEST' => 'true'];
        $request = (new Request('GET', '/reservations/res-1/cancel-modal', server: $server))
            ->withAttribute('uid', 'res-1');

        $response = $this->controller->cancelModal($request, $this->session);
        $this->assertSame(200, $response->getStatusCode());
        $body = $response->getBody();

        // 1. Extract the refund_amount input tag
        $this->assertSame(1, preg_match('/<input[^>]*name="refund_amount"[^>]*>/s', $body, $matches));
        $inputTag = $matches[0] ?? '';

        // 2. When default refund_type is 'full', the hidden partial refund input MUST be disabled so HTML5 constraint validation ignores it
        $this->assertMatchesRegularExpression('/\bdisabled\b/', $inputTag);

        // 3. The step attribute MUST NOT combine min="1" with step="1000" (which invalidates all multiples of 1000 via (val - 1) % 1000 != 0)
        $this->assertStringNotContainsString('step="1000"', $inputTag);
        $this->assertStringContainsString('step="any"', $inputTag);

        // 4. Radio onchange handlers must toggle the disabled property on #partial-refund-amount in sync with visibility
        $this->assertStringContainsString("getElementById('partial-refund-amount').disabled = true", $body);
        $this->assertStringContainsString("getElementById('partial-refund-amount').disabled = false", $body);

        // 5. Verify zero-balance reservation (e.g. Airbnb / $0 COP) also keeps refund_amount disabled and free of invalid min > max constraints
        $this->pdo->exec("UPDATE reservations SET total_price = 0.00 WHERE reservation_uid = 'res-1'");
        $zeroResponse = $this->controller->cancelModal($request, $this->session);
        $this->assertSame(200, $zeroResponse->getStatusCode());
        $this->assertSame(1, preg_match('/<input[^>]*name="refund_amount"[^>]*>/s', $zeroResponse->getBody(), $zeroMatches));
        $zeroInputTag = $zeroMatches[0] ?? '';
        $this->assertMatchesRegularExpression('/\bdisabled\b/', $zeroInputTag);
    }

    public function testCancelModalReturns404ForNonExistentReservation(): void
    {
        $request = (new Request('GET', '/reservations/unknown/cancel-modal'))
            ->withAttribute('uid', 'unknown');

        $response = $this->controller->cancelModal($request, $this->session);

        $this->assertSame(404, $response->getStatusCode());
        $this->assertStringContainsString('Reservation not found', $response->getBody());
    }

    public function testCancelModalReturns422ForAlreadyCancelledReservation(): void
    {
        $this->pdo->exec("UPDATE reservations SET status = 'cancelled' WHERE reservation_uid = 'res-1'");

        $request = (new Request('GET', '/reservations/res-1/cancel-modal'))
            ->withAttribute('uid', 'res-1');

        $response = $this->controller->cancelModal($request, $this->session);

        $this->assertSame(422, $response->getStatusCode());
        $this->assertStringContainsString('already cancelled', $response->getBody());
    }

    public function testCancelRejectsInvalidCsrfToken(): void
    {
        $post = [
            'csrf_token' => 'invalid-token',
            'reason' => 'Guest cancelled',
            'refund_type' => 'full',
        ];
        $request = (new Request('POST', '/reservations/res-1/cancel', post: $post))
            ->withAttribute('uid', 'res-1');

        $response = $this->controller->cancel($request, $this->session);

        $this->assertSame(403, $response->getStatusCode());
        $this->assertStringContainsString('Invalid or expired CSRF token', $response->getBody());
    }

    public function testCancelRequiresReason(): void
    {
        $post = [
            'csrf_token' => 'test-csrf-token-xyz',
            'reason' => '',
            'refund_type' => 'full',
        ];
        $request = (new Request('POST', '/reservations/res-1/cancel', post: $post, server: ['HTTP_HX_REQUEST' => 'true']))
            ->withAttribute('uid', 'res-1');

        $response = $this->controller->cancel($request, $this->session);

        $this->assertSame(422, $response->getStatusCode());
        $this->assertStringContainsString('Cancellation reason is required', $response->getBody());
    }

    public function testCancelValidatesPartialAmount(): void
    {
        $postZero = [
            'csrf_token' => 'test-csrf-token-xyz',
            'reason' => 'Guest requested',
            'refund_type' => 'partial',
            'refund_amount' => '0',
        ];
        $requestZero = (new Request('POST', '/reservations/res-1/cancel', post: $postZero, server: ['HTTP_HX_REQUEST' => 'true']))
            ->withAttribute('uid', 'res-1');

        $responseZero = $this->controller->cancel($requestZero, $this->session);
        $this->assertSame(422, $responseZero->getStatusCode());
        $this->assertStringContainsString('Partial refund amount must be greater than 0', $responseZero->getBody());

        $postExceeding = [
            'csrf_token' => 'test-csrf-token-xyz',
            'reason' => 'Guest requested',
            'refund_type' => 'partial',
            'refund_amount' => '2000000',
        ];
        $requestExceeding = (new Request('POST', '/reservations/res-1/cancel', post: $postExceeding, server: ['HTTP_HX_REQUEST' => 'true']))
            ->withAttribute('uid', 'res-1');

        $responseExceeding = $this->controller->cancel($requestExceeding, $this->session);
        $this->assertSame(422, $responseExceeding->getStatusCode());
        $this->assertStringContainsString('cannot exceed the refundable balance', $responseExceeding->getBody());
    }

    public function testCancelExecutesFullRefundViaMercadoPagoClient(): void
    {
        $this->refundClient->setCustomResponse([
            'id' => 'ref-mp-999',
            'payment_id' => 'pay-mp-123456',
            'amount' => 1200000.0,
            'status' => 'approved',
        ]);

        $post = [
            'csrf_token' => 'test-csrf-token-xyz',
            'reason' => 'Guest emergency cancellation',
            'refund_type' => 'full',
        ];
        $request = (new Request('POST', '/reservations/res-1/cancel', post: $post, server: ['HTTP_HX_REQUEST' => 'true']))
            ->withAttribute('uid', 'res-1');

        $response = $this->controller->cancel($request, $this->session);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('reservationUpdated', $response->getHeaders()['HX-Trigger'] ?? null);
        $this->assertStringContainsString('modal-container', $response->getBody());
        $this->assertStringContainsString('drawer-container', $response->getBody());

        // Assert gateway dispatch
        $dispatches = $this->refundClient->getDispatchedRefunds();
        $this->assertCount(1, $dispatches);
        $this->assertSame('pay-mp-123456', $dispatches[0]['payment_id']);
        $this->assertSame(1200000.0, $dispatches[0]['amount']);

        // Assert database updates
        $res = $this->repository->findByUid('res-1');
        $this->assertNotNull($res);
        $this->assertSame('cancelled', $res->status->value);
        $this->assertSame('refunded', $res->paymentStatus);
        $this->assertEquals(1200000.0, $res->refundedAmount);

        // Assert refund recorded in reservation_refunds
        $refunds = $this->search->findRefunds('res-1');
        $this->assertCount(1, $refunds);
        $this->assertSame('ref-mp-999', $refunds[0]['mercadopago_refund_id']);
        $this->assertSame('admin_pms', $refunds[0]['source']);
        $this->assertEquals(1200000.0, (float) $refunds[0]['amount']);

        // Assert audit logs
        $stmt = $this->pdo->prepare('SELECT action FROM admin_audit_logs WHERE entity_id = :uid ORDER BY id ASC');
        $stmt->execute(['uid' => 'res-1']);
        $actions = $stmt->fetchAll(PDO::FETCH_COLUMN);
        $this->assertContains('reservation_cancelled', $actions);
        $this->assertContains('refund_issued', $actions);
    }

    public function testCancelHandlesGatewayFailureGracefullyWithRollback(): void
    {
        $this->refundClient->setShouldFail(
            shouldFail: true,
            statusCode: 428,
            message: 'Insufficient balance in account',
            errorCode: 'insufficient_money_for_refund'
        );

        $post = [
            'csrf_token' => 'test-csrf-token-xyz',
            'reason' => 'Guest requested cancel',
            'refund_type' => 'full',
        ];
        $request = (new Request('POST', '/reservations/res-1/cancel', post: $post, server: ['HTTP_HX_REQUEST' => 'true']))
            ->withAttribute('uid', 'res-1');

        $response = $this->controller->cancel($request, $this->session);

        $this->assertSame(422, $response->getStatusCode());
        $this->assertStringContainsString('Insufficient merchant balance', $response->getBody());

        // Verify reservation remained confirmed and no refund records were committed
        $res = $this->repository->findByUid('res-1');
        $this->assertNotNull($res);
        $this->assertSame('confirmed', $res->status->value);
        $this->assertEquals(0.0, $res->refundedAmount);

        $refunds = $this->search->findRefunds('res-1');
        $this->assertCount(0, $refunds);
    }

    public function testCancelOfflineReservationDoesNotDispatchToGateway(): void
    {
        $post = [
            'csrf_token' => 'test-csrf-token-xyz',
            'reason' => 'Manual reservation cancelled and refunded in cash',
            'refund_type' => 'partial',
            'refund_amount' => '400000',
        ];
        $request = (new Request('POST', '/reservations/res-3/cancel', post: $post, server: ['HTTP_HX_REQUEST' => 'true']))
            ->withAttribute('uid', 'res-3');

        $response = $this->controller->cancel($request, $this->session);

        $this->assertSame(200, $response->getStatusCode());

        // Zero gateway dispatches
        $this->assertCount(0, $this->refundClient->getDispatchedRefunds());

        // Database updated
        $res = $this->repository->findByUid('res-3');
        $this->assertNotNull($res);
        $this->assertSame('cancelled', $res->status->value);
        $this->assertEquals(400000.0, $res->refundedAmount);

        $refunds = $this->search->findRefunds('res-3');
        $this->assertCount(1, $refunds);
        $this->assertSame('admin_manual', $refunds[0]['source']);
        $this->assertNull($refunds[0]['mercadopago_refund_id']);
        $this->assertEquals(400000.0, (float) $refunds[0]['amount']);
    }

    public function testCancelWithPolicyRetentionZeroRefund(): void
    {
        $post = [
            'csrf_token' => 'test-csrf-token-xyz',
            'reason' => 'No-show strict policy retention',
            'refund_type' => 'none',
        ];
        $request = (new Request('POST', '/reservations/res-1/cancel', post: $post, server: ['HTTP_HX_REQUEST' => 'true']))
            ->withAttribute('uid', 'res-1');

        $response = $this->controller->cancel($request, $this->session);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertCount(0, $this->refundClient->getDispatchedRefunds());

        $res = $this->repository->findByUid('res-1');
        $this->assertNotNull($res);
        $this->assertSame('cancelled', $res->status->value);
        $this->assertEquals(0.0, $res->refundedAmount);

        // No refund record
        $refunds = $this->search->findRefunds('res-1');
        $this->assertCount(0, $refunds);

        // Audit log has cancellation, but no refund_issued
        $stmt = $this->pdo->prepare('SELECT action FROM admin_audit_logs WHERE entity_id = :uid');
        $stmt->execute(['uid' => 'res-1']);
        $actions = $stmt->fetchAll(PDO::FETCH_COLUMN);
        $this->assertContains('reservation_cancelled', $actions);
        $this->assertNotContains('refund_issued', $actions);
    }

    public function testNewReservationWithPreFillParametersRendersModalWithPrePopulatedFields(): void
    {
        $request = new Request('GET', '/reservations/new', query: [
            'property_id' => '1707',
            'check_in' => '2026-11-10',
            'check_out' => '2026-11-15',
            'source' => 'airbnb',
            'external_confirmation_code' => 'HM4XYZ1234',
            'channel_block_uid' => 'ical-abnb-999',
        ], server: ['HTTP_HX_REQUEST' => 'true']);

        $response = $this->controller->newReservation($request, $this->session);

        $this->assertSame(200, $response->getStatusCode());
        $body = $response->getBody();
        $this->assertStringContainsString('Onboard Airbnb Reservation', $body);
        $this->assertStringContainsString('value="HM4XYZ1234"', $body);
        $this->assertStringContainsString('value="ical-abnb-999"', $body);
        $this->assertStringContainsString('value="2026-11-10"', $body);
        $this->assertStringContainsString('value="2026-11-15"', $body);
        $this->assertStringContainsString('selected>Oceanview Grand 1707 (17th Fl)</option>', $body);
    }

    public function testQuotePreviewPassesAbsorbingSourceForAirbnb(): void
    {
        $this->ledger->expects($this->once())
            ->method('isAvailable')
            ->with('1707', '2026-11-10', '2026-11-15', null, 'airbnb')
            ->willReturn(true);

        $quote = new Quote(
            propertyId: '1707',
            checkIn: '2026-11-10',
            checkOut: '2026-11-15',
            nights: [],
            nightsCount: 5,
            accommodationTotalCop: 2000000.0,
            cleaningFeeCop: 100000.0,
            resortFeeCop: 50000.0,
            totalCop: 2150000.0,
            minimumStayRequired: 2,
            isValid: true
        );
        $this->quoteEngine->method('quote')->willReturn($quote);

        $request = new Request('POST', '/reservations/quote-preview', post: [
            'property_id' => '1707',
            'check_in' => '2026-11-10',
            'check_out' => '2026-11-15',
            'source' => 'airbnb',
        ]);

        $response = $this->controller->quotePreview($request);
        $this->assertSame(200, $response->getStatusCode());
        $this->assertStringContainsString('Airbnb booking absorbs overlapping Airbnb channel blocks', $response->getBody());
    }

    public function testCreateManualAirbnbReservationRequiresExternalConfirmationCode(): void
    {
        $request = new Request('POST', '/reservations/create-manual', post: [
            'property_id' => '1606',
            'check_in' => '2026-11-01',
            'check_out' => '2026-11-05',
            'source' => 'airbnb',
            'guest_name' => 'Airbnb Guest',
            'external_confirmation_code' => '',
        ]);

        $response = $this->controller->createManual($request, $this->session);

        $this->assertSame(422, $response->getStatusCode());
        $this->assertStringContainsString('Airbnb confirmation code is required', $response->getBody());
    }

    public function testCreateManualAirbnbReservationSuccessSavesWithResAbnbPrefixAndAbsorbsBlock(): void
    {
        $this->ledger->expects($this->once())
            ->method('isAvailable')
            ->with('1606', '2026-11-01', '2026-11-05', null, 'airbnb')
            ->willReturn(true);

        $request = new Request('POST', '/reservations/create-manual', post: [
            'property_id' => '1606',
            'check_in' => '2026-11-01',
            'check_out' => '2026-11-05',
            'source' => 'airbnb',
            'external_confirmation_code' => 'HM789XYZ',
            'channel_block_uid' => 'ical-airbnb-block-456',
            'guest_name' => 'Sarah Connor',
            'guest_email' => '',
            'guest_phone' => '',
            'total_price' => '',
            'pre_mark_registry' => '0',
            'send_confirmation_email' => '0',
        ], server: ['HTTP_HX_REQUEST' => 'true']);

        $response = $this->controller->createManual($request, $this->session);

        $this->assertSame(200, $response->getStatusCode());
        $headers = $response->getHeaders();
        $this->assertMatchesRegularExpression('#^/reservations/res-abnb-[a-f0-9]+$#', (string) ($headers['HX-Push-Url'] ?? ''));

        // Verify database entry
        $stmt = $this->pdo->prepare('SELECT * FROM reservations WHERE external_confirmation_code = :code');
        $stmt->execute(['code' => 'HM789XYZ']);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        $this->assertNotFalse($row);
        $this->assertStringStartsWith('res-abnb-', (string) $row['reservation_uid']);
        $this->assertSame('1606', $row['property_id']);
        $this->assertSame('Sarah Connor', $row['guest_name']);
        $this->assertSame('airbnb-hm789xyz@guest.oceanviewflats.com', $row['guest_email']);
        $this->assertSame('N/A', $row['guest_phone']);
        $this->assertSame('airbnb', $row['source']);
        $this->assertSame('HM789XYZ', $row['external_confirmation_code']);
        $this->assertSame('ical-airbnb-block-456', $row['channel_block_uid']);
        $this->assertSame(0.0, (float) $row['total_price']);
        $this->assertSame('external_ota', $row['payment_method_id']);
        $this->assertSame(0, (int) $row['registry_completed']);

        // Verify audit log
        $logStmt = $this->pdo->prepare('SELECT * FROM admin_audit_logs WHERE action = :action AND entity_id = :uid');
        $logStmt->execute(['action' => 'airbnb_reservation_created', 'uid' => $row['reservation_uid']]);
        $log = $logStmt->fetch(PDO::FETCH_ASSOC);
        $this->assertNotFalse($log);

        // Verify drawer body contains Airbnb chat dispatch Stage 1 (ADR 0001 gating)
        $body = $response->getBody();
        $this->assertStringContainsString('Airbnb Chat Dispatch', $body);
        $this->assertStringContainsString('Stage 1: Registry Required', $body);
        $this->assertStringContainsString('ADR 0001: Door PIN and Guide are locked until registry is completed', $body);
        $this->assertStringContainsString('/registry/es.html?code=' . $row['reservation_uid'], $body);
        $this->assertStringContainsString('/registry/index.html?code=' . $row['reservation_uid'], $body);
    }

    public function testAirbnbChatDispatchShowsStage2WhenRegistryCompleted(): void
    {
        $this->pdo->exec("
            INSERT INTO reservations (
                reservation_uid, property_id, guest_name, guest_email, guest_phone,
                check_in, check_out, total_price, status, source, registry_completed,
                door_code, external_confirmation_code, channel_block_uid, created_at
            ) VALUES (
                'res-abnb-test1234', '1707', 'Marcus Vance', 'marcus@example.com', '+1234567890',
                '2026-11-20', '2026-11-25', 1500000.00, 'confirmed', 'airbnb', 1,
                '0987654#', 'HM99887766', 'ical-uid-99', '2026-10-01 10:00:00'
            )
        ");

        $response = $this->executeShow('res-abnb-test1234', isHtmx: true, hxTarget: 'drawer-container');

        $this->assertSame(200, $response->getStatusCode());
        $body = $response->getBody();
        $this->assertStringContainsString('Airbnb Chat Dispatch', $body);
        $this->assertStringContainsString('Stage 2: Access Dispatched', $body);
        $this->assertStringContainsString('0987654#', $body);
        $this->assertStringContainsString('/guide/es.html?code=res-abnb-test1234', $body);
        $this->assertStringContainsString('/guide/index.html?code=res-abnb-test1234', $body);
        $this->assertStringContainsString('ADR 0001: Registry complete. Door PIN and Guide are unlocked', $body);
    }

    public function testShowRendersCondominiumClearanceSyncedBadgeAndNumber(): void
    {
        $this->pdo->exec("
            INSERT INTO condominium_clearances (
                reservation_uid, property_id, status, clearance_number, attempts, synced_at
            ) VALUES (
                'res-1', '1606', 'synced', 'CLR-98765', 1, '2026-10-01 12:30:00'
            )
        ");

        $response = $this->executeShow('res-1', isHtmx: true, hxTarget: 'drawer-container');
        $this->assertSame(200, $response->getStatusCode());
        $body = $response->getBody();

        $this->assertStringContainsString('Condominium Clearance', $body);
        $this->assertStringContainsString('Synced', $body);
        $this->assertStringContainsString('#CLR-98765', $body);
        $this->assertStringNotContainsString('Retry Condominium Clearance', $body);
    }

    public function testShowRendersCondominiumClearanceFailedBadgeErrorDetailsAndRetryButton(): void
    {
        $this->pdo->exec("
            INSERT INTO condominium_clearances (
                reservation_uid, property_id, status, error_message, attempts, last_attempt_at
            ) VALUES (
                'res-1', '1606', 'failed', 'Missing primary guest document number', 2, '2026-10-01 12:35:00'
            )
        ");

        $response = $this->executeShow('res-1', isHtmx: true, hxTarget: 'drawer-container');
        $this->assertSame(200, $response->getStatusCode());
        $body = $response->getBody();

        $this->assertStringContainsString('Condominium Clearance', $body);
        $this->assertStringContainsString('Failed', $body);
        $this->assertStringContainsString('Missing primary guest document number', $body);
        $this->assertStringContainsString('Retry Condominium Clearance', $body);
        $this->assertStringContainsString('/reservations/res-1/clearance-retry', $body);
        $this->assertStringContainsString('Dismiss Alert', $body);
        $this->assertStringContainsString('name="return_context" value="drawer"', $body);
        $this->assertStringContainsString('name="alert_id" value="clearance_res-1"', $body);
        $this->assertStringNotContainsString('Dismissed', $body);
    }

    public function testShowRendersDismissedBadgeWhenFailedClearanceAlertIsDismissedAndHidesDismissButtonOnConcludedStays(): void
    {
        $this->pdo->exec("
            INSERT INTO condominium_clearances (
                reservation_uid, property_id, status, error_message, attempts, last_attempt_at
            ) VALUES (
                'res-1', '1606', 'failed', 'Portal timeout while syncing', 3, '2026-10-01 12:35:00'
            )
        ");

        $dismissalRepo = new \OceanViewFlats\Domain\Reservation\Dashboard\PdoOperationalAlertDismissalRepository($this->pdo);
        $dismissalRepo->dismiss(
            alertId: 'clearance_res-1',
            alertType: 'failed_condominium_clearance',
            propertyId: '1606',
            reservationUid: 'res-1',
            dismissedBy: 1
        );

        $response = $this->executeShow('res-1', isHtmx: true, hxTarget: 'drawer-container');
        $this->assertSame(200, $response->getStatusCode());
        $body = $response->getBody();

        // Displays Dismissed status badge alongside Failed badge, error details, and Retry button
        $this->assertStringContainsString('Dismissed', $body);
        $this->assertStringContainsString('Failed', $body);
        $this->assertStringContainsString('Portal timeout while syncing', $body);
        $this->assertStringContainsString('Retry Condominium Clearance', $body);
        $this->assertStringNotContainsString('Dismiss Alert', $body);

        // Concluded stay (check_out <= today) with undismissed failed clearance does NOT render Dismiss Alert button
        $this->pdo->exec("
            INSERT INTO reservations (
                reservation_uid, property_id, guest_name, guest_email, guest_phone,
                check_in, check_out, total_price, status, source, registry_completed
            ) VALUES (
                'res-concluded-fail', '1606', 'Past Stay Guest', 'past@example.com', '+573001112233',
                '2025-01-01', '2025-01-05', 1000000.00, 'confirmed', 'web', 1
            );
            INSERT INTO condominium_clearances (
                reservation_uid, property_id, status, error_message, attempts
            ) VALUES (
                'res-concluded-fail', '1606', 'failed', 'Old error', 1
            );
        ");

        $concludedResponse = $this->executeShow('res-concluded-fail', isHtmx: true, hxTarget: 'drawer-container');
        $this->assertSame(200, $concludedResponse->getStatusCode());
        $concludedBody = $concludedResponse->getBody();
        $this->assertStringContainsString('Failed', $concludedBody);
        $this->assertStringContainsString('Retry Condominium Clearance', $concludedBody);
        $this->assertStringNotContainsString('Dismiss Alert', $concludedBody);
        $this->assertStringNotContainsString('Dismissed', $concludedBody);

        // Incomplete registry (registry_completed = 0) with failed clearance renders Dismiss Alert but NOT Retry Condominium Clearance
        $this->pdo->exec("
            INSERT INTO reservations (
                reservation_uid, property_id, guest_name, guest_email, guest_phone,
                check_in, check_out, total_price, status, source, registry_completed
            ) VALUES (
                'res-unregistered-fail', '1606', 'Unregistered Guest', 'unreg@example.com', '+573001112233',
                '2026-11-10', '2026-11-15', 1000000.00, 'confirmed', 'web', 0
            );
            INSERT INTO condominium_clearances (
                reservation_uid, property_id, status, error_message, attempts
            ) VALUES (
                'res-unregistered-fail', '1606', 'failed', 'Registry was reset after failure', 1
            );
        ");

        $unregisteredResponse = $this->executeShow('res-unregistered-fail', isHtmx: true, hxTarget: 'drawer-container');
        $this->assertSame(200, $unregisteredResponse->getStatusCode());
        $unregisteredBody = $unregisteredResponse->getBody();
        $this->assertStringContainsString('Failed', $unregisteredBody);
        $this->assertStringContainsString('Dismiss Alert', $unregisteredBody);
        $this->assertStringNotContainsString('Retry Condominium Clearance', $unregisteredBody);
        $this->assertStringNotContainsString('Sync Condominium Clearance', $unregisteredBody);
    }

    public function testRetryClearanceAndEditorActionsClearPriorAlertDismissals(): void
    {
        $dismissalRepo = new \OceanViewFlats\Domain\Reservation\Dashboard\PdoOperationalAlertDismissalRepository($this->pdo);
        $dismissalRepo->dismiss('clearance_res-1', 'failed_condominium_clearance', '1606', 'res-1', 1);
        $this->assertTrue($dismissalRepo->isDismissed('clearance_res-1'));

        $mockSync = $this->createMock(\OceanViewFlats\Domain\Fulfillment\CondominiumClearanceSyncInterface::class);
        $mockSync->expects($this->exactly(2))
            ->method('syncForReservation')
            ->willReturnOnConsecutiveCalls(
                new \OceanViewFlats\Domain\Fulfillment\CondominiumClearance(
                    reservationUid: 'res-1',
                    propertyId: '1606',
                    status: 'failed',
                    errorMessage: 'Still failing on portal',
                    attempts: 2
                ),
                new \OceanViewFlats\Domain\Fulfillment\CondominiumClearance(
                    reservationUid: 'res-1',
                    propertyId: '1606',
                    status: 'synced',
                    clearanceNumber: 'CLR-REARM-1',
                    attempts: 3,
                    syncedAt: '2026-10-16 14:00:00'
                )
            );

        $clearanceRepo = new \OceanViewFlats\Domain\Fulfillment\PdoCondominiumClearanceRepository($this->pdo);
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
            pdo: $this->pdo,
            clearanceRepo: $clearanceRepo,
            clearanceSync: $mockSync,
            alertDismissalRepo: $dismissalRepo
        );

        // 1. Retry clearance via HTMX when sync fails -> clears dismissal and re-renders drawer with Dismiss Alert button
        $retryFailReq = (new Request(
            method: 'POST',
            uri: '/reservations/res-1/clearance-retry',
            server: [
                'HTTP_HX_REQUEST' => 'true',
                'HTTP_HX_CSRF_TOKEN' => 'test-csrf-token-xyz',
            ]
        ))->withAttribute('uid', 'res-1');

        $retryFailResp = $controller->retryClearance($retryFailReq, $this->session);
        $this->assertSame(200, $retryFailResp->getStatusCode());
        $this->assertFalse($dismissalRepo->isDismissed('clearance_res-1'));
        $this->assertStringNotContainsString('Dismissed', $retryFailResp->getBody());
        $this->assertStringContainsString('Dismiss Alert', $retryFailResp->getBody());

        // 2. Dismiss again and updateReservation with sync_condominium_clearance=1 -> clears clearance_res-1 dismissal
        $dismissalRepo->dismiss('clearance_res-1', 'failed_condominium_clearance', '1606', 'res-1', 1);
        $this->assertTrue($dismissalRepo->isDismissed('clearance_res-1'));

        $this->ledger->method('isAvailable')->willReturn(true);
        $updateSyncReq = (new Request(
            method: 'POST',
            uri: '/reservations/res-1/edit',
            post: [
                'property_id' => '1606',
                'check_in' => '2026-11-01',
                'check_out' => '2026-11-05',
                'total_price' => '1200000',
                'max_guests' => '6',
                'guest_name' => 'Alice Smith',
                'guest_email' => 'alice@example.com',
                'guest_phone' => '+573001112233',
                'lang' => 'es',
                'sync_condominium_clearance' => '1',
                'csrf_token' => 'test-csrf-token-xyz',
            ],
            server: [
                'HTTP_HX_REQUEST' => 'true',
                'HTTP_HX_CSRF_TOKEN' => 'test-csrf-token-xyz',
            ]
        ))->withAttribute('uid', 'res-1');

        $updateSyncResp = $controller->updateReservation($updateSyncReq, $this->session);
        $this->assertSame(200, $updateSyncResp->getStatusCode());
        $this->assertFalse($dismissalRepo->isDismissed('clearance_res-1'));

        // 3. Dismiss registry_res-1 and updateReservation with reset_guest_registry=1 -> clears registry_res-1 dismissal
        $dismissalRepo->dismiss('registry_res-1', 'incomplete_guest_registry', '1606', 'res-1', 1);
        $this->assertTrue($dismissalRepo->isDismissed('registry_res-1'));

        $updateResetReq = (new Request(
            method: 'POST',
            uri: '/reservations/res-1/edit',
            post: [
                'property_id' => '1606',
                'check_in' => '2026-11-01',
                'check_out' => '2026-11-05',
                'total_price' => '1200000',
                'max_guests' => '6',
                'guest_name' => 'Alice Smith',
                'guest_email' => 'alice@example.com',
                'guest_phone' => '+573001112233',
                'lang' => 'es',
                'reset_guest_registry' => '1',
                'guest_count' => '0',
                'csrf_token' => 'test-csrf-token-xyz',
            ],
            server: [
                'HTTP_HX_REQUEST' => 'true',
                'HTTP_HX_CSRF_TOKEN' => 'test-csrf-token-xyz',
            ]
        ))->withAttribute('uid', 'res-1');

        $updateResetResp = $controller->updateReservation($updateResetReq, $this->session);
        $this->assertSame(200, $updateResetResp->getStatusCode());
        $this->assertFalse($dismissalRepo->isDismissed('registry_res-1'));

        // 4. When registry is incomplete (0 occupants), sync_condominium_clearance=1 does not execute sync and does NOT clear clearance dismissal
        $dismissalRepo->dismiss('clearance_res-1', 'failed_condominium_clearance', '1606', 'res-1', 1);
        $this->assertTrue($dismissalRepo->isDismissed('clearance_res-1'));

        $updateNoOccupantsSyncReq = (new Request(
            method: 'POST',
            uri: '/reservations/res-1/edit',
            post: [
                'property_id' => '1606',
                'check_in' => '2026-11-01',
                'check_out' => '2026-11-05',
                'total_price' => '1200000',
                'max_guests' => '6',
                'guest_name' => 'Alice Smith',
                'guest_email' => 'alice@example.com',
                'guest_phone' => '+573001112233',
                'lang' => 'es',
                'sync_condominium_clearance' => '1',
                'csrf_token' => 'test-csrf-token-xyz',
            ],
            server: [
                'HTTP_HX_REQUEST' => 'true',
                'HTTP_HX_CSRF_TOKEN' => 'test-csrf-token-xyz',
            ]
        ))->withAttribute('uid', 'res-1');

        $updateNoOccupantsSyncResp = $controller->updateReservation($updateNoOccupantsSyncReq, $this->session);
        $this->assertSame(200, $updateNoOccupantsSyncResp->getStatusCode());
        $this->assertTrue($dismissalRepo->isDismissed('clearance_res-1'));
    }

    public function testShowRendersCondominiumClearancePendingBadge(): void
    {
        $this->pdo->exec("
            INSERT INTO condominium_clearances (
                reservation_uid, property_id, status, attempts
            ) VALUES (
                'res-1', '1606', 'pending', 0
            )
        ");

        $response = $this->executeShow('res-1', isHtmx: true, hxTarget: 'drawer-container');
        $this->assertSame(200, $response->getStatusCode());
        $body = $response->getBody();

        $this->assertStringContainsString('Pending Sync', $body);
    }

    public function testShowRendersAwaitingGuestRegistryWhenNotCompleted(): void
    {
        $response = $this->executeShow('res-2', isHtmx: true, hxTarget: 'drawer-container');
        $this->assertSame(200, $response->getStatusCode());
        $body = $response->getBody();

        $this->assertStringContainsString('Awaiting Guest Registry', $body);
        $this->assertStringNotContainsString('Retry Condominium Clearance', $body);
    }

    public function testShowRendersAwaitingInitialClearanceSyncWhenRegistryCompletedWithoutClearance(): void
    {
        // res-1 has registry_completed = 1, but no condominium_clearances record
        $response = $this->executeShow('res-1', isHtmx: true, hxTarget: 'drawer-container');
        $this->assertSame(200, $response->getStatusCode());
        $body = $response->getBody();

        $this->assertStringContainsString('Awaiting Initial Clearance Sync', $body);
        $this->assertStringContainsString('Sync Condominium Clearance', $body);
    }

    public function testRetryClearanceRequiresActiveAdminSession(): void
    {
        $request = (new Request('POST', '/reservations/res-1/clearance-retry'))
            ->withAttribute('uid', 'res-1');
        $emptySession = [];

        $response = $this->controller->retryClearance($request, $emptySession);
        $this->assertSame(401, $response->getStatusCode());
        $data = json_decode($response->getBody(), true);
        $this->assertFalse($data['success']);
        $this->assertStringContainsString('Unauthorized', $data['error']);
    }

    public function testRetryClearanceValidatesCsrfToken(): void
    {
        $request = (new Request('POST', '/reservations/res-1/clearance-retry', post: ['csrf_token' => 'wrong-token']))
            ->withAttribute('uid', 'res-1');

        $response = $this->controller->retryClearance($request, $this->session);
        $this->assertSame(403, $response->getStatusCode());
        $data = json_decode($response->getBody(), true);
        $this->assertFalse($data['success']);
        $this->assertStringContainsString('Forbidden', $data['error']);
    }

    public function testRetryClearanceFailsWhenReservationNotFound(): void
    {
        $request = (new Request(
            method: 'POST',
            uri: '/reservations/res-nonexistent/clearance-retry',
            server: ['HTTP_X_CSRF_TOKEN' => 'test-csrf-token-xyz']
        ))->withAttribute('uid', 'res-nonexistent');

        $response = $this->controller->retryClearance($request, $this->session);
        $this->assertSame(404, $response->getStatusCode());
        $data = json_decode($response->getBody(), true);
        $this->assertFalse($data['success']);
        $this->assertSame('Reservation not found.', $data['error']);
    }

    public function testRetryClearanceFailsWhenGuestRegistryNotSubmitted(): void
    {
        // res-2 has no guest registry submitted
        $request = (new Request(
            method: 'POST',
            uri: '/reservations/res-2/clearance-retry',
            server: ['HTTP_X_CSRF_TOKEN' => 'test-csrf-token-xyz']
        ))->withAttribute('uid', 'res-2');

        $response = $this->controller->retryClearance($request, $this->session);
        $this->assertSame(400, $response->getStatusCode());
        $data = json_decode($response->getBody(), true);
        $this->assertFalse($data['success']);
        $this->assertStringContainsString('Guest registry must be submitted', $data['error']);
    }

    public function testRetryClearanceSucceedsAndRecordsAuditLog(): void
    {
        $mockTransport = $this->createMock(\OceanViewFlats\Domain\Fulfillment\HttpTransportInterface::class);
        $mockTransport->expects($this->exactly(2))
            ->method('post')
            ->willReturnOnConsecutiveCalls(
                [
                    'statusCode' => 200,
                    'body' => json_encode(['last_id' => '777']),
                    'headers' => [],
                    'cookies' => ['PHPSESSID' => 'test-session-123'],
                    'error' => null,
                ],
                [
                    'statusCode' => 302,
                    'body' => '',
                    'headers' => ['location' => 'https://example.com/ok.php'],
                    'cookies' => [],
                    'error' => null,
                ]
            );

        $clearanceRepo = new \OceanViewFlats\Domain\Fulfillment\PdoCondominiumClearanceRepository($this->pdo);
        $clearanceRepo->save(
            \OceanViewFlats\Domain\Fulfillment\CondominiumClearance::createPending('res-1', '1606')
                ->markFailed('Prior connection failure')
        );
        $clearanceSync = new \OceanViewFlats\Domain\Fulfillment\HuespedManagerClearanceSync(
            repository: $clearanceRepo,
            reservationRepository: $this->repository,
            searchAdapter: $this->search,
            transport: $mockTransport,
            auditLogger: $this->auditLogger
        );

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
            pdo: $this->pdo,
            clearanceRepo: $clearanceRepo,
            clearanceSync: $clearanceSync
        );

        $request = (new Request(
            method: 'POST',
            uri: '/reservations/res-1/clearance-retry',
            server: ['HTTP_X_CSRF_TOKEN' => 'test-csrf-token-xyz']
        ))->withAttribute('uid', 'res-1');

        $response = $controller->retryClearance($request, $this->session);
        $this->assertSame(200, $response->getStatusCode());
        $data = json_decode($response->getBody(), true);
        $this->assertTrue($data['success']);
        $this->assertSame('synced', $data['status']);
        $this->assertSame('777', $data['clearance_number']);

        $stmt = $this->pdo->prepare('SELECT * FROM admin_audit_logs WHERE action = "condominium_clearance_retry" AND entity_id = "res-1"');
        $stmt->execute();
        $log = $stmt->fetch(PDO::FETCH_ASSOC);
        $this->assertNotFalse($log);
        $after = json_decode((string) $log['payload_after'], true);
        $this->assertSame('synced', $after['status']);
        $this->assertSame('777', $after['clearance_number']);
        $this->assertSame(1, (int) $log['admin_user_id']);
    }

    public function testInitialClearanceSyncRecordsSyncAuditLog(): void
    {
        $mockTransport = $this->createMock(\OceanViewFlats\Domain\Fulfillment\HttpTransportInterface::class);
        $mockTransport->expects($this->exactly(2))
            ->method('post')
            ->willReturnOnConsecutiveCalls(
                [
                    'statusCode' => 200,
                    'body' => json_encode(['last_id' => '778']),
                    'headers' => [],
                    'cookies' => ['PHPSESSID' => 'test-session-123'],
                    'error' => null,
                ],
                [
                    'statusCode' => 302,
                    'body' => '',
                    'headers' => ['location' => 'https://example.com/ok.php'],
                    'cookies' => [],
                    'error' => null,
                ]
            );

        $clearanceRepo = new \OceanViewFlats\Domain\Fulfillment\PdoCondominiumClearanceRepository($this->pdo);
        $clearanceSync = new \OceanViewFlats\Domain\Fulfillment\HuespedManagerClearanceSync(
            repository: $clearanceRepo,
            reservationRepository: $this->repository,
            searchAdapter: $this->search,
            transport: $mockTransport,
            auditLogger: $this->auditLogger
        );

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
            pdo: $this->pdo,
            clearanceRepo: $clearanceRepo,
            clearanceSync: $clearanceSync
        );

        $request = (new Request(
            method: 'POST',
            uri: '/reservations/res-1/clearance-retry',
            server: ['HTTP_X_CSRF_TOKEN' => 'test-csrf-token-xyz']
        ))->withAttribute('uid', 'res-1');

        $response = $controller->retryClearance($request, $this->session);
        $this->assertSame(200, $response->getStatusCode());
        $data = json_decode($response->getBody(), true);
        $this->assertTrue($data['success']);
        $this->assertSame('synced', $data['status']);
        $this->assertSame('778', $data['clearance_number']);

        $stmt = $this->pdo->prepare('SELECT * FROM admin_audit_logs WHERE action = "condominium_clearance_sync" AND entity_id = "res-1"');
        $stmt->execute();
        $log = $stmt->fetch(PDO::FETCH_ASSOC);
        $this->assertNotFalse($log);
        $after = json_decode((string) $log['payload_after'], true);
        $this->assertSame('synced', $after['status']);
        $this->assertSame('778', $after['clearance_number']);
        $this->assertSame(1, (int) $log['admin_user_id']);
    }

    public function testRetryClearanceFailureCapturesErrorAndRecordsAuditLog(): void
    {
        $mockTransport = $this->createMock(\OceanViewFlats\Domain\Fulfillment\HttpTransportInterface::class);
        $mockTransport->expects($this->once())
            ->method('post')
            ->willReturn([
                'statusCode' => 500,
                'body' => 'National ID document expired for primary guest',
                'headers' => [],
                'cookies' => [],
                'error' => null,
            ]);

        $clearanceRepo = new \OceanViewFlats\Domain\Fulfillment\PdoCondominiumClearanceRepository($this->pdo);
        $clearanceRepo->save(
            \OceanViewFlats\Domain\Fulfillment\CondominiumClearance::createPending('res-1', '1606')
                ->markFailed('Prior connection failure')
        );
        $clearanceSync = new \OceanViewFlats\Domain\Fulfillment\HuespedManagerClearanceSync(
            repository: $clearanceRepo,
            reservationRepository: $this->repository,
            searchAdapter: $this->search,
            transport: $mockTransport,
            auditLogger: $this->auditLogger
        );

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
            pdo: $this->pdo,
            clearanceRepo: $clearanceRepo,
            clearanceSync: $clearanceSync
        );

        $request = (new Request(
            method: 'POST',
            uri: '/reservations/res-1/clearance-retry',
            server: ['HTTP_X_CSRF_TOKEN' => 'test-csrf-token-xyz']
        ))->withAttribute('uid', 'res-1');

        $response = $controller->retryClearance($request, $this->session);
        $this->assertSame(200, $response->getStatusCode());
        $data = json_decode($response->getBody(), true);
        $this->assertFalse($data['success']);
        $this->assertSame('failed', $data['status']);
        $this->assertStringContainsString('500', (string) $data['error']);

        $stmt = $this->pdo->prepare('SELECT * FROM admin_audit_logs WHERE action = "condominium_clearance_retry" AND entity_id = "res-1"');
        $stmt->execute();
        $log = $stmt->fetch(PDO::FETCH_ASSOC);
        $this->assertNotFalse($log);
    }

    public function testRetryClearanceReturnsHtmxDrawerResponse(): void
    {
        $mockSync = $this->createMock(\OceanViewFlats\Domain\Fulfillment\CondominiumClearanceSyncInterface::class);
        $mockSync->expects($this->once())
            ->method('syncForReservation')
            ->with('res-1', $this->isInstanceOf(\OceanViewFlats\Domain\Fulfillment\AdminContext::class))
            ->willReturn(new \OceanViewFlats\Domain\Fulfillment\CondominiumClearance(
                reservationUid: 'res-1',
                propertyId: '1606',
                status: 'synced',
                clearanceNumber: 'CLR-HTMX-123',
                attempts: 1,
                syncedAt: '2026-10-01 12:00:00'
            ));

        $clearanceRepo = new \OceanViewFlats\Domain\Fulfillment\PdoCondominiumClearanceRepository($this->pdo);

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
            pdo: $this->pdo,
            clearanceRepo: $clearanceRepo,
            clearanceSync: $mockSync
        );

        $request = (new Request(
            method: 'POST',
            uri: '/reservations/res-1/clearance-retry',
            server: [
                'HTTP_HX_REQUEST' => 'true',
                'HTTP_HX_CSRF_TOKEN' => 'test-csrf-token-xyz',
            ]
        ))->withAttribute('uid', 'res-1');

        $response = $controller->retryClearance($request, $this->session);
        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('reservationUpdated', $response->getHeader('HX-Trigger'));
        $body = $response->getBody();
        $this->assertStringContainsString('Condominium Clearance', $body);
        $this->assertStringContainsString('Synced', $body);
        $this->assertStringContainsString('#CLR-HTMX-123', $body);
    }

    public function testRetryClearanceReturnsHtmx404WhenReservationNotFound(): void
    {
        $request = (new Request(
            method: 'POST',
            uri: '/reservations/res-nonexistent/clearance-retry',
            server: [
                'HTTP_HX_REQUEST' => 'true',
                'HTTP_HX_CSRF_TOKEN' => 'test-csrf-token-xyz',
            ]
        ))->withAttribute('uid', 'res-nonexistent');

        $response = $this->controller->retryClearance($request, $this->session);
        $this->assertSame(404, $response->getStatusCode());
        $this->assertStringContainsString('Reservation not found.', $response->getBody());
    }

    public function testRetryClearanceReturnsHtmx400WhenGuestRegistryNotSubmitted(): void
    {
        $request = (new Request(
            method: 'POST',
            uri: '/reservations/res-2/clearance-retry',
            server: [
                'HTTP_HX_REQUEST' => 'true',
                'HTTP_HX_CSRF_TOKEN' => 'test-csrf-token-xyz',
            ]
        ))->withAttribute('uid', 'res-2');

        $response = $this->controller->retryClearance($request, $this->session);
        $this->assertSame(400, $response->getStatusCode());
        $this->assertStringContainsString('Guest registry must be submitted before condominium clearance can be synced.', $response->getBody());
    }

    public function testRetryClearanceReturns500WhenClearanceSyncServiceUnavailable(): void
    {
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
            pdo: null,
            clearanceRepo: null,
            clearanceSync: null
        );

        $request = (new Request(
            method: 'POST',
            uri: '/reservations/res-1/clearance-retry',
            server: ['HTTP_X_CSRF_TOKEN' => 'test-csrf-token-xyz']
        ))->withAttribute('uid', 'res-1');

        $response = $controller->retryClearance($request, $this->session);
        $this->assertSame(500, $response->getStatusCode());
        $data = json_decode($response->getBody(), true);
        $this->assertFalse($data['success']);
        $this->assertStringContainsString('service is unavailable', $data['error']);
    }

    private function seedDatabase(): void
    {
        $this->pdo->exec("
            INSERT INTO admin_users (id, email, password_hash, name, role)
            VALUES (1, 'operator@oceanviewflats.com', 'dummy_hash', 'Operator Manuel', 'admin');

            INSERT INTO reservations (
                reservation_uid, property_id, guest_name, guest_email, guest_phone,
                check_in, check_out, total_price, status, source, registry_completed,
                door_code, mercadopago_payment_id, payment_status, created_at
            ) VALUES 
            ('res-1', '1606', 'Alice Smith', 'alice@example.com', '+573001112233', '2026-11-01', '2026-11-05', 1200000.00, 'confirmed', 'web', 1, '1234#', 'pay-mp-123456', 'approved', '2026-09-01 12:00:00'),
            ('res-2', '1606', 'Bob Jones', 'bob@example.com', '+573004445566', '2026-10-10', '2026-10-15', 1500000.00, 'pending_payment', 'cash', 0, NULL, NULL, 'pending', '2026-09-02 12:00:00'),
            ('res-3', '1707', 'Carlos Gomez', 'carlos@example.com', '+573007778899', '2027-10-20', '2027-10-25', 1800000.00, 'confirmed', 'manual_override', 0, '5678#', NULL, 'offline', '2026-09-03 12:00:00');

            INSERT INTO admin_audit_logs (admin_user_id, action, entity_type, entity_id, payload_before, payload_after, ip_address, created_at)
            VALUES (1, 'pin_override', 'reservation', 'res-1', '{\"door_code\": \"1111#\"}', '{\"door_code\": \"1234#\"}', '127.0.0.1', '2026-09-29 10:00:00');

            INSERT INTO guest_registries (reservation_uid, property_id, check_in, check_out, guest_count, guests_payload, car_plates, car_model, ip_address)
            VALUES ('res-1', '1606', '2026-11-01', '2026-11-05', 1, '[{\"full_name\":\"Bob Smith\",\"doc_type\":\"CC\",\"doc_number\":\"12345678\",\"is_primary\":true}]', 'ABC-123', 'Toyota Corolla', '192.168.1.1');
        ");
    }

    public function testBuildFallbackLifecycleEngineWiresBookingFulfillment(): void
    {
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
            pdo: $this->pdo
        );

        $ref = new ReflectionClass($controller);
        $engine = $ref->getProperty('lifecycleEngine')->getValue($controller);
        $this->assertInstanceOf(ReservationLifecycleEngine::class, $engine);

        $engineRef = new ReflectionClass($engine);
        $publisher = $engineRef->getProperty('eventPublisherPort')->getValue($engine);
        $this->assertInstanceOf(TransactionalLifecycleEventPublisherAdapter::class, $publisher);

        $publisherRef = new ReflectionClass($publisher);
        $fulfillment = $publisherRef->getProperty('bookingFulfillment')->getValue($publisher);
        $this->assertInstanceOf(BookingFulfillment::class, $fulfillment);

        $fRef = new ReflectionClass($fulfillment);
        $sender = $fRef->getProperty('emailSender')->getValue($fulfillment);
        $this->assertSame($this->emailSender, $sender);
    }

    public function testConstructorInjectionOfBookingFulfillment(): void
    {
        $mockFulfillment = $this->createMock(BookingFulfillmentInterface::class);

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
            pdo: $this->pdo,
            bookingFulfillment: $mockFulfillment
        );

        $ref = new ReflectionClass($controller);
        $engine = $ref->getProperty('lifecycleEngine')->getValue($controller);
        $this->assertInstanceOf(ReservationLifecycleEngine::class, $engine);

        $engineRef = new ReflectionClass($engine);
        $publisher = $engineRef->getProperty('eventPublisherPort')->getValue($engine);
        $this->assertInstanceOf(TransactionalLifecycleEventPublisherAdapter::class, $publisher);

        $publisherRef = new ReflectionClass($publisher);
        $fulfillment = $publisherRef->getProperty('bookingFulfillment')->getValue($publisher);
        $this->assertSame($mockFulfillment, $fulfillment);

        $injectedFulfillment = $ref->getProperty('bookingFulfillment')->getValue($controller);
        $this->assertSame($mockFulfillment, $injectedFulfillment);
    }

    public function testRedactPiiEnforcesRoleGatingCsrfAndDrawerBadgeRendering(): void
    {
        // Seed a concluded reservation and a cancelled reservation
        $this->pdo->exec("
            INSERT INTO reservations (reservation_uid, property_id, guest_name, guest_email, guest_phone, check_in, check_out, total_price, status, door_code, notes)
            VALUES
                ('res-pii-concluded', '1606', 'Concluded Guest', 'concluded@example.com', '+573001112233', '2024-03-01', '2024-03-05', 1400000.00, 'confirmed', '0112233#', 'Check-out note'),
                ('res-pii-cancelled', '1707', 'Cancelled Guest', 'cancelled@example.com', '+573004445566', '2026-12-01', '2026-12-05', 1800000.00, 'cancelled', NULL, 'Cancelled note');
        ");

        // 1. Dossier drawer shows 'Redact Guest PII (Ley 1581)' button for admin on concluded & cancelled stays, but NOT on active stays
        $drawerConcluded = $this->executeShow('res-pii-concluded', true, 'drawer-container');
        $this->assertSame(200, $drawerConcluded->getStatusCode());
        $this->assertStringContainsString('Redact Guest PII (Ley 1581)', $drawerConcluded->getBody());
        $this->assertStringContainsString('/reservations/res-pii-concluded/redact-pii', $drawerConcluded->getBody());

        $drawerActive = $this->executeShow('res-1', true, 'drawer-container');
        $this->assertSame(200, $drawerActive->getStatusCode());
        $this->assertStringNotContainsString('Redact Guest PII (Ley 1581)', $drawerActive->getBody());

        // 2. Viewer role is rejected with 403 Forbidden and does not see the button in drawer
        $viewerSession = $this->session;
        $viewerSession['admin_user_role'] = 'viewer';

        $viewerDrawerReq = (new Request('GET', '/reservations/res-pii-concluded', server: [
            'HTTP_HX_REQUEST' => 'true',
            'HTTP_HX_TARGET' => 'drawer-container',
        ]))->withAttribute('uid', 'res-pii-concluded');
        $viewerDrawerRes = $this->controller->show($viewerDrawerReq, $viewerSession);
        $this->assertStringNotContainsString('Redact Guest PII (Ley 1581)', $viewerDrawerRes->getBody());

        $viewerPostReq = (new Request(
            'POST',
            '/reservations/res-pii-concluded/redact-pii',
            post: ['csrf_token' => 'test-csrf-token-xyz'],
            server: ['HTTP_HX_REQUEST' => 'true', 'HTTP_X_CSRF_TOKEN' => 'test-csrf-token-xyz']
        ))->withAttribute('uid', 'res-pii-concluded');
        $viewerPostRes = $this->controller->redactPii($viewerPostReq, $viewerSession);
        $this->assertSame(403, $viewerPostRes->getStatusCode());

        // 3. Invalid CSRF token is rejected with 403 Forbidden
        $badCsrfReq = (new Request(
            'POST',
            '/reservations/res-pii-concluded/redact-pii',
            post: ['csrf_token' => 'wrong-token'],
            server: ['HTTP_HX_REQUEST' => 'true']
        ))->withAttribute('uid', 'res-pii-concluded');
        $badCsrfRes = $this->controller->redactPii($badCsrfReq, $this->session);
        $this->assertSame(403, $badCsrfRes->getStatusCode());

        // 4. Active reservation is rejected with 422 Unprocessable Entity
        $activeRedactReq = (new Request(
            'POST',
            '/reservations/res-1/redact-pii',
            post: ['csrf_token' => 'test-csrf-token-xyz'],
            server: ['HTTP_HX_REQUEST' => 'true', 'HTTP_X_CSRF_TOKEN' => 'test-csrf-token-xyz']
        ))->withAttribute('uid', 'res-1');
        $activeRedactRes = $this->controller->redactPii($activeRedactReq, $this->session);
        $this->assertSame(422, $activeRedactRes->getStatusCode());
        $this->assertStringContainsString('Only concluded or cancelled reservations can be redacted', $activeRedactRes->getBody());

        // 5. Admin successfully redacts concluded reservation via HTMX and receives updated drawer with PII Redacted badge
        $adminRedactReq = (new Request(
            'POST',
            '/reservations/res-pii-concluded/redact-pii',
            post: ['csrf_token' => 'test-csrf-token-xyz'],
            server: ['HTTP_HX_REQUEST' => 'true', 'HTTP_X_CSRF_TOKEN' => 'test-csrf-token-xyz']
        ))->withAttribute('uid', 'res-pii-concluded');
        $adminRedactRes = $this->controller->redactPii($adminRedactReq, $this->session);
        $this->assertSame(200, $adminRedactRes->getStatusCode());
        $this->assertSame('reservationUpdated', $adminRedactRes->getHeaders()['HX-Trigger'] ?? null);
        $this->assertStringContainsString('PII Redacted', $adminRedactRes->getBody());
        $this->assertStringContainsString('Redacted Guest', $adminRedactRes->getBody());
        $this->assertStringNotContainsString('Redact Guest PII (Ley 1581)', $adminRedactRes->getBody());

        // 6. Superadmin successfully redacts cancelled reservation via non-HTMX POST (redirects to /reservations/{uid})
        $superadminSession = $this->session;
        $superadminSession['admin_user_role'] = 'superadmin';
        $superadminRedactReq = (new Request(
            'POST',
            '/reservations/res-pii-cancelled/redact-pii',
            post: ['csrf_token' => 'test-csrf-token-xyz']
        ))->withAttribute('uid', 'res-pii-cancelled');
        $superadminRedactRes = $this->controller->redactPii($superadminRedactReq, $superadminSession);
        $this->assertSame(302, $superadminRedactRes->getStatusCode());
        $this->assertSame('/reservations/res-pii-cancelled', $superadminRedactRes->getHeaders()['Location'] ?? null);
        $this->assertSame('Redacted Guest', $this->repository->findByUid('res-pii-cancelled')?->guestName);
    }
}
