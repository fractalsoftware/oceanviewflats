<?php

declare(strict_types=1);

namespace OceanViewFlats\Admin\Tests\Middleware;

use OceanViewFlats\Admin\Http\Request;
use OceanViewFlats\Admin\Middleware\AuthMiddleware;
use PHPUnit\Framework\TestCase;

final class AuthMiddlewareTest extends TestCase
{
    private AuthMiddleware $middleware;

    protected function setUp(): void
    {
        $this->middleware = new AuthMiddleware();
    }

    public function testAllowsWhitelistedLoginRoute(): void
    {
        $session = [];
        $request = new Request('GET', '/login');

        $response = $this->middleware->process($request, $session);

        $this->assertNull($response);
    }

    public function testAllowsWhitelistedPostLoginRoute(): void
    {
        $session = [];
        $request = new Request('POST', '/login');

        $response = $this->middleware->process($request, $session);

        $this->assertNull($response);
    }

    public function testBlocksUnwhitelistedVerbOnLoginRoute(): void
    {
        $session = [];
        $request = new Request('DELETE', '/login');

        $response = $this->middleware->process($request, $session);

        $this->assertNotNull($response);
        $this->assertSame(302, $response->getStatusCode());
    }

    public function testAllowsWhitelistedLogoutRoute(): void
    {
        $session = [];
        $request = new Request('POST', '/logout');

        $response = $this->middleware->process($request, $session);

        $this->assertNull($response);
    }

    public function testAllowsAuthenticatedUserToAccessProtectedRoutes(): void
    {
        $session = ['admin_user_id' => 42];
        $request = new Request('GET', '/reservations');

        $response = $this->middleware->process($request, $session);

        $this->assertNull($response);
    }

    public function testRedirectsUnauthenticatedBrowserRequestWith302(): void
    {
        $session = [];
        $request = new Request('GET', '/reservations');

        $response = $this->middleware->process($request, $session);

        $this->assertNotNull($response);
        $this->assertSame(302, $response->getStatusCode());
        $this->assertSame('/login', $response->getHeaders()['Location']);
    }

    public function testRedirectsUnauthenticatedHtmxRequestWith401AndHxRedirectHeader(): void
    {
        $session = [];
        $request = new Request(
            method: 'GET',
            uri: '/reservations/rows',
            server: ['HTTP_HX_REQUEST' => 'true']
        );

        $response = $this->middleware->process($request, $session);

        $this->assertNotNull($response);
        $this->assertSame(401, $response->getStatusCode());
        $this->assertSame('/login', $response->getHeaders()['HX-Redirect']);
    }
}
