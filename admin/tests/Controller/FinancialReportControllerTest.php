<?php

declare(strict_types=1);

namespace OceanViewFlats\Admin\Tests\Controller;

use DateTimeImmutable;
use OceanViewFlats\Admin\AdminApp;
use OceanViewFlats\Admin\Http\Request;
use OceanViewFlats\Admin\Tests\Support\AdminDatabaseTestHelper;
use OceanViewFlats\Domain\Finance\ExpenseCategory;
use OceanViewFlats\Domain\Finance\PdoOperationalExpenseRepository;
use PDO;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class FinancialReportControllerTest extends TestCase
{
    private PDO $pdo;
    private AdminApp $app;
    private PdoOperationalExpenseRepository $expenseRepo;

    /** @var array<string, mixed> */
    private array $adminSession;

    /** @var array<string, mixed> */
    private array $superadminSession;

    /** @var array<string, mixed> */
    private array $managerSession;

    /** @var array<string, mixed> */
    private array $viewerSession;

    protected function setUp(): void
    {
        $this->pdo = AdminDatabaseTestHelper::createDatabase();
        $this->expenseRepo = new PdoOperationalExpenseRepository($this->pdo);

        $this->pdo->exec("
            INSERT INTO admin_users (id, email, password_hash, name, role, is_active)
            VALUES
                (1, 'admin@oceanviewflats.com', 'hash', 'Manuel Admin', 'admin', 1),
                (2, 'super@oceanviewflats.com', 'hash', 'Super Dave', 'superadmin', 1),
                (3, 'manager@oceanviewflats.com', 'hash', 'Manager Bob', 'manager', 1),
                (4, 'viewer@oceanviewflats.com', 'hash', 'Viewer Charlie', 'viewer', 1);
        ");

        $this->pdo->exec("
            INSERT INTO reservations (reservation_uid, property_id, guest_name, guest_email, guest_phone, check_in, check_out, total_price, status, source)
            VALUES
                ('ovf_1606_stay', '1606', 'Markus Vance', 'markus@example.com', '+573001112233', '2026-10-05', '2026-10-11', 2880000, 'confirmed', 'web'),
                ('ovf_1707_stay', '1707', 'Elena Rostova', 'elena@example.com', '+573004445566', '2026-10-02', '2026-10-08', 3120000, 'confirmed', 'web');
        ");

        $this->app = AdminApp::createDefault($this->pdo, [
            'financial_report_now' => new DateTimeImmutable('2026-10-17 12:00:00', new \DateTimeZone('America/Bogota')),
        ]);

        $this->adminSession = [
            'admin_user_id' => 1,
            'admin_user_name' => 'Manuel Admin',
            'admin_user_email' => 'admin@oceanviewflats.com',
            'admin_user_role' => 'admin',
            'csrf_token' => 'valid-csrf-token',
        ];

        $this->superadminSession = [
            'admin_user_id' => 2,
            'admin_user_name' => 'Super Dave',
            'admin_user_email' => 'super@oceanviewflats.com',
            'admin_user_role' => 'superadmin',
            'csrf_token' => 'valid-csrf-token',
        ];

        $this->managerSession = [
            'admin_user_id' => 3,
            'admin_user_name' => 'Manager Bob',
            'admin_user_email' => 'manager@oceanviewflats.com',
            'admin_user_role' => 'manager',
            'csrf_token' => 'valid-csrf-token',
        ];

        $this->viewerSession = [
            'admin_user_id' => 4,
            'admin_user_name' => 'Viewer Charlie',
            'admin_user_email' => 'viewer@oceanviewflats.com',
            'admin_user_role' => 'viewer',
            'csrf_token' => 'valid-csrf-token',
        ];
    }

    public function testUnauthenticatedRequestsRedirectToLogin(): void
    {
        $session = [];
        $response = $this->app->handle(new Request('GET', '/financial-report'), $session);
        $this->assertSame(302, $response->getStatusCode());
        $this->assertSame('/login', $response->getHeader('Location'));

        $htmxSession = [];
        $htmxResponse = $this->app->handle(
            new Request('GET', '/financial-report/expenses/new', server: ['HTTP_HX_REQUEST' => 'true']),
            $htmxSession
        );
        $this->assertSame(401, $htmxResponse->getStatusCode());
        $this->assertSame('/login', $htmxResponse->getHeader('HX-Redirect'));
    }

    public function testNavigationLinkVisibilityAndRbacForAdminAndSuperadmin(): void
    {
        $adminResp = $this->app->handle(new Request('GET', '/financial-report'), $this->adminSession);
        $this->assertSame(200, $adminResp->getStatusCode());
        $adminBody = $adminResp->getBody();
        $this->assertStringContainsString('Financial Report', $adminBody);
        $this->assertStringContainsString('Settlement Currency: COP', $adminBody);
        $this->assertStringContainsString('href="/financial-report" class="px-3 py-2 rounded-md text-sm font-medium', $adminBody);
        $this->assertStringContainsString('href="/financial-report" class="block px-3 py-2 rounded-md text-base font-medium', $adminBody);

        $superResp = $this->app->handle(new Request('GET', '/financial-report'), $this->superadminSession);
        $this->assertSame(200, $superResp->getStatusCode());
        $this->assertStringContainsString('href="/financial-report" class="px-3 py-2 rounded-md text-sm font-medium', $superResp->getBody());
        $this->assertStringContainsString('href="/financial-report" class="block px-3 py-2 rounded-md text-base font-medium', $superResp->getBody());

        // Manager and Viewer do not see the Financial Report link in layout navigation
        $managerDash = $this->app->handle(new Request('GET', '/rates'), $this->managerSession);
        $this->assertSame(200, $managerDash->getStatusCode());
        $this->assertStringNotContainsString('href="/financial-report"', $managerDash->getBody());

        $viewerDash = $this->app->handle(new Request('GET', '/rates'), $this->viewerSession);
        $this->assertSame(200, $viewerDash->getStatusCode());
        $this->assertStringNotContainsString('href="/financial-report"', $viewerDash->getBody());
    }

    /**
     * @return array<string, array{0: string, 1: string}>
     */
    public static function protectedFinancialRoutesProvider(): array
    {
        return [
            'GET /financial-report' => ['GET', '/financial-report'],
            'GET /financial-report/export/expenses.csv' => ['GET', '/financial-report/export/expenses.csv'],
            'GET /financial-report/export/reservations.csv' => ['GET', '/financial-report/export/reservations.csv'],
            'GET /financial-report/expenses/new' => ['GET', '/financial-report/expenses/new'],
            'POST /financial-report/expenses' => ['POST', '/financial-report/expenses'],
            'GET /financial-report/expenses/1/edit' => ['GET', '/financial-report/expenses/1/edit'],
            'POST /financial-report/expenses/1' => ['POST', '/financial-report/expenses/1'],
            'DELETE /financial-report/expenses/1' => ['DELETE', '/financial-report/expenses/1'],
            'POST /financial-report/expenses/1/delete' => ['POST', '/financial-report/expenses/1/delete'],
        ];
    }

    #[DataProvider('protectedFinancialRoutesProvider')]
    public function testManagerAndViewerRolesReceive403OnAllFinancialReportRoutes(string $method, string $uri): void
    {
        $post = $method !== 'GET' ? ['csrf_token' => 'valid-csrf-token'] : [];

        // Full page request for Manager
        $respManager = $this->app->handle(new Request($method, $uri, post: $post), $this->managerSession);
        $this->assertSame(403, $respManager->getStatusCode());
        $this->assertStringContainsString('Access Restricted', $respManager->getBody());

        // Full page request for Viewer
        $respViewer = $this->app->handle(new Request($method, $uri, post: $post), $this->viewerSession);
        $this->assertSame(403, $respViewer->getStatusCode());
        $this->assertStringContainsString('Access Restricted', $respViewer->getBody());

        // HTMX request for Manager
        $respHtmx = $this->app->handle(
            new Request($method, $uri, post: $post, server: ['HTTP_HX_REQUEST' => 'true']),
            $this->managerSession
        );
        $this->assertSame(403, $respHtmx->getStatusCode());
        $this->assertStringContainsString('Access Restricted', $respHtmx->getBody());
        $this->assertStringNotContainsString('<!DOCTYPE html>', $respHtmx->getBody());
    }

    public function testCreateEditDuplicateAndDeleteOperationalExpensesAcrossAllCategoriesAndProperties(): void
    {
        // 1. Create an expense for each of the 8 canonical categories across 1606, 1707, and shared
        $properties = ['1606', '1707', 'shared'];
        $createdIds = [];

        foreach (ExpenseCategory::cases() as $index => $category) {
            $prop = $properties[$index % 3];
            $resUid = $prop === '1606' && $category === ExpenseCategory::CLEANING ? 'ovf_1606_stay' : '';

            $createReq = new Request(
                method: 'POST',
                uri: '/financial-report/expenses',
                post: [
                    'csrf_token' => 'valid-csrf-token',
                    'property_id' => $prop,
                    'category' => $category->value,
                    'title' => 'Expense for ' . $category->label(),
                    'amount' => (string) ((1 + $index) * 100000),
                    'expense_date' => sprintf('2026-10-%02d', 2 + $index),
                    'vendor_name' => 'Vendor ' . $category->value,
                    'reference_code' => 'REF-' . strtoupper($category->value),
                    'reservation_uid' => $resUid,
                    'notes' => 'Note for ' . $category->value,
                ],
                server: ['HTTP_HX_REQUEST' => 'true']
            );

            $createResp = $this->app->handle($createReq, $this->adminSession);
            $this->assertSame(200, $createResp->getStatusCode());
            $this->assertSame('expenseSaved', $createResp->getHeader('HX-Trigger'));
            $this->assertStringContainsString('Expense for ' . htmlspecialchars($category->label(), ENT_QUOTES, 'UTF-8'), $createResp->getBody());
            $this->assertStringContainsString('<div id="modal-container" hx-swap-oob="innerHTML"></div>', $createResp->getBody());
        }

        $allExpenses = $this->expenseRepo->listForPeriod('2026-10-01', '2026-11-01', 'all');
        $this->assertCount(8, $allExpenses);

        foreach ($allExpenses as $exp) {
            $this->assertMatchesRegularExpression('/^exp_[0-9a-f]{16}$/', $exp->expenseUid);
            $createdIds[] = (int) $exp->id;
        }

        // 2. Test Duplicate workflow (?duplicate_from={id})
        $targetExpense = $allExpenses[0];
        $dupReq = new Request(
            method: 'GET',
            uri: '/financial-report/expenses/new',
            query: ['duplicate_from' => (string) $targetExpense->id],
            server: ['HTTP_HX_REQUEST' => 'true']
        );
        $dupResp = $this->app->handle($dupReq, $this->adminSession);
        $this->assertSame(200, $dupResp->getStatusCode());
        $dupBody = $dupResp->getBody();

        $this->assertStringContainsString('Duplicate Operational Expense', $dupBody);
        $this->assertStringContainsString('value="' . htmlspecialchars($targetExpense->title, ENT_QUOTES, 'UTF-8') . '"', $dupBody);
        $this->assertStringContainsString('value="' . (int) $targetExpense->amount . '"', $dupBody);
        $this->assertStringContainsString('value="' . htmlspecialchars((string) $targetExpense->vendorName, ENT_QUOTES, 'UTF-8') . '"', $dupBody);
        $this->assertStringContainsString(htmlspecialchars((string) $targetExpense->notes, ENT_QUOTES, 'UTF-8'), $dupBody);
        // Expense date defaults to today in America/Bogota (2026-10-17) and reference_code is cleared
        $this->assertStringContainsString('name="expense_date"' . "\n" . '                           required' . "\n" . '                           value="2026-10-17"', $dupBody);
        $this->assertStringNotContainsString((string) $targetExpense->referenceCode, $dupBody);

        // 3. Test ?reservation_uid={uid} pre-fill on new expense modal
        $resPrefillReq = new Request(
            method: 'GET',
            uri: '/financial-report/expenses/new',
            query: ['reservation_uid' => 'ovf_1707_stay'],
            server: ['HTTP_HX_REQUEST' => 'true']
        );
        $resPrefillResp = $this->app->handle($resPrefillReq, $this->adminSession);
        $this->assertSame(200, $resPrefillResp->getStatusCode());
        $this->assertStringContainsString('value="ovf_1707_stay"', $resPrefillResp->getBody());
        $this->assertStringContainsString('<option value="1707" selected>', $resPrefillResp->getBody());

        // 4. Test Edit modal and Update across a past date
        $editId = (int) $targetExpense->id;
        $editModalResp = $this->app->handle(
            new Request('GET', "/financial-report/expenses/{$editId}/edit", server: ['HTTP_HX_REQUEST' => 'true']),
            $this->adminSession
        );
        $this->assertSame(200, $editModalResp->getStatusCode());
        $this->assertStringContainsString('Edit Operational Expense', $editModalResp->getBody());

        $updateReq = new Request(
            method: 'POST',
            uri: "/financial-report/expenses/{$editId}",
            post: [
                'csrf_token' => 'valid-csrf-token',
                'property_id' => '1707',
                'category' => 'maintenance',
                'title' => 'Retroactive AC Repair 1707',
                'amount' => '450000',
                'expense_date' => '2026-03-15',
                'vendor_name' => 'ClimaCaribe SAS',
                'reference_code' => 'MNT-2026-03',
                'reservation_uid' => '',
                'notes' => 'Updated to past date',
            ],
            server: ['HTTP_HX_REQUEST' => 'true']
        );
        $updateResp = $this->app->handle($updateReq, $this->adminSession);
        $this->assertSame(200, $updateResp->getStatusCode());
        $this->assertStringContainsString('Retroactive AC Repair 1707', $updateResp->getBody());

        $reloaded = $this->expenseRepo->findById($editId);
        $this->assertNotNull($reloaded);
        $this->assertSame('1707', $reloaded->propertyId);
        $this->assertSame(ExpenseCategory::MAINTENANCE, $reloaded->category);
        $this->assertSame('Retroactive AC Repair 1707', $reloaded->title);
        $this->assertSame(450000.0, $reloaded->amount);
        $this->assertSame('2026-03-15', $reloaded->expenseDate);

        // 5. Test Delete via DELETE and POST .../delete
        $deleteResp1 = $this->app->handle(
            new Request(
                method: 'DELETE',
                uri: "/financial-report/expenses/{$editId}",
                server: [
                    'HTTP_HX_REQUEST' => 'true',
                    'HTTP_HX_CSRF_TOKEN' => 'valid-csrf-token',
                ]
            ),
            $this->adminSession
        );
        $this->assertSame(200, $deleteResp1->getStatusCode());
        $this->assertNull($this->expenseRepo->findById($editId));

        $secondDeleteId = (int) $allExpenses[1]->id;
        $deleteResp2 = $this->app->handle(
            new Request(
                method: 'POST',
                uri: "/financial-report/expenses/{$secondDeleteId}/delete",
                post: ['csrf_token' => 'valid-csrf-token'],
                server: ['HTTP_HX_REQUEST' => 'true']
            ),
            $this->adminSession
        );
        $this->assertSame(200, $deleteResp2->getStatusCode());
        $this->assertNull($this->expenseRepo->findById($secondDeleteId));
    }

    public function testValidationErrorsReturn422AndPreserve100PercentOfOperatorInput(): void
    {
        // Case 1: 'shared' property with a reservation_uid
        $sharedWithResReq = new Request(
            method: 'POST',
            uri: '/financial-report/expenses',
            post: [
                'csrf_token' => 'valid-csrf-token',
                'property_id' => 'shared',
                'category' => 'cleaning',
                'title' => 'Shared Turnover Attempt',
                'amount' => '185000',
                'expense_date' => '2026-10-12',
                'vendor_name' => 'Marta Ospina',
                'reference_code' => 'CLN-901',
                'reservation_uid' => 'ovf_1606_stay',
                'notes' => 'Preserve this exact note text',
            ],
            server: ['HTTP_HX_REQUEST' => 'true']
        );

        $resp1 = $this->app->handle($sharedWithResReq, $this->adminSession);
        $this->assertSame(422, $resp1->getStatusCode());
        $this->assertSame('#modal-container', $resp1->getHeader('HX-Retarget'));
        $this->assertSame('innerHTML', $resp1->getHeader('HX-Reswap'));
        $body1 = $resp1->getBody();
        $this->assertStringContainsString('Stay-linked expenses cannot be assigned to Shared Portfolio', $body1);
        $this->assertStringContainsString('<option value="shared" selected>', $body1);
        $this->assertStringContainsString('value="Shared Turnover Attempt"', $body1);
        $this->assertStringContainsString('value="185000"', $body1);
        $this->assertStringContainsString('value="2026-10-12"', $body1);
        $this->assertStringContainsString('value="Marta Ospina"', $body1);
        $this->assertStringContainsString('value="CLN-901"', $body1);
        $this->assertStringContainsString('value="ovf_1606_stay"', $body1);
        $this->assertStringContainsString('Preserve this exact note text', $body1);

        // Case 2: Non-existent reservation_uid
        $missingResReq = new Request(
            method: 'POST',
            uri: '/financial-report/expenses',
            post: [
                'csrf_token' => 'valid-csrf-token',
                'property_id' => '1606',
                'category' => 'cleaning',
                'title' => 'Missing Stay Turnover',
                'amount' => '160000',
                'expense_date' => '2026-10-14',
                'vendor_name' => 'Vendor X',
                'reference_code' => 'REF-X',
                'reservation_uid' => 'ovf_nonexistent_999',
                'notes' => 'Notes for missing stay',
            ],
            server: ['HTTP_HX_REQUEST' => 'true']
        );

        $resp2 = $this->app->handle($missingResReq, $this->adminSession);
        $this->assertSame(422, $resp2->getStatusCode());
        $body2 = $resp2->getBody();
        $this->assertStringContainsString('Reservation UID &quot;ovf_nonexistent_999&quot; was not found in the ledger.', $body2);
        $this->assertStringContainsString('value="Missing Stay Turnover"', $body2);
        $this->assertStringContainsString('value="ovf_nonexistent_999"', $body2);
        $this->assertStringContainsString('Notes for missing stay', $body2);

        // Case 3: Property mismatch between expense (1606) and reservation (1707)
        $mismatchReq = new Request(
            method: 'POST',
            uri: '/financial-report/expenses',
            post: [
                'csrf_token' => 'valid-csrf-token',
                'property_id' => '1606',
                'category' => 'maintenance',
                'title' => 'Mismatched Property Expense',
                'amount' => '310000',
                'expense_date' => '2026-10-15',
                'vendor_name' => 'Tech Repair',
                'reference_code' => 'INV-771',
                'reservation_uid' => 'ovf_1707_stay',
                'notes' => 'Mismatched unit note',
            ],
            server: ['HTTP_HX_REQUEST' => 'true']
        );

        $resp3 = $this->app->handle($mismatchReq, $this->adminSession);
        $this->assertSame(422, $resp3->getStatusCode());
        $body3 = $resp3->getBody();
        $this->assertStringContainsString('Reservation &quot;ovf_1707_stay&quot; belongs to Property 1707, not Property 1606.', $body3);
        $this->assertStringContainsString('value="Mismatched Property Expense"', $body3);
        $this->assertStringContainsString('value="310000"', $body3);
        $this->assertStringContainsString('value="Tech Repair"', $body3);
        $this->assertStringContainsString('value="INV-771"', $body3);
        $this->assertStringContainsString('value="ovf_1707_stay"', $body3);
        $this->assertStringContainsString('Mismatched unit note', $body3);
    }

    public function testAuditLogRecordingFilteringAndInspectorDeepLink(): void
    {
        // 1. Create an expense
        $this->app->handle(new Request(
            method: 'POST',
            uri: '/financial-report/expenses',
            post: [
                'csrf_token' => 'valid-csrf-token',
                'property_id' => '1606',
                'category' => 'hoa_admin',
                'title' => 'HOA Oct 2026',
                'amount' => '720000',
                'expense_date' => '2026-10-02',
                'vendor_name' => 'Edificio Salguero Sunset',
                'reference_code' => 'HOA-101',
                'reservation_uid' => '',
                'notes' => 'Initial entry',
            ],
            server: ['HTTP_HX_REQUEST' => 'true', 'REMOTE_ADDR' => '190.10.20.30']
        ), $this->adminSession);

        $expenses = $this->expenseRepo->listForPeriod('2026-10-01', '2026-11-01', 'all');
        $this->assertCount(1, $expenses);
        $expense = $expenses[0];
        $expenseId = (int) $expense->id;
        $expenseUid = $expense->expenseUid;

        // 2. Update the expense
        $this->app->handle(new Request(
            method: 'POST',
            uri: "/financial-report/expenses/{$expenseId}",
            post: [
                'csrf_token' => 'valid-csrf-token',
                'property_id' => '1606',
                'category' => 'hoa_admin',
                'title' => 'HOA Oct 2026 (Updated)',
                'amount' => '740000',
                'expense_date' => '2026-10-02',
                'vendor_name' => 'Edificio Salguero Sunset',
                'reference_code' => 'HOA-101-REV',
                'reservation_uid' => '',
                'notes' => 'Updated entry',
            ],
            server: ['HTTP_HX_REQUEST' => 'true', 'REMOTE_ADDR' => '190.10.20.30']
        ), $this->adminSession);

        // 3. Delete the expense
        $this->app->handle(new Request(
            method: 'POST',
            uri: "/financial-report/expenses/{$expenseId}/delete",
            post: ['csrf_token' => 'valid-csrf-token'],
            server: ['HTTP_HX_REQUEST' => 'true', 'REMOTE_ADDR' => '190.10.20.30']
        ), $this->adminSession);

        // 4. Verify all 3 audit log records exist in admin_audit_logs with accurate before/after diffs
        $stmt = $this->pdo->prepare("
            SELECT id, action, entity_type, entity_id, payload_before, payload_after
            FROM admin_audit_logs
            WHERE entity_type = 'operational_expense' AND entity_id = :uid
            ORDER BY id ASC
        ");
        $stmt->execute(['uid' => $expenseUid]);
        $logs = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $this->assertCount(3, $logs);

        // Created log
        $this->assertSame('operational_expense_created', $logs[0]['action']);
        $this->assertNull($logs[0]['payload_before']);
        $afterCreate = json_decode((string) $logs[0]['payload_after'], true);
        $this->assertSame('HOA Oct 2026', $afterCreate['title']);
        $this->assertEquals(720000.0, $afterCreate['amount']);

        // Updated log
        $this->assertSame('operational_expense_updated', $logs[1]['action']);
        $beforeUpdate = json_decode((string) $logs[1]['payload_before'], true);
        $afterUpdate = json_decode((string) $logs[1]['payload_after'], true);
        $this->assertSame('HOA Oct 2026', $beforeUpdate['title']);
        $this->assertSame('HOA Oct 2026 (Updated)', $afterUpdate['title']);
        $this->assertEquals(720000.0, $beforeUpdate['amount']);
        $this->assertEquals(740000.0, $afterUpdate['amount']);

        // Deleted log
        $this->assertSame('operational_expense_deleted', $logs[2]['action']);
        $beforeDelete = json_decode((string) $logs[2]['payload_before'], true);
        $this->assertSame('HOA Oct 2026 (Updated)', $beforeDelete['title']);
        $this->assertNull($logs[2]['payload_after']);

        // 5. Verify /audit-logs filtering by cat:expenses and Inspector Drawer deep link
        $auditListResp = $this->app->handle(
            new Request('GET', '/audit-logs', query: ['action' => 'cat:expenses']),
            $this->adminSession
        );
        $this->assertSame(200, $auditListResp->getStatusCode());
        $auditListBody = $auditListResp->getBody();
        $this->assertStringContainsString('All Operational Expenses', $auditListBody);
        $this->assertStringContainsString('Operational Expense Created', $auditListBody);
        $this->assertStringContainsString($expenseUid, $auditListBody);
        $this->assertStringContainsString('/financial-report?tab=expenses&amp;search=' . urlencode($expenseUid), $auditListBody);

        $logId = (int) $logs[1]['id'];
        $drawerResp = $this->app->handle(
            new Request(
                'GET',
                "/audit-logs/{$logId}",
                server: ['HTTP_HX_REQUEST' => 'true', 'HTTP_HX_TARGET' => 'drawer-container']
            ),
            $this->adminSession
        );
        $this->assertSame(200, $drawerResp->getStatusCode());
        $this->assertStringContainsString(
            '/financial-report?tab=expenses&amp;search=' . urlencode($expenseUid),
            $drawerResp->getBody()
        );
    }

    public function testAccrualProrationScorecardsUnpricedAirbnbBannerAnd5050SharedAllocationInViews(): void
    {
        // Clear baseline reservations and seed rich October 2026 scenario
        $this->pdo->exec('DELETE FROM reservations');
        $this->pdo->exec('DELETE FROM calendar_blocks');
        $this->pdo->exec('DELETE FROM operational_expenses');

        // 1. Cross-month web stay: Sep 28 -> Oct 03 (5 nights total, 2 nights in Oct = 40% proration)
        // Total: 2,250,000 COP => Oct attributed: 900,000 COP (2 occupied & priced nights)
        // 2. Full-month web stay on 1606: Oct 05 -> Oct 11 (6 nights, 2,880,000 COP)
        // 3. Direct manual stay on 1707 with partial refund: Oct 10 -> Oct 15 (5 nights, total 2,450,000, refunded 200,000 => net 2,250,000)
        // 4. Unpriced ($0 COP) Airbnb stay on 1707: Oct 22 -> Oct 26 (4 occupied nights, 0 priced nights)
        // 5. Settled cancelled reservation on 1707: Oct 17 -> Oct 20 (total 1,500,000, refunded 750,000 => 750,000 Policy Retention, 0 occupied nights)
        // 6. Unpaid cancelled pending hold on 1606: Oct 12 -> Oct 14 (total 1,800,000, payment_status='pending' => excluded!)
        // 7. Confirmed owner_stay on 1707: Oct 28 -> Oct 31 (3 nights deducted from capacity)
        $this->pdo->exec("
            INSERT INTO reservations (
                reservation_uid, property_id, guest_name, guest_email, guest_phone,
                check_in, check_out, total_price, refunded_amount, status, payment_status, source
            ) VALUES
                ('ovf_cross_sep_oct', '1606', 'Camila Restrepo', 'camila@example.com', '+573001112233', '2026-09-28', '2026-10-03', 2250000, 0, 'confirmed', 'approved', 'web'),
                ('ovf_oct_1606', '1606', 'Markus Vance', 'markus@example.com', '+573001112234', '2026-10-05', '2026-10-11', 2880000, 0, 'confirmed', 'approved', 'web'),
                ('res-man-1707', '1707', 'Lucia Fernandez', 'lucia@example.com', '+573001112235', '2026-10-10', '2026-10-15', 2450000, 200000, 'confirmed', 'partially_refunded', 'cash'),
                ('res-abnb-zero', '1707', 'Sophie Laurent', 'sophie@guest.oceanviewflats.com', '+336000000', '2026-10-22', '2026-10-26', 0, 0, 'confirmed', 'approved', 'airbnb'),
                ('ovf_cancelled_ret', '1707', 'Carlos Mendoza', 'carlos@example.com', '+573001112236', '2026-10-17', '2026-10-20', 1500000, 750000, 'cancelled', 'partially_refunded', 'web'),
                ('ovf_unpaid_void', '1606', 'Unpaid Void Guest', 'void@example.com', '+573001112237', '2026-10-12', '2026-10-14', 1800000, 0, 'cancelled', 'pending', 'web'),
                ('res-owner-1707', '1707', 'Owner Personal Stay', 'owner@oceanviewflats.com', '+573000000000', '2026-10-28', '2026-10-31', 0, 0, 'confirmed', 'approved', 'owner_stay'),
                ('ovf_oct25_yoy', '1606', 'YoY Guest', 'yoy@example.com', '+573009999999', '2025-10-05', '2025-10-10', 3390000, 0, 'confirmed', 'approved', 'web');
        ");

        // Maintenance block on 1606: Oct 29 -> Oct 31 (2 nights deducted from capacity)
        $this->pdo->exec("
            INSERT INTO calendar_blocks (property_id, start_date, end_date, reason, created_by)
            VALUES ('1606', '2026-10-29', '2026-10-31', 'Deep AC servicing', 1);
        ");

        // Operational expenses in Oct 2026:
        // Direct 1606: 720,000 COP; Direct 1707: 480,000 COP; Shared: 640,000 COP (320,000 per property)
        $this->pdo->exec("
            INSERT INTO operational_expenses (expense_uid, property_id, category, title, amount, expense_date, vendor_name, reference_code, created_by)
            VALUES
                ('exp_direct_1606', '1606', 'hoa_admin', 'HOA 1606 Oct', 720000, '2026-10-02', 'Edificio Salguero', 'ADM-1606', 1),
                ('exp_direct_1707', '1707', 'utilities', 'Utilities 1707 Oct', 480000, '2026-10-06', 'Air-e', 'UTIL-1707', 1),
                ('exp_shared_pool', 'shared', 'supplies', 'Bulk Hotel Linens', 640000, '2026-10-09', 'Makro', 'FAC-99201', 1);
        ");

        // 1. Portfolio View (All Properties, this_month):
        // Active Stay Revenue = 900,000 + 2,880,000 + 2,250,000 = 6,030,000 COP
        // Policy Retention = 750,000 COP (Unpaid void hold contributes 0!)
        // Gross Revenue = 6,780,000 COP
        // Refunded = 200,000 + 750,000 = 950,000 COP
        // Total Expenses = 720,000 + 480,000 + 640,000 = 1,840,000 COP
        // NOI = 6,780,000 - 1,840,000 = 4,940,000 COP
        // Capacity: 62 total calendar nights - 5 deducted (2 maint + 3 owner) = 57 Available Nights
        // Occupied Nights: 2 + 6 + 5 + 4 ($0 Airbnb) = 17 Occupied Nights (17 / 57 = 29.8%)
        // Priced Occupied Nights: 2 + 6 + 5 = 13 Priced Nights (ADR = 6,030,000 / 13 = 463,846 COP)
        $respAll = $this->app->handle(new Request('GET', '/financial-report'), $this->adminSession);
        $this->assertSame(200, $respAll->getStatusCode());
        $bodyAll = $respAll->getBody();

        // Verify hx-push-url="true" on filter form
        $this->assertStringContainsString('hx-push-url="true"', $bodyAll);

        // Verify Row 1 P&L Hero Cards
        $this->assertStringContainsString('Gross Revenue (Realized)', $bodyAll);
        $this->assertStringContainsString('$ 6.780.000 COP', $bodyAll);
        $this->assertStringContainsString('$ 6.030.000', $bodyAll); // Active Stays
        $this->assertStringContainsString('$ 750.000', $bodyAll);   // Policy Retention
        $this->assertStringContainsString('$ 950.000', $bodyAll);   // Refunded
        $this->assertStringContainsString('$ 1.840.000 COP', $bodyAll); // Total Operational Expenses
        $this->assertStringContainsString('Shared Pool (100%)', $bodyAll);
        $this->assertStringContainsString('$ 4.940.000 COP', $bodyAll); // NOI

        // Verify Row 2 Hospitality KPI Cards
        $this->assertStringContainsString('29.8%', $bodyAll);
        $this->assertStringContainsString('17 Occupied / 57 Available Nights (5 Maint/Owner deducted)', $bodyAll);
        $this->assertStringContainsString('Across 13 priced Occupied Nights (excl. retention)', $bodyAll);
        $this->assertStringContainsString('Gross Revenue &divide; 57 Available Nights', $bodyAll);
        $this->assertStringContainsString('4 Active Stays', $bodyAll);
        $this->assertStringContainsString('1 Settled Cancellation ($ 750.000 retained)', $bodyAll);

        // Verify Unpriced ($0 COP) Airbnb Warning Banner & Shortcut
        $this->assertStringContainsString('id="unpriced-airbnb-banner"', $bodyAll);
        $this->assertStringContainsString('1 External Reservation (Airbnb) in this period has a $0 COP host payout', $bodyAll);
        $this->assertStringContainsString('Set Host Payout (res-abnb-zero)', $bodyAll);
        $this->assertStringContainsString('hx-get="/reservations/res-abnb-zero/edit"', $bodyAll);

        // 2. Single Property Filter (property_id = 1606):
        // Direct 1606 Expenses = 720,000; Allocated Shared (50%) = 320,000 (50% of 640,000) => Total = 1,040,000 COP
        // 1606 Gross Revenue = 900,000 + 2,880,000 = 3,780,000 COP => 1606 NOI = 2,740,000 COP
        $resp1606 = $this->app->handle(
            new Request('GET', '/financial-report', query: ['property_id' => '1606'], server: ['HTTP_HX_REQUEST' => 'true']),
            $this->adminSession
        );
        $this->assertSame(200, $resp1606->getStatusCode());
        $body1606 = $resp1606->getBody();

        $this->assertStringContainsString('$ 3.780.000 COP', $body1606);
        $this->assertStringContainsString('$ 1.040.000 COP', $body1606);
        $this->assertStringContainsString('Allocated Shared Expenses (50%)', $body1606);
        $this->assertStringContainsString('$ 320.000', $body1606);
        $this->assertStringContainsString('50% of $ 640.000 COP', $body1606);
        $this->assertStringContainsString('Shared (50% Alloc)', $body1606);
        $this->assertStringContainsString('$ 2.740.000 COP', $body1606);

        // 3. Compare Against YoY (compare = yoy):
        // Oct 2026 Gross Revenue (6,780,000) vs Oct 2025 Gross Revenue (3,390,000) => +100.0% vs YoY (+$ 3.390.000 COP)
        $respYoy = $this->app->handle(
            new Request('GET', '/financial-report', query: ['compare' => 'yoy'], server: ['HTTP_HX_REQUEST' => 'true']),
            $this->adminSession
        );
        $this->assertSame(200, $respYoy->getStatusCode());
        $bodyYoy = $respYoy->getBody();
        $this->assertStringContainsString('+100.0% vs YoY (+$ 3.390.000 COP)', $bodyYoy);

        // 4. Single Booking Source Filter (source = direct_manual):
        // Scopes revenue to Lucia Fernandez (2,250,000 COP, 5 nights) and displays Channel Contribution Notice
        $respManualSource = $this->app->handle(
            new Request('GET', '/financial-report', query: ['source' => 'direct_manual'], server: ['HTTP_HX_REQUEST' => 'true']),
            $this->adminSession
        );
        $this->assertSame(200, $respManualSource->getStatusCode());
        $bodyManualSource = $respManualSource->getBody();
        $this->assertStringContainsString('id="channel-contribution-notice"', $bodyManualSource);
        $this->assertStringContainsString('Channel Contribution View (Direct Manual):', $bodyManualSource);
        $this->assertStringContainsString('Operational Expenses and Net Operating Income are evaluated at the Property/Portfolio (All Sources) level.', $bodyManualSource);
        $this->assertStringContainsString('$ 2.250.000 COP', $bodyManualSource);

        // 5. Tabbed Switching to Attributed Reservations (tab = reservations):
        $respResTab = $this->app->handle(
            new Request('GET', '/financial-report', query: ['tab' => 'reservations'], server: ['HTTP_HX_REQUEST' => 'true']),
            $this->adminSession
        );
        $this->assertSame(200, $respResTab->getStatusCode());
        $bodyResTab = $respResTab->getBody();
        $this->assertStringContainsString('id="attributed-reservations-ledger-panel"', $bodyResTab);
        $this->assertStringContainsString('ovf_cross_sep_oct', $bodyResTab);
        $this->assertStringContainsString('Camila Restrepo', $bodyResTab);
        $this->assertStringContainsString('2 / 5 nights (40%)', $bodyResTab);
        $this->assertStringContainsString('$ 900.000 COP', $bodyResTab);
        $this->assertStringContainsString('ovf_cancelled_ret', $bodyResTab);
        $this->assertStringContainsString('Cancelled &middot; Policy Retention', $bodyResTab);
        $this->assertStringContainsString('$0 COP &middot; Set Payout', $bodyResTab);
        // Unpaid cancelled hold must not appear in Attributed Reservations
        $this->assertStringNotContainsString('ovf_unpaid_void', $bodyResTab);

        // 6. Verify HTMX Expense Mutation refreshes KPI Scorecards via hx-swap-oob
        $mutateResp = $this->app->handle(new Request(
            method: 'POST',
            uri: '/financial-report/expenses',
            post: [
                'csrf_token' => 'valid-csrf-token',
                'property_id' => '1606',
                'category' => 'maintenance',
                'title' => 'Lock Battery Replacement',
                'amount' => '160000',
                'expense_date' => '2026-10-16',
                'current_property_id' => 'all',
                'current_period' => 'this_month',
            ],
            server: ['HTTP_HX_REQUEST' => 'true']
        ), $this->adminSession);
        $this->assertSame(200, $mutateResp->getStatusCode());
        $mutateBody = $mutateResp->getBody();
        // Total Expenses increased from 1,840,000 to 2,000,000 COP; NOI updated from 4,940,000 to 4,780,000 COP
        $this->assertStringContainsString('id="financial-report-content" class="space-y-6" hx-swap-oob="outerHTML"', $mutateBody);
        $this->assertStringContainsString('$ 2.000.000 COP', $mutateBody);
        $this->assertStringContainsString('$ 4.780.000 COP', $mutateBody);
    }

    public function testMultiPropertyComparisonMatrixDimensionalBreakdownsAndMonthlyPnlTrendTableRendering(): void
    {
        $this->pdo->exec('DELETE FROM reservations');
        $this->pdo->exec('DELETE FROM calendar_blocks');
        $this->pdo->exec('DELETE FROM operational_expenses');

        // Seed reservations across Oct and Nov 2026:
        // - 1606 Direct Online (web): Oct 05 -> Oct 11 (6 nights, 3,000,000 COP)
        // - 1707 Direct Manual (bank_transfer): Oct 10 -> Oct 15 (5 nights, 2,000,000 COP)
        // - 1606 External OTA (airbnb): Oct 20 -> Oct 24 (4 nights, 1,600,000 COP)
        // - 1707 Settled Cancelled Policy Retention (web): Oct 16 -> Oct 19 (3 nights, 1,200,000 total, 800,000 refunded => 400,000 retained)
        // - 1707 Nov Direct Online (web): Nov 05 -> Nov 10 (5 nights, 2,500,000 COP)
        $this->pdo->exec("
            INSERT INTO reservations (
                reservation_uid, property_id, guest_name, guest_email, guest_phone,
                check_in, check_out, total_price, refunded_amount, status, payment_status, source
            ) VALUES
                ('ovf_oct_web_1606', '1606', 'Markus Vance', 'markus@example.com', '+573001112233', '2026-10-05', '2026-10-11', 3000000, 0, 'confirmed', 'approved', 'web'),
                ('res-man-oct-1707', '1707', 'Lucia Fernandez', 'lucia@example.com', '+573004445566', '2026-10-10', '2026-10-15', 2000000, 0, 'confirmed', 'approved', 'bank_transfer'),
                ('res-abnb-oct-1606', '1606', 'David Miller', 'david@guest.oceanviewflats.com', '+13055550199', '2026-10-20', '2026-10-24', 1600000, 0, 'confirmed', 'approved', 'airbnb'),
                ('ovf_oct_ret_1707', '1707', 'Carlos Mendoza', 'carlos@example.com', '+573007778899', '2026-10-16', '2026-10-19', 1200000, 800000, 'cancelled', 'partially_refunded', 'web'),
                ('ovf_nov_web_1707', '1707', 'Elena Rostova', 'elena@example.com', '+573009990011', '2026-11-05', '2026-11-10', 2500000, 0, 'confirmed', 'approved', 'web');
        ");

        // Maintenance block on 1606: Oct 29 -> Oct 31 (2 nights deducted)
        $this->pdo->exec("
            INSERT INTO calendar_blocks (property_id, start_date, end_date, reason, created_by)
            VALUES ('1606', '2026-10-29', '2026-10-31', 'AC Maintenance', 1);
        ");

        // Operational Expenses in Oct 2026 with distinct category amounts to verify descending sort:
        // - hoa_admin (1606 direct): 800,000 COP (40.0%)
        // - supplies (shared): 600,000 COP (30.0%) -> allocated 300,000 to 1606 and 300,000 to 1707
        // - utilities (1707 direct): 400,000 COP (20.0%)
        // - cleaning (1606 direct): 200,000 COP (10.0%)
        // Total Oct Expenses = 2,000,000 COP (Direct = 1,400,000, Shared = 600,000)
        // Plus Nov 2026 expense on 1707: 500,000 COP
        $this->pdo->exec("
            INSERT INTO operational_expenses (expense_uid, property_id, category, title, amount, expense_date, vendor_name, reference_code, created_by)
            VALUES
                ('exp_oct_hoa', '1606', 'hoa_admin', 'HOA 1606 Oct', 800000, '2026-10-02', 'Salguero Sunset', 'HOA-10', 1),
                ('exp_oct_sup', 'shared', 'supplies', 'Shared Hotel Linens', 600000, '2026-10-05', 'Makro', 'SUP-10', 1),
                ('exp_oct_utl', '1707', 'utilities', 'Utilities 1707 Oct', 400000, '2026-10-08', 'Air-e', 'UTL-10', 1),
                ('exp_oct_cln', '1606', 'cleaning', 'Turnover Cleaning 1606', 200000, '2026-10-11', 'Marta Ospina', 'CLN-10', 1),
                ('exp_nov_mnt', '1707', 'maintenance', 'Nov Balcony Repair', 500000, '2026-11-12', 'Tecnicos Caribe', 'MNT-11', 1);
        ");

        // 1. Request All Properties (this_month = Oct 2026):
        // Portfolio Total:
        // - Available Nights: 60 / 62 nights (1606: 29 / 31 nights, 1707: 31 / 31 nights)
        // - Occupied Nights: 15n (25.0%) (1606: 10n (34.5%), 1707: 5n (16.1%))
        // - Direct Online (active): $ 3.000.000 COP (1606: $ 3.000.000 COP, 1707: $ 0 COP)
        // - Direct Manual (active): $ 2.000.000 COP (1606: $ 0 COP, 1707: $ 2.000.000 COP)
        // - External OTA — Airbnb (active): $ 1.600.000 COP (1606: $ 1.600.000 COP, 1707: $ 0 COP)
        // - Policy Retention: $ 400.000 COP (1606: $ 0 COP, 1707: $ 400.000 COP)
        // - Gross Revenue: $ 7.000.000 COP (1606: $ 4.600.000 COP, 1707: $ 2.400.000 COP)
        // - Direct Property Operational Expenses (100%): $ 1.400.000 COP (1606: $ 1.000.000 COP, 1707: $ 400.000 COP)
        // - Shared Portfolio Expenses (50/50): $ 600.000 COP (100%) (1606: $ 300.000 COP (50%), 1707: $ 300.000 COP (50%))
        // - Total Operational Expenses: - $ 2.000.000 COP (1606: - $ 1.300.000 COP, 1707: - $ 700.000 COP)
        // - Net Operating Income: $ 5.000.000 COP (71.4%) (1606: $ 3.300.000 COP (71.7%), 1707: $ 1.700.000 COP (70.8%))
        $respAll = $this->app->handle(new Request('GET', '/financial-report'), $this->adminSession);
        $this->assertSame(200, $respAll->getStatusCode());
        $bodyAll = $respAll->getBody();

        // Multi-Property Comparison Matrix is rendered when property_id=all
        $this->assertStringContainsString('id="multi-property-comparison-matrix"', $bodyAll);
        $this->assertStringContainsString('Shared Portfolio Expenses (Allocated 50/50 per Property)', $bodyAll);
        $this->assertStringContainsString('Portfolio Total (All)', $bodyAll);
        $this->assertStringContainsString('60 / 62 nights', $bodyAll);
        $this->assertStringContainsString('29 / 31 nights', $bodyAll);
        $this->assertStringContainsString('31 / 31 nights', $bodyAll);
        $this->assertStringContainsString('15n (25.0%)', $bodyAll);
        $this->assertStringContainsString('10n (34.5%)', $bodyAll);
        $this->assertStringContainsString('5n (16.1%)', $bodyAll);
        $this->assertStringContainsString('$ 600.000 COP (100%)', $bodyAll);
        $this->assertStringContainsString('$ 300.000 COP (50%)', $bodyAll);
        $this->assertStringContainsString('- $ 2.000.000 COP', $bodyAll);
        $this->assertStringContainsString('- $ 1.300.000 COP', $bodyAll);
        $this->assertStringContainsString('- $ 700.000 COP', $bodyAll);
        $this->assertStringContainsString('$ 5.000.000 COP (71.4%)', $bodyAll);
        $this->assertStringContainsString('$ 3.300.000 COP (71.7%)', $bodyAll);
        $this->assertStringContainsString('$ 1.700.000 COP (70.8%)', $bodyAll);

        // Single-month window does NOT render Monthly P&L Trend Table
        $this->assertStringNotContainsString('id="monthly-pnl-trend-table"', $bodyAll);

        // Verify Booking Source breakdown panel (Direct Online = 3,400,000 incl. 400k retention = 48.6%, Direct Manual = 2,000,000 = 28.6%, Airbnb = 1,600,000 = 22.9%)
        $this->assertStringContainsString('id="booking-source-breakdown-panel"', $bodyAll);
        $this->assertStringContainsString('data-booking-source-row="direct_online"', $bodyAll);
        $this->assertStringContainsString('$ 3.400.000 COP (48.6%)', $bodyAll);
        $this->assertStringContainsString('data-booking-source-row="direct_manual"', $bodyAll);
        $this->assertStringContainsString('$ 2.000.000 COP (28.6%)', $bodyAll);
        $this->assertStringContainsString('data-booking-source-row="airbnb"', $bodyAll);
        $this->assertStringContainsString('$ 1.600.000 COP (22.9%)', $bodyAll);

        // Verify Expense Category breakdown panel is sorted descending across the 8 canonical categories:
        // hoa_admin ($800k, 40.0%) -> supplies ($600k, 30.0%) -> utilities ($400k, 20.0%) -> cleaning ($200k, 10.0%) -> remaining 0% categories
        $this->assertStringContainsString('id="expense-category-breakdown-panel"', $bodyAll);
        $this->assertStringContainsString('$ 800.000 COP (40.0%)', $bodyAll);
        $this->assertStringContainsString('$ 600.000 COP (30.0%)', $bodyAll);
        $this->assertStringContainsString('$ 400.000 COP (20.0%)', $bodyAll);
        $this->assertStringContainsString('$ 200.000 COP (10.0%)', $bodyAll);

        $posHoa = strpos($bodyAll, 'data-expense-category-row="hoa_admin"');
        $posSup = strpos($bodyAll, 'data-expense-category-row="supplies"');
        $posUtl = strpos($bodyAll, 'data-expense-category-row="utilities"');
        $posCln = strpos($bodyAll, 'data-expense-category-row="cleaning"');
        $posMnt = strpos($bodyAll, 'data-expense-category-row="maintenance"');
        $this->assertNotFalse($posHoa);
        $this->assertNotFalse($posSup);
        $this->assertNotFalse($posUtl);
        $this->assertNotFalse($posCln);
        $this->assertNotFalse($posMnt);
        $this->assertLessThan($posSup, $posHoa);
        $this->assertLessThan($posUtl, $posSup);
        $this->assertLessThan($posCln, $posUtl);
        $this->assertLessThan($posMnt, $posCln);

        // 2. When Property Filter is 1606 or 1707, Multi-Property Comparison Matrix is NOT rendered
        $resp1606 = $this->app->handle(
            new Request('GET', '/financial-report', query: ['property_id' => '1606'], server: ['HTTP_HX_REQUEST' => 'true']),
            $this->adminSession
        );
        $this->assertSame(200, $resp1606->getStatusCode());
        $this->assertStringNotContainsString('id="multi-property-comparison-matrix"', $resp1606->getBody());

        $resp1707 = $this->app->handle(
            new Request('GET', '/financial-report', query: ['property_id' => '1707'], server: ['HTTP_HX_REQUEST' => 'true']),
            $this->adminSession
        );
        $this->assertSame(200, $resp1707->getStatusCode());
        $this->assertStringNotContainsString('id="multi-property-comparison-matrix"', $resp1707->getBody());

        // 3. Multi-Month Period (this_quarter = Q4 2026) renders the Monthly P&L Trend Table with YYYY-MM rows and inline bars
        $respQuarter = $this->app->handle(
            new Request('GET', '/financial-report', query: ['period' => 'this_quarter'], server: ['HTTP_HX_REQUEST' => 'true']),
            $this->adminSession
        );
        $this->assertSame(200, $respQuarter->getStatusCode());
        $bodyQuarter = $respQuarter->getBody();
        $this->assertStringContainsString('id="monthly-pnl-trend-table"', $bodyQuarter);
        $this->assertStringContainsString('data-trend-month="2026-10"', $bodyQuarter);
        $this->assertStringContainsString('data-trend-month="2026-11"', $bodyQuarter);
        $this->assertStringContainsString('data-trend-month="2026-12"', $bodyQuarter);
        $this->assertStringContainsString('trend-revenue-bar', $bodyQuarter);
        $this->assertStringContainsString('trend-expense-bar', $bodyQuarter);
    }

    public function testFilterAwareUtf8BomCsvExportsAndDynamicHeaderActionsLinks(): void
    {
        $this->pdo->exec('DELETE FROM reservations');
        $this->pdo->exec('DELETE FROM operational_expenses');

        // Seed reservations across properties and sources in Oct 2026
        $this->pdo->exec("
            INSERT INTO reservations (
                reservation_uid, property_id, guest_name, guest_email, guest_phone,
                check_in, check_out, total_price, refunded_amount, status, payment_status, source
            ) VALUES
                ('ovf_csv_web_1606', '1606', 'Markus Vance', 'markus@example.com', '+573001112233', '2026-10-05', '2026-10-10', 2500000, 0, 'confirmed', 'approved', 'web'),
                ('res-abnb-csv-1707', '1707', 'Sophie Laurent', 'sophie@guest.oceanviewflats.com', '+33612345678', '2026-10-12', '2026-10-16', 1800000, 0, 'confirmed', 'approved', 'airbnb'),
                ('ovf_csv_sep_1606', '1606', 'September Guest', 'sep@example.com', '+573009998877', '2026-09-10', '2026-09-15', 2000000, 0, 'confirmed', 'approved', 'web');
        ");

        // Seed expenses across 1606, 1707, and shared
        $this->pdo->exec("
            INSERT INTO operational_expenses (expense_uid, property_id, category, title, amount, expense_date, vendor_name, reference_code, reservation_uid, notes, created_by)
            VALUES
                ('exp_csv_1606_cln', '1606', 'cleaning', 'Turnover Cleaning 1606', 180000, '2026-10-10', 'Marta Ospina', 'CLN-1606', 'ovf_csv_web_1606', 'Deep clean', 1),
                ('exp_csv_1707_utl', '1707', 'utilities', 'Energia Air-e 1707', 450000, '2026-10-14', 'Air-e', 'UTL-1707', NULL, 'Monthly bill', 1),
                ('exp_csv_shared_sup', 'shared', 'supplies', 'Shared Coffee & Linens', 600000, '2026-10-15', 'Makro', 'SUP-SHARED', NULL, '50/50 split', 1);
        ");

        // 1. Verify header actions carry dynamic filter query parameters and update via hx-swap-oob on HTMX filter change
        $htmxFilterResp = $this->app->handle(
            new Request(
                method: 'GET',
                uri: '/financial-report',
                query: [
                    'property_id' => '1606',
                    'period' => 'custom',
                    'date_from' => '2026-10-01',
                    'date_to' => '2026-11-01',
                    'source' => 'direct_online',
                    'category' => 'cleaning',
                ],
                server: ['HTTP_HX_REQUEST' => 'true']
            ),
            $this->adminSession
        );
        $this->assertSame(200, $htmxFilterResp->getStatusCode());
        $htmxBody = $htmxFilterResp->getBody();
        $this->assertStringContainsString('id="financial-report-header-actions" class="flex flex-wrap items-center gap-2.5" hx-swap-oob="outerHTML"', $htmxBody);
        $this->assertStringContainsString('/financial-report/export/reservations.csv?property_id=1606&amp;period=custom&amp;date_from=2026-10-01&amp;date_to=2026-11-01&amp;compare=prev_period&amp;source=direct_online&amp;category=cleaning', $htmxBody);
        $this->assertStringContainsString('/financial-report/export/expenses.csv?property_id=1606&amp;period=custom&amp;date_from=2026-10-01&amp;date_to=2026-11-01&amp;compare=prev_period&amp;source=direct_online&amp;category=cleaning', $htmxBody);

        // 2. Export Expenses CSV for Property 1606 (includes 100% of 1606 direct + 50% of shared, excludes 1707 direct)
        $expCsvResp = $this->app->handle(
            new Request(
                method: 'GET',
                uri: '/financial-report/export/expenses.csv',
                query: ['property_id' => '1606', 'period' => 'this_month']
            ),
            $this->adminSession
        );
        $this->assertSame(200, $expCsvResp->getStatusCode());
        $this->assertSame('text/csv; charset=UTF-8', $expCsvResp->getHeader('Content-Type'));
        $this->assertStringContainsString('attachment; filename="operational-expenses-1606-2026-10-01-to-2026-11-01.csv"', (string) $expCsvResp->getHeader('Content-Disposition'));

        $expCsvBody = $expCsvResp->getBody();
        // Starts with UTF-8 BOM (\xEF\xBB\xBF)
        $this->assertTrue(str_starts_with($expCsvBody, "\xEF\xBB\xBF"));

        $expCsvLines = array_values(array_filter(explode("\n", trim(substr($expCsvBody, 3)))));
        $this->assertCount(3, $expCsvLines); // Header + 2 rows (exp_csv_shared_sup + exp_csv_1606_cln)
        $headerRow = str_getcsv($expCsvLines[0], ',', '"', '');
        $this->assertSame([
            'expense_uid',
            'expense_date',
            'property_id',
            'category',
            'category_label',
            'title',
            'vendor_name',
            'reference_code',
            'reservation_uid',
            'raw_amount_cop',
            'allocation_factor',
            'allocated_amount_cop',
            'notes',
        ], $headerRow);

        $this->assertStringContainsString('exp_csv_1606_cln', $expCsvBody);
        $this->assertStringContainsString('180000.00,1.00,180000.00', $expCsvBody);
        $this->assertStringContainsString('exp_csv_shared_sup', $expCsvBody);
        $this->assertStringContainsString('600000.00,0.50,300000.00', $expCsvBody);
        $this->assertStringNotContainsString('exp_csv_1707_utl', $expCsvBody);

        // Filter Expenses CSV by category = cleaning -> only exp_csv_1606_cln
        $expCatCsvResp = $this->app->handle(
            new Request(
                method: 'GET',
                uri: '/financial-report/export/expenses.csv',
                query: ['property_id' => 'all', 'period' => 'this_month', 'category' => 'cleaning']
            ),
            $this->superadminSession
        );
        $this->assertSame(200, $expCatCsvResp->getStatusCode());
        $expCatCsvBody = $expCatCsvResp->getBody();
        $this->assertStringContainsString('exp_csv_1606_cln', $expCatCsvBody);
        $this->assertStringNotContainsString('exp_csv_shared_sup', $expCatCsvBody);
        $this->assertStringNotContainsString('exp_csv_1707_utl', $expCatCsvBody);

        // 3. Export Reservations CSV filtered by source = airbnb and period = this_month
        $resCsvResp = $this->app->handle(
            new Request(
                method: 'GET',
                uri: '/financial-report/export/reservations.csv',
                query: ['property_id' => 'all', 'period' => 'this_month', 'source' => 'airbnb']
            ),
            $this->adminSession
        );
        $this->assertSame(200, $resCsvResp->getStatusCode());
        $this->assertSame('text/csv; charset=UTF-8', $resCsvResp->getHeader('Content-Type'));
        $this->assertStringContainsString('attachment; filename="attributed-reservations-all-2026-10-01-to-2026-11-01.csv"', (string) $resCsvResp->getHeader('Content-Disposition'));

        $resCsvBody = $resCsvResp->getBody();
        $this->assertTrue(str_starts_with($resCsvBody, "\xEF\xBB\xBF"));
        $this->assertStringContainsString('res-abnb-csv-1707', $resCsvBody);
        $this->assertStringContainsString('Sophie Laurent', $resCsvBody);
        $this->assertStringContainsString('1800000.00', $resCsvBody);
        $this->assertStringNotContainsString('ovf_csv_web_1606', $resCsvBody);
        $this->assertStringNotContainsString('ovf_csv_sep_1606', $resCsvBody);

        // 4. Switching from custom range back to a non-custom preset clears stale date_from/date_to
        $presetAfterCustomResp = $this->app->handle(
            new Request(
                method: 'GET',
                uri: '/financial-report',
                query: [
                    'property_id' => 'all',
                    'period' => 'this_month',
                    'date_from' => '2026-10-05',
                    'date_to' => '2026-10-10',
                ]
            ),
            $this->adminSession
        );
        $this->assertSame(200, $presetAfterCustomResp->getStatusCode());
        $presetAfterCustomBody = $presetAfterCustomResp->getBody();
        $this->assertStringContainsString('name="date_from"' . "\n" . '                           value=""', $presetAfterCustomBody);
        $this->assertStringContainsString('name="date_to"' . "\n" . '                           value=""', $presetAfterCustomBody);
        $this->assertStringContainsString('reservationUpdated from:body', $presetAfterCustomBody);

        // 5. Concluded unpriced ($0 COP) Airbnb reservation can be edited via Set Host Payout shortcut
        $this->pdo->exec("
            INSERT INTO reservations (
                reservation_uid, property_id, guest_name, guest_email, guest_phone,
                check_in, check_out, total_price, refunded_amount, status, payment_status, source, external_confirmation_code
            ) VALUES (
                'res-abnb-past-unpriced', '1606', 'Past Airbnb Guest', 'airbnb-past@guest.oceanviewflats.com', 'N/A',
                '2025-05-01', '2025-05-05', 0, 0, 'confirmed', 'approved', 'airbnb', 'HMPAST999'
            );
        ");

        $editPastAbnbResp = $this->app->handle(
            new Request('GET', '/reservations/res-abnb-past-unpriced/edit', server: ['HTTP_HX_REQUEST' => 'true']),
            $this->adminSession
        );
        $this->assertSame(200, $editPastAbnbResp->getStatusCode());

        $updatePastAbnbResp = $this->app->handle(
            new Request(
                method: 'POST',
                uri: '/reservations/res-abnb-past-unpriced/edit',
                post: [
                    'csrf_token' => 'valid-csrf-token',
                    'property_id' => '1606',
                    'check_in' => '2025-05-01',
                    'check_out' => '2025-05-05',
                    'total_price' => '1950000',
                    'max_guests' => '4',
                    'guest_name' => 'Past Airbnb Guest',
                    'external_confirmation_code' => 'HMPAST999',
                ],
                server: ['HTTP_HX_REQUEST' => 'true']
            ),
            $this->adminSession
        );
        $this->assertSame(200, $updatePastAbnbResp->getStatusCode());
        $this->assertSame('reservationUpdated', $updatePastAbnbResp->getHeader('HX-Trigger'));
    }

    public function testCreateExpenseSelfHealsMissingTableAndSurfacesPersistenceErrorInsideModalWith422(): void
    {
        // 1. Simulate an environment where operational_expenses table has not yet been migrated
        $this->pdo->exec('DROP TABLE operational_expenses');

        $indexResp = $this->app->handle(new Request('GET', '/financial-report'), $this->adminSession);
        $this->assertSame(200, $indexResp->getStatusCode());

        $newModalResp = $this->app->handle(
            new Request('GET', '/financial-report/expenses/new', server: ['HTTP_HX_REQUEST' => 'true']),
            $this->adminSession
        );
        $this->assertSame(200, $newModalResp->getStatusCode());
        $this->assertStringContainsString('z-40', $newModalResp->getBody());
        $this->assertStringContainsString('overflow-y-auto', $newModalResp->getBody());

        // Submitting POST /financial-report/expenses self-heals the missing table via MigrationRunner,
        // closes #modal-container, and renders the success flash banner in #financial-report-content
        $createReq = new Request(
            method: 'POST',
            uri: '/financial-report/expenses',
            post: [
                'csrf_token' => 'valid-csrf-token',
                'property_id' => '1606',
                'category' => 'hoa_admin',
                'title' => 'Monthly HOA Administration Oct 2026',
                'amount' => '680000',
                'expense_date' => '2026-10-18',
                'vendor_name' => 'Edificio Salguero Sunset',
                'reference_code' => 'FAC-2026-1094',
                'reservation_uid' => '',
                'notes' => 'Preserve this note on DB failure',
            ],
            server: ['HTTP_HX_REQUEST' => 'true']
        );

        $healedResp = $this->app->handle($createReq, $this->adminSession);
        $this->assertSame(200, $healedResp->getStatusCode());
        $this->assertSame('expenseSaved', $healedResp->getHeader('HX-Trigger'));
        $this->assertStringContainsString('<div id="modal-container" hx-swap-oob="innerHTML"></div>', $healedResp->getBody());
        $this->assertStringContainsString('Monthly HOA Administration Oct 2026', $healedResp->getBody());

        // 2. When persistence throws an unrecoverable database error, FinancialReportController catches Throwable
        //    and returns HTTP 422 targeting #modal-container with #modal-error and 100% preserved inputs
        $failingRepo = new class implements \OceanViewFlats\Domain\Finance\OperationalExpenseRepositoryInterface {
            public function findById(int $id): ?\OceanViewFlats\Domain\Finance\OperationalExpense
            {
                return null;
            }

            public function findByUid(string $expenseUid): ?\OceanViewFlats\Domain\Finance\OperationalExpense
            {
                return null;
            }

            public function listForPeriod(
                string $startDate,
                string $endDate,
                string $propertyId = 'all',
                string $category = 'all',
                string $search = ''
            ): array {
                return [];
            }

            public function save(\OceanViewFlats\Domain\Finance\OperationalExpense $expense): \OceanViewFlats\Domain\Finance\OperationalExpense
            {
                throw new \PDOException('SQLSTATE[HY000]: Disk I/O error');
            }

            public function delete(int $id): bool
            {
                return false;
            }
        };

        $failingApp = AdminApp::createDefault($this->pdo, [
            'operational_expense_repository' => $failingRepo,
            'financial_report_now' => new DateTimeImmutable('2026-10-17 14:30:00', new \DateTimeZone('America/Bogota')),
        ]);

        $errorResp = $failingApp->handle($createReq, $this->adminSession);
        $this->assertSame(422, $errorResp->getStatusCode());
        $this->assertSame('#modal-container', $errorResp->getHeader('HX-Retarget'));
        $this->assertSame('innerHTML', $errorResp->getHeader('HX-Reswap'));

        $body = $errorResp->getBody();
        $this->assertStringContainsString('id="modal-error"', $body);
        $this->assertStringContainsString('Unable to save operational expense due to a database error.', $body);
        $this->assertStringContainsString('value="Monthly HOA Administration Oct 2026"', $body);
        $this->assertStringContainsString('value="680000"', $body);
        $this->assertStringContainsString('value="Edificio Salguero Sunset"', $body);
        $this->assertStringContainsString('value="FAC-2026-1094"', $body);
        $this->assertStringContainsString('Preserve this note on DB failure', $body);
    }
}
