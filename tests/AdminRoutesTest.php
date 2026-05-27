<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use zRoute\Router;
use zRoute\Route;

/**
 * Tests for the admin route registration defined in public/admin-routes.php.
 *
 * The file is included once per test run (via require_once) and the two public
 * helper functions it defines – admin_handler() and register_admin_routes() –
 * are then exercised directly without booting the full application.
 */
class AdminRoutesTest extends TestCase
{
    private Router $router;

    protected function setUp(): void
    {
        // Include the route-definition file. We use require_once so that the
        // file-level `use` declarations and function definitions are loaded
        // exactly once even when multiple test methods run.
        require_once __DIR__ . '/../public/admin-routes.php';

        $this->router = new Router();
    }

    // -----------------------------------------------------------------------
    // Helpers
    // -----------------------------------------------------------------------

    /**
     * Return all registered routes as [method => pattern] pairs.
     *
     * @return array<string, string[]>   method => list of patterns
     */
    private function routeMap(): array
    {
        $map = [];
        foreach ($this->router->getRoutes() as $route) {
            $map[$route->getMethod()][] = $route->getPattern();
        }
        return $map;
    }

    /**
     * Assert that a route with the given method and pattern exists.
     */
    private function assertRouteExists(string $method, string $pattern): void
    {
        $map = $this->routeMap();
        $this->assertContains(
            $pattern,
            $map[strtoupper($method)] ?? [],
            "Expected {$method} route '{$pattern}' to be registered."
        );
    }

    /**
     * Assert that a route registered under /admin matches the given path and
     * extracts the expected parameters.  Uses Route::matches() directly so
     * that controller code (and its session/DB requirements) is never invoked.
     *
     * @param array<string, string> $expectedParams
     */
    private function assertDispatchParams(
        string $method,
        string $path,
        array $expectedParams
    ): void {
        $spy = new Router();
        register_admin_routes($spy, '/admin');

        $found = false;
        foreach ($spy->getRoutes() as $route) {
            $params = $route->matches($method, $path);
            if ($params !== null) {
                $found = true;
                $this->assertSame(
                    $expectedParams,
                    $params,
                    "Params mismatch for {$method} {$path}"
                );
                break;
            }
        }

        $this->assertTrue($found, "No route matched {$method} {$path}");
    }

    // -----------------------------------------------------------------------
    // /admin prefix – static routes
    // -----------------------------------------------------------------------

    /** @test */
    public function testAdminLoginGetIsRegistered(): void
    {
        register_admin_routes($this->router, '/admin');
        $this->assertRouteExists('GET', '/admin/login');
    }

    /** @test */
    public function testAdminLoginPostIsRegistered(): void
    {
        register_admin_routes($this->router, '/admin');
        $this->assertRouteExists('POST', '/admin/login');
    }

    /** @test */
    public function testAdminLogoutPostIsRegistered(): void
    {
        register_admin_routes($this->router, '/admin');
        $this->assertRouteExists('POST', '/admin/logout');
    }

    /** @test */
    public function testAdminDashboardGetIsRegistered(): void
    {
        register_admin_routes($this->router, '/admin');
        $this->assertRouteExists('GET', '/admin');
    }

    // -----------------------------------------------------------------------
    // /admin prefix – categories
    // -----------------------------------------------------------------------

    /** @test */
    public function testCategoryIndexIsRegistered(): void
    {
        register_admin_routes($this->router, '/admin');
        $this->assertRouteExists('GET', '/admin/categories');
    }

    /** @test */
    public function testCategoryCreateFormIsRegistered(): void
    {
        register_admin_routes($this->router, '/admin');
        $this->assertRouteExists('GET', '/admin/categories/new');
    }

    /** @test */
    public function testCategoryEditFormPatternContainsIdPlaceholder(): void
    {
        register_admin_routes($this->router, '/admin');
        $this->assertRouteExists('GET', '/admin/categories/$id/edit');
    }

    /** @test */
    public function testCategoryStoreIsRegistered(): void
    {
        register_admin_routes($this->router, '/admin');
        $this->assertRouteExists('POST', '/admin/categories');
    }

