<?php

declare(strict_types=1);

namespace Tests\Feature\Tenancy;

use App\Models\Shop;
use App\Models\ShopUser;
use App\Models\User;
use App\Services\RbacService;
use App\Tenancy\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Route;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Route as RouteFacade;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * T7d: a shop B owner requesting a shop A record on any tenant route that takes
 * a model parameter must get 404. A 200 leaks data; a 403 leaks existence.
 */
final class TenantRouteIsolationMatrixTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        TenantContext::reset();
        parent::tearDown();
    }

    public function test_shop_b_owner_gets_404_for_every_shop_a_record_on_every_tenant_route(): void
    {
        $shopA = Shop::factory()->create();
        $shopB = Shop::factory()->create();

        TenantContext::setShop($shopB);
        $rolesB = app(RbacService::class)->createDefaultRolesForShop($shopB);
        $ownerB = User::factory()->create();
        ShopUser::factory()->create([
            'shop_id' => $shopB->id,
            'user_id' => $ownerB->id,
            'role_id' => $rolesB['Shop Owner']->id,
            'status' => 'active',
        ]);

        $routes = $this->tenantRoutesWithParameters();
        $this->assertNotEmpty($routes, 'Tenant route matrix has no parameterised routes.');

        $leaks = [];

        foreach ($routes as $route) {
            $parameters = $this->routeParameters($route, $shopA);
            $url = route((string) $route->getName(), $parameters);

            TenantContext::setShop($shopB);

            foreach ($route->methods() as $method) {
                if (in_array($method, ['HEAD', 'OPTIONS'], true)) {
                    continue;
                }

                $response = $this->actingAs($ownerB)
                    ->withSession(['current_shop_id' => $shopB->id])
                    ->call($method, $url);

                $status = $response->getStatusCode();

                if ($status !== 404) {
                    $leaks[] = sprintf('%s %s -> %d (shop A record)', $method, $route->uri(), $status);
                }
            }
        }

        $this->assertSame([], $leaks, "Cross-tenant route leaks:\n".implode("\n", $leaks));
    }

    /** @return Collection<int, Route> */
    private function tenantRoutesWithParameters(): Collection
    {
        return collect(RouteFacade::getRoutes())
            ->filter(fn (Route $route): bool => in_array('tenant.resolve', $route->gatherMiddleware(), true))
            ->filter(fn (Route $route): bool => $route->getName() !== null)
            ->filter(fn (Route $route): bool => count($route->parameterNames()) > 0)
            ->values();
    }

    /**
     * @return array<string, mixed>
     */
    private function routeParameters(Route $route, Shop $shop): array
    {
        $parameters = [];

        TenantContext::runAs($shop, function () use ($route, $shop, &$parameters): void {
            foreach ($route->parameterNames() as $name) {
                $modelClass = 'App\\Models\\'.Str::studly($name);
                $this->assertTrue(class_exists($modelClass), 'No model fixture mapping for route parameter '.$name.'.');
                $this->assertTrue(method_exists($modelClass, 'factory'), $modelClass.' must define a factory.');

                $parameters[$name] = $modelClass::factory()->create(['shop_id' => $shop->id])->getRouteKey();
            }
        });

        return $parameters;
    }
}
