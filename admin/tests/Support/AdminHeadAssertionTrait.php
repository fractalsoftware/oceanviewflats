<?php

declare(strict_types=1);

namespace OceanViewFlats\Admin\Tests\Support;

use DOMDocument;
use DOMXPath;
use OceanViewFlats\Admin\AdminApp;
use PDO;

/**
 * Shared test setup, route providers, and HTML <head> XPath parsing helpers
 * for Admin Console favicon and PWA integration tests.
 */
trait AdminHeadAssertionTrait
{
    protected string $rootPath;
    protected string $publicDir;
    protected string $adminPublicDir;
    protected PDO $pdo;
    protected AdminApp $app;

    /**
     * @var array<string, mixed>
     */
    protected array $authenticatedAdminSession;

    protected function setUpAdminHeadTestEnvironment(): void
    {
        $this->rootPath = dirname(__DIR__, 3);
        $this->publicDir = $this->rootPath . '/public';
        $this->adminPublicDir = $this->rootPath . '/admin/public';

        $this->pdo = AdminDatabaseTestHelper::createDatabaseWithDefaultAdmin();
        $this->app = AdminApp::createDefault($this->pdo);

        $this->authenticatedAdminSession = [
            'admin_user_id' => 1,
            'admin_user_name' => 'Manuel Admin',
            'admin_user_email' => 'admin@oceanviewflats.com',
            'admin_user_role' => 'admin',
            'csrf_token' => 'secure_csrf_token_head_assertion_test',
        ];
    }

    /**
     * @return array<string, array{0: string}>
     */
    public static function authenticatedAdminRoutesProvider(): array
    {
        return [
            'Dashboard Hub' => ['/'],
            'Reservations Ledger' => ['/reservations'],
            'Seasonal Rate Tiers' => ['/rates'],
            'Maintenance Blocks' => ['/calendar-blocks'],
            'Audit Log' => ['/audit-logs'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function createViewerSession(): array
    {
        return [
            'admin_user_id' => 2,
            'admin_user_name' => 'Viewer Operator',
            'admin_user_email' => 'viewer@oceanviewflats.com',
            'admin_user_role' => 'viewer',
            'csrf_token' => 'secure_csrf_token_viewer_test',
        ];
    }

    protected function createHeadXPath(string $html): DOMXPath
    {
        $dom = new DOMDocument();
        libxml_use_internal_errors(true);
        $dom->loadHTML($html);
        libxml_clear_errors();

        return new DOMXPath($dom);
    }

    protected function extractSetupHtmlShell(): string
    {
        $setupPath = $this->adminPublicDir . '/setup.php';
        $this->assertFileExists($setupPath);

        $setupSource = (string) file_get_contents($setupPath);
        $doctypePos = strpos($setupSource, '<!DOCTYPE html>');
        $this->assertNotFalse($doctypePos, 'admin/public/setup.php must contain an HTML document shell');

        return substr($setupSource, $doctypePos);
    }
}