    /** @test */
    public function testCategoryUpdatePatternContainsIdPlaceholder(): void
    {
        register_admin_routes($this->router, '/admin');
        $this->assertRouteExists('POST', '/admin/categories/$id');
    }

    /** @test */
    public function testCategoryDeletePatternContainsIdPlaceholder(): void
    {
        register_admin_routes($this->router, '/admin');
        $this->assertRouteExists('POST', '/admin/categories/$id/delete');
    }

    // -----------------------------------------------------------------------
    // /admin prefix – products
    // -----------------------------------------------------------------------

    /** @test */
    public function testProductIndexIsRegistered(): void
    {
        register_admin_routes($this->router, '/admin');
        $this->assertRouteExists('GET', '/admin/products');
    }

    /** @test */
    public function testProductCreateFormIsRegistered(): void
    {
        register_admin_routes($this->router, '/admin');
        $this->assertRouteExists('GET', '/admin/products/new');
    }

    /** @test */
    public function testProductEditFormPatternContainsIdPlaceholder(): void
    {
        register_admin_routes($this->router, '/admin');
        $this->assertRouteExists('GET', '/admin/products/$id/edit');
    }

    /** @test */
    public function testProductStoreIsRegistered(): void
    {
        register_admin_routes($this->router, '/admin');
        $this->assertRouteExists('POST', '/admin/products');
    }

    /** @test */
    public function testProductUpdatePatternContainsIdPlaceholder(): void
    {
        register_admin_routes($this->router, '/admin');
        $this->assertRouteExists('POST', '/admin/products/$id');
    }

    /** @test */
    public function testProductDeletePatternContainsIdPlaceholder(): void
    {
        register_admin_routes($this->router, '/admin');
        $this->assertRouteExists('POST', '/admin/products/$id/delete');
    }

    // -----------------------------------------------------------------------
    // /admin prefix – payment-gateways
    // -----------------------------------------------------------------------

    /** @test */
    public function testPaymentGatewayIndexIsRegistered(): void
    {
        register_admin_routes($this->router, '/admin');
        $this->assertRouteExists('GET', '/admin/payment-gateways');
    }

    /** @test */
    public function testPaymentGatewayCreateFormIsRegistered(): void
    {
        register_admin_routes($this->router, '/admin');
        $this->assertRouteExists('GET', '/admin/payment-gateways/new');
    }

    /** @test */
    public function testPaymentGatewayEditFormPatternContainsIdPlaceholder(): void
    {
        register_admin_routes($this->router, '/admin');
        $this->assertRouteExists('GET', '/admin/payment-gateways/$id/edit');
    }

    /** @test */
    public function testPaymentGatewayStoreIsRegistered(): void
    {
        register_admin_routes($this->router, '/admin');
        $this->assertRouteExists('POST', '/admin/payment-gateways');
    }

    /** @test */
    public function testPaymentGatewayUpdatePatternContainsIdPlaceholder(): void
    {
        register_admin_routes($this->router, '/admin');
        $this->assertRouteExists('POST', '/admin/payment-gateways/$id');
    }

    /** @test */
    public function testPaymentGatewayDeletePatternContainsIdPlaceholder(): void
    {
        register_admin_routes($this->router, '/admin');
        $this->assertRouteExists('POST', '/admin/payment-gateways/$id/delete');
    }

    // -----------------------------------------------------------------------
    // /admin prefix – shipping
    // -----------------------------------------------------------------------

    /** @test */
    public function testShippingIndexIsRegistered(): void
    {
        register_admin_routes($this->router, '/admin');
        $this->assertRouteExists('GET', '/admin/shipping');
    }

    /** @test */
    public function testShippingCreateFormIsRegistered(): void
    {
        register_admin_routes($this->router, '/admin');
        $this->assertRouteExists('GET', '/admin/shipping/new');
    }

    /** @test */
    public function testShippingEditFormPatternContainsIdPlaceholder(): void
    {
        register_admin_routes($this->router, '/admin');
        $this->assertRouteExists('GET', '/admin/shipping/$id/edit');
    }

