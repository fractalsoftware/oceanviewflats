<?php

declare(strict_types=1);

namespace OceanViewFlats\Admin\Tests\Middleware;

use OceanViewFlats\Admin\Auth\AdminRole;
use OceanViewFlats\Admin\Http\Request;
use OceanViewFlats\Admin\Http\RouteAccess;
use OceanViewFlats\Admin\Middleware\RbacMiddleware;
use PHPUnit\Framework\TestCase;

final class RbacMiddlewareTest extends TestCase
{
    private RbacMiddleware $middleware;

    protected function setUp(): void
    {
        $this->middleware = new RbacMiddleware();
    }

    public function testMissingOrUnknownRoleFailsClosedToViewerAndBlocksAllOperatorAndPrivilegedRoutes(): void
    {
        $session = ['admin_user_id' => 1];

        $this->assertSame(AdminRole::VIEWER, AdminRole::fromSession($session));
        $this->assertSame(AdminRole::VIEWER, AdminRole::fromSession(['admin_user_role' => 'superuser']));

        // Unannotated mutating routes fail closed (require PRIVILEGED)
        $this->assertSame(403, $this->middleware->process(new Request('POST', '/any-mutation'), $session)?->getStatusCode());
        $this->assertSame(403, $this->middleware->process(new Request('DELETE', '/rates/5'), $session)?->getStatusCode());

        // Explicit OPERATOR and PRIVILEGED routes denied
        $this->assertSame(403, $this->middleware->process(new Request('POST', '/calendar-blocks'), $session, RouteAccess::MANAGER)?->getStatusCode());
        $this->assertSame(403, $this->middleware->process(new Request('POST', '/reservations/ovf_1/cancel'), $session, RouteAccess::PRIVILEGED)?->getStatusCode());
        $this->assertSame(403, $this->middleware->process(new Request('GET', '/financial-report'), $session, RouteAccess::PRIVILEGED)?->getStatusCode());
    }

    public function testViewerCanReadAuthenticatedViewsAndLogoutButCannotOperateOrAccessPrivilegedDomains(): void
    {
        $session = ['admin_user_id' => 4, 'admin_user_role' => 'viewer'];

        $this->assertNull($this->middleware->process(new Request('GET', '/'), $session, RouteAccess::AUTHENTICATED));
        $this->assertNull($this->middleware->process(new Request('GET', '/reservations'), $session, RouteAccess::AUTHENTICATED));
        $this->assertNull($this->middleware->process(new Request('POST', '/logout'), $session, RouteAccess::PUBLIC));

        $this->assertSame(403, $this->middleware->process(new Request('GET', '/financial-report'), $session, RouteAccess::PRIVILEGED)?->getStatusCode());
        $this->assertSame(403, $this->middleware->process(new Request('GET', '/audit-logs'), $session, RouteAccess::PRIVILEGED)?->getStatusCode());
        $this->assertSame(403, $this->middleware->process(new Request('POST', '/channel-sync'), $session, RouteAccess::MANAGER)?->getStatusCode());
        $this->assertSame(403, $this->middleware->process(new Request('POST', '/calendar-blocks'), $session, RouteAccess::MANAGER)?->getStatusCode());
    }

    public function testManagerCanPerformOperatorActionsButNotPrivilegedActions(): void
    {
        $session = ['admin_user_id' => 3, 'admin_user_role' => 'manager'];

        $this->assertNull($this->middleware->process(new Request('POST', '/reservations/create-manual'), $session, RouteAccess::MANAGER));
        $this->assertNull($this->middleware->process(new Request('POST', '/calendar-blocks'), $session, RouteAccess::MANAGER));
        $this->assertNull($this->middleware->process(new Request('POST', '/channel-sync'), $session, RouteAccess::MANAGER));

        $this->assertSame(403, $this->middleware->process(new Request('POST', '/reservations/ovf_1/cancel'), $session, RouteAccess::PRIVILEGED)?->getStatusCode());
        $this->assertSame(403, $this->middleware->process(new Request('POST', '/reservations/ovf_1/door-code/override'), $session, RouteAccess::PRIVILEGED)?->getStatusCode());
        $this->assertSame(403, $this->middleware->process(new Request('POST', '/rates'), $session, RouteAccess::PRIVILEGED)?->getStatusCode());
        $this->assertSame(403, $this->middleware->process(new Request('GET', '/financial-report'), $session, RouteAccess::PRIVILEGED)?->getStatusCode());
        $this->assertSame(403, $this->middleware->process(new Request('GET', '/audit-logs'), $session, RouteAccess::PRIVILEGED)?->getStatusCode());
    }

    public function testAdminAndSuperadminCanAccessAllTiers(): void
    {
        foreach ([AdminRole::ADMIN->value, AdminRole::SUPERADMIN->value] as $role) {
            $session = ['admin_user_id' => 1, 'admin_user_role' => $role];
            $this->assertNull($this->middleware->process(new Request('POST', '/reservations/ovf_1/cancel'), $session, RouteAccess::PRIVILEGED));
            $this->assertNull($this->middleware->process(new Request('POST', '/calendar-blocks'), $session, RouteAccess::MANAGER));
            $this->assertNull($this->middleware->process(new Request('GET', '/financial-report'), $session, RouteAccess::PRIVILEGED));
            $this->assertNull($this->middleware->process(new Request('GET', '/audit-logs'), $session, RouteAccess::PRIVILEGED));
        }
    }
}
