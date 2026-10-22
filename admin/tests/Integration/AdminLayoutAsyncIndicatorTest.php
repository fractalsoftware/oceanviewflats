<?php

declare(strict_types=1);

namespace OceanViewFlats\Admin\Tests\Integration;

use OceanViewFlats\Admin\AdminApp;
use OceanViewFlats\Admin\Http\Request;
use OceanViewFlats\Admin\Tests\Support\AdminDatabaseTestHelper;
use PDO;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Integration test suite for Ticket #123, Ticket #124 & Ticket #125:
 * - Global Animated Top Progress Bar with Debounced HTMX Request Counter (#123).
 * - Immediate Button-Level Mutation Locking and Double-Submit Protection (#124).
 * - Asynchronous Error Resiliency (Red Bar Flash & Floating Alert Banner) (#125).
 *
 * Verifies that all primary administrative authenticated pages render:
 * 1. Pinned top progress bar (#global-progress-bar) with Tailwind classes and ARIA attributes.
 * 2. Keyframe animations for indeterminate shimmer and prefers-reduced-motion media query.
 * 3. Preserved explicit .htmx-indicator rules.
 * 4. Client-side HTMX lifecycle script handling htmx:beforeRequest and htmx:afterRequest
 *    with an activeRequests counter, 150ms debounce threshold, and width transitions.
 * 5. Button-level mutation locking CSS rules for in-flight requests.
 * 6. Client-side mutating request detection (POST, DELETE, PUT) and trigger lock/unlock lifecycle hooks.
 * 7. Global alert container (#global-alert-container) with role="alert" and aria-live="assertive".
 * 8. Asynchronous error resilience script handling htmx:responseError and htmx:sendError,
 *    flashing bg-rose-500, displaying floating toast alert, and releasing mutation locks.
 */
final class AdminLayoutAsyncIndicatorTest extends TestCase
{
    private PDO $pdo;
    private AdminApp $app;

    /**
     * @var array<string, mixed>
     */
    private array $authenticatedSession;

    protected function setUp(): void
    {
        $this->pdo = AdminDatabaseTestHelper::createDatabaseWithDefaultAdmin();
        $this->app = AdminApp::createDefault($this->pdo);

        $this->authenticatedSession = [
            'admin_user_id' => 1,
            'admin_user_name' => 'Manuel Admin',
            'admin_user_email' => 'admin@oceanviewflats.com',
            'admin_user_role' => 'admin',
            'csrf_token' => 'secure_csrf_token_async_indicator_test',
        ];
    }

    /**
     * @return array<string, array{0: string}>
     */
    public static function authenticatedRoutesProvider(): array
    {
        return [
            'Dashboard Hub' => ['/'],
            'Reservations Ledger' => ['/reservations'],
            'Property Rates' => ['/rates'],
            'Calendar Maintenance Blocks' => ['/calendar-blocks'],
        ];
    }

    #[DataProvider('authenticatedRoutesProvider')]
    public function testAuthenticatedRoutesRenderGlobalProgressBarElement(string $route): void
    {
        $request = new Request('GET', $route);
        $response = $this->app->handle($request, $this->authenticatedSession);

        $this->assertSame(200, $response->getStatusCode(), "Expected HTTP 200 for authenticated GET {$route}");
        $html = $response->getBody();

        // 1. Progress bar element existence and ID
        $this->assertStringContainsString('id="global-progress-bar"', $html);

        // 2. Accessibility attributes
        $this->assertStringContainsString('role="status"', $html);
        $this->assertStringContainsString('aria-live="polite"', $html);
        $this->assertStringContainsString('aria-label="Loading"', $html);

        // 3. Tailwind styling and initial state
        $this->assertStringContainsString('fixed top-0 left-0 w-full h-1 bg-indigo-600 z-50 pointer-events-none transition-all duration-300 ease-out opacity-0', $html);
        $this->assertStringContainsString('w-full', $html);
        $this->assertStringContainsString('style="width: 0%;"', $html);
    }

    #[DataProvider('authenticatedRoutesProvider')]
    public function testAuthenticatedRoutesIncludeHtmxLifecycleScript(string $route): void
    {
        $request = new Request('GET', $route);
        $response = $this->app->handle($request, $this->authenticatedSession);

        $this->assertSame(200, $response->getStatusCode());
        $html = $response->getBody();

        // 1. HTMX lifecycle listeners
        $this->assertStringContainsString('htmx:beforeRequest', $html);
        $this->assertStringContainsString('htmx:afterRequest', $html);

        // 2. Active requests counter
        $this->assertStringContainsString('activeRequests', $html);

        // 3. 150ms debounce logic
        $this->assertStringContainsString('150', $html);
        $this->assertStringContainsString('150ms debounce', $html);

        // 4. Smooth progression markers (25% -> 60% -> 85% -> 100% -> 0%)
        $this->assertStringContainsString("25%", $html);
        $this->assertStringContainsString("60%", $html);
        $this->assertStringContainsString("85%", $html);
        $this->assertStringContainsString("100%", $html);
        $this->assertStringContainsString("0%", $html);
    }

    #[DataProvider('authenticatedRoutesProvider')]
    public function testAuthenticatedRoutesIncludeShimmerAndAccessibilityStyles(string $route): void
    {
        $request = new Request('GET', $route);
        $response = $this->app->handle($request, $this->authenticatedSession);

        $this->assertSame(200, $response->getStatusCode());
        $html = $response->getBody();

        // 1. Shimmer animation keyframes, reachable selector, and theme variables
        $this->assertStringContainsString('@keyframes progress-shimmer', $html);
        $this->assertStringContainsString('#global-progress-bar.loading', $html);
        $this->assertStringContainsString('body.htmx-request #global-progress-bar', $html);
        $this->assertStringContainsString('var(--color-indigo-600, #4f46e5)', $html);
        $this->assertStringContainsString('var(--color-indigo-400, #818cf8)', $html);

        // 2. Reduced motion media query
        $this->assertStringContainsString('@media (prefers-reduced-motion: reduce)', $html);
        $this->assertStringContainsString('animation: none !important', $html);

        // 3. Preserved existing .htmx-indicator rules
        $this->assertStringContainsString('.htmx-indicator { display: none; }', $html);
        $this->assertStringContainsString('.htmx-request .htmx-indicator', $html);
    }

    #[DataProvider('authenticatedRoutesProvider')]
    public function testAuthenticatedRoutesIncludeButtonMutationLockStyles(string $route): void
    {
        $request = new Request('GET', $route);
        $response = $this->app->handle($request, $this->authenticatedSession);

        $this->assertSame(200, $response->getStatusCode());
        $html = $response->getBody();

        // 1. Selector targeting mutation buttons and submit controls
        $this->assertStringContainsString('.htmx-request:is(button, [type="submit"], a[hx-post], a[hx-delete], a[hx-put])', $html);
        $this->assertStringContainsString('form.htmx-request button[type="submit"]', $html);
        $this->assertStringContainsString('form.htmx-request input[type="submit"]', $html);

        // 2. Button mutation lock styling rules
        $this->assertStringContainsString('pointer-events: none !important;', $html);
        $this->assertStringContainsString('opacity: 0.75 !important;', $html);
        $this->assertStringContainsString('cursor: wait !important;', $html);
    }

    #[DataProvider('authenticatedRoutesProvider')]
    public function testAuthenticatedRoutesIncludeMutationLockingLifecycleScript(string $route): void
    {
        $request = new Request('GET', $route);
        $response = $this->app->handle($request, $this->authenticatedSession);

        $this->assertSame(200, $response->getStatusCode());
        $html = $response->getBody();

        // 1. Mutating HTTP verb detection (POST, DELETE, PUT)
        $this->assertStringContainsString('isMutatingVerb', $html);
        $this->assertStringContainsString('post', $html);
        $this->assertStringContainsString('delete', $html);
        $this->assertStringContainsString('put', $html);

        // 2. HTMX lifecycle hooks and error handlers for locking/unlocking
        $this->assertStringContainsString('htmx:beforeRequest', $html);
        $this->assertStringContainsString('htmx:afterRequest', $html);
        $this->assertStringContainsString('htmx:sendError', $html);
        $this->assertStringContainsString('htmx:responseError', $html);

        // 3. Trigger locking and unlocking logic
        $this->assertStringContainsString('requestConfig', $html);
        $this->assertStringContainsString('verb', $html);
        $this->assertStringContainsString('button[type="submit"]', $html);
        $this->assertStringContainsString('input[type="submit"]', $html);
        $this->assertStringContainsString('data-mutation-locked', $html);
        $this->assertStringContainsString('lockMutatingTriggers', $html);
        $this->assertStringContainsString('unlockMutatingTriggers', $html);
    }

    #[DataProvider('authenticatedRoutesProvider')]
    public function testAuthenticatedRoutesRenderGlobalAlertContainer(string $route): void
    {
        $request = new Request('GET', $route);
        $response = $this->app->handle($request, $this->authenticatedSession);

        $this->assertSame(200, $response->getStatusCode(), "Expected HTTP 200 for authenticated GET {$route}");
        $html = $response->getBody();

        // 1. Alert container element existence and ID
        $this->assertStringContainsString('id="global-alert-container"', $html);

        // 2. Accessibility and localization attributes
        $this->assertStringContainsString('role="alert"', $html);
        $this->assertStringContainsString('aria-live="assertive"', $html);
        $this->assertStringContainsString('data-msg-error="Network or server error occurred. Please try again."', $html);
        $this->assertStringContainsString('data-msg-dismiss="Dismiss alert"', $html);

        // 3. Tailwind positioning and layout classes
        $this->assertStringContainsString('fixed top-4 right-4 z-50 pointer-events-none space-y-2', $html);
    }

    #[DataProvider('authenticatedRoutesProvider')]
    public function testAuthenticatedRoutesIncludeErrorResilienceLifecycleScript(string $route): void
    {
        $request = new Request('GET', $route);
        $response = $this->app->handle($request, $this->authenticatedSession);

        $this->assertSame(200, $response->getStatusCode());
        $html = $response->getBody();

        // 1. HTMX error event handlers
        $this->assertStringContainsString('htmx:responseError', $html);
        $this->assertStringContainsString('htmx:sendError', $html);

        // 2. Error handling clears/decrements activeRequests counter
        $this->assertStringContainsString('decrementActiveRequests', $html);

        // 3. HTTP 500+ / network error detection
        $this->assertStringContainsString('status >= 500', $html);

        // 4. Progress bar rose error flash and duration
        $this->assertStringContainsString('bg-rose-500', $html);
        $this->assertStringContainsString('bg-indigo-600', $html);
        $this->assertStringContainsString('1200', $html);

        // 5. Floating alert toast creation, localization data-msg reading, and Tailwind rose tokens
        $this->assertStringContainsString('global-alert-container', $html);
        $this->assertStringContainsString('bg-rose-50', $html);
        $this->assertStringContainsString('border-rose-200', $html);
        $this->assertStringContainsString('text-rose-800', $html);
        $this->assertStringContainsString('data-msg-error', $html);
        $this->assertStringContainsString('data-msg-dismiss', $html);
        $this->assertStringContainsString('Network or server error occurred. Please try again.', $html);

        // 6. Toast auto-dismiss duration (4000ms) and manual close button
        $this->assertStringContainsString('4000', $html);
        $this->assertStringContainsString('Dismiss alert', $html);

        // 7. Mutation lock release on error for user retry
        $this->assertStringContainsString('unlockAllButtons', $html);
    }

    #[DataProvider('authenticatedRoutesProvider')]
    public function testUnauthenticatedRequestsRedirectToLogin(string $route): void
    {
        $request = new Request('GET', $route);
        $emptySession = [];
        $response = $this->app->handle($request, $emptySession);

        $this->assertSame(302, $response->getStatusCode(), "Unauthenticated GET {$route} must redirect");
        $this->assertSame('/login', $response->getHeader('Location'));
    }

    public function testLayoutChecksHtml5FormValidityBeforeLockingAndStacksGlobalAlertsAboveModals(): void
    {
        $response = $this->app->handle(new Request('GET', '/financial-report'), $this->authenticatedSession);
        $this->assertSame(200, $response->getStatusCode());
        $html = $response->getBody();

        // 1. Prevent premature button locking when HTML5 form constraint validation fails
        $this->assertStringContainsString('checkValidity', $html);

        // 2. Global progress bar and alert container stack above z-40/z-50 modals via explicit z-index
        $this->assertStringContainsString('z-index: 70', $html);
    }
}