    /** @test */
    public function testShippingStoreIsRegistered(): void
    {
        register_admin_routes($this->router, '/admin');
        $this->assertRouteExists('POST', '/admin/shipping');
    }

    /** @test */
    public function testShippingUpdatePatternContainsIdPlaceholder(): void
    {
        register_admin_routes($this->router, '/admin');
        $this->assertRouteExists('POST', '/admin/shipping/$id');
    }

    /** @test */
    public function testShippingDeletePatternContainsIdPlaceholder(): void
    {
        register_admin_routes($this->router, '/admin');
        $this->assertRouteExists('POST', '/admin/shipping/$id/delete');
    }

    // -----------------------------------------------------------------------
    // /administration prefix – spot-check
    // -----------------------------------------------------------------------

    /** @test */
    public function testAdministrationPrefixRegistersLogin(): void
    {
        register_admin_routes($this->router, '/administration');
        $this->assertRouteExists('GET', '/administration/login');
    }

    /** @test */
    public function testAdministrationPrefixCategoryEditHasIdPlaceholder(): void
    {
        register_admin_routes($this->router, '/administration');
        $this->assertRouteExists('GET', '/administration/categories/$id/edit');
    }

    /** @test */
    public function testAdministrationPrefixProductUpdateHasIdPlaceholder(): void
    {
        register_admin_routes($this->router, '/administration');
        $this->assertRouteExists('POST', '/administration/products/$id');
    }

    // -----------------------------------------------------------------------
    // Dispatch: $id placeholder must match a real numeric segment
    // -----------------------------------------------------------------------

    /** @test */
    public function testCategoryEditFormDispatchesWithId(): void
    {
        $this->assertDispatchParams('GET', '/admin/categories/42/edit', ['id' => '42']);
    }

    /** @test */
    public function testCategoryUpdateDispatchesWithId(): void
    {
        $this->assertDispatchParams('POST', '/admin/categories/7', ['id' => '7']);
    }

    /** @test */
    public function testCategoryDeleteDispatchesWithId(): void
    {
        $this->assertDispatchParams('POST', '/admin/categories/123/delete', ['id' => '123']);
    }

    /** @test */
    public function testProductEditFormDispatchesWithId(): void
    {
        $this->assertDispatchParams('GET', '/admin/products/99/edit', ['id' => '99']);
    }

    /** @test */
    public function testProductUpdateDispatchesWithId(): void
    {
        $this->assertDispatchParams('POST', '/admin/products/5', ['id' => '5']);
    }

    /** @test */
    public function testProductDeleteDispatchesWithId(): void
    {
        $this->assertDispatchParams('POST', '/admin/products/10/delete', ['id' => '10']);
    }

    /** @test */
    public function testPaymentGatewayEditFormDispatchesWithId(): void
    {
        $this->assertDispatchParams('GET', '/admin/payment-gateways/3/edit', ['id' => '3']);
    }

    /** @test */
    public function testShippingEditFormDispatchesWithId(): void
    {
        $this->assertDispatchParams('GET', '/admin/shipping/8/edit', ['id' => '8']);
    }

    // -----------------------------------------------------------------------
    // Regression: empty-segment paths must NOT match (pre-fix behaviour)
    // -----------------------------------------------------------------------

    /** @test */
    public function testCategoryEditDoesNotMatchEmptyId(): void
    {
        register_admin_routes($this->router, '/admin');

        $matched = false;
        foreach ($this->router->getRoutes() as $route) {
            if ($route->matches('GET', '/admin/categories//edit') !== null) {
                $matched = true;
                break;
            }
        }

        $this->assertFalse($matched, '/admin/categories//edit must not match any route.');
    }

    /** @test */
    public function testProductUpdateDoesNotMatchEmptyId(): void
    {
        register_admin_routes($this->router, '/admin');

        $matched = false;
        foreach ($this->router->getRoutes() as $route) {
            if ($route->matches('POST', '/admin/products/') !== null) {
                $matched = true;
                break;
            }
        }

        $this->assertFalse($matched, '/admin/products/ (empty id) must not match any route.');
    }
}
