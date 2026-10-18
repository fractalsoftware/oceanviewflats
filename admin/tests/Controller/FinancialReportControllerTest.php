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
}
