<?php

declare(strict_types=1);

namespace Tests\Feature\Tenancy;

use App\Http\Middleware\SecurityHeaders;
use App\Models\Role;
use App\Models\Shop;
use App\Models\ShopUser;
use App\Models\User;
use App\Services\RbacService;
use App\Tenancy\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Routing\Route;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route as RouteFacade;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;
use Tests\TestCase;

class F2RouteCoverageTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        TenantContext::reset();
        parent::tearDown();
    }

    public function test_every_mutating_tenant_route_denies_a_member_without_its_permission(): void
    {
        [$shop, $user] = $this->createTenantMember(false);
        $routes = $this->tenantRoutes()->filter(fn (Route $route): bool => count(array_intersect(
            $route->methods(), ['POST', 'PUT', 'PATCH', 'DELETE']
        )) > 0);

        $this->assertNotEmpty($routes);

        foreach ($routes as $route) {
            $this->assertNotNull($route->getName(), 'Tenant route must be named: '.$route->uri());
            $parameters = $this->routeParameters($route, $shop);
            $method = collect($route->methods())->first(fn (string $candidate): bool => in_array(
                $candidate, ['POST', 'PUT', 'PATCH', 'DELETE'], true
            ));
            $url = route($route->getName(), $parameters);

            $response = $this->actingAs($user)
                ->withSession(['current_shop_id' => $shop->id])
                ->call($method, $url);

            $this->assertSame(403, $response->getStatusCode(), 'Mutating route '.$route->getName().' was not authorized.');
        }
    }

    public function test_every_named_tenant_get_route_returns_a_non_server_error_for_shop_owner(): void
    {
        [$shop, $user] = $this->createTenantMember(true);
        $routes = $this->tenantRoutes()->filter(fn (Route $route): bool => in_array('GET', $route->methods(), true));

        $this->assertNotEmpty($routes);

        foreach ($routes as $route) {
            $this->assertNotNull($route->getName(), 'Tenant route must be named: '.$route->uri());
            $parameters = $this->routeParameters($route, $shop);
            $url = route($route->getName(), $parameters);

            $response = $this->actingAs($user)
                ->withSession(['current_shop_id' => $shop->id])
                ->get($url);

            $this->assertLessThan(500, $response->getStatusCode(), 'GET route '.$route->getName().' returned a server error.');
        }
    }

    public function test_global_roles_are_not_visible_in_tenant_queries_and_units_are_read_only(): void
    {
        [$shop] = $this->createTenantMember(true);
        $globalRoleId = DB::table('roles')->insertGetId([
            'shop_id' => null,
            'name' => 'Global System Role',
            'guard_name' => 'web',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('audit_logs')->insert([
            'shop_id' => null,
            'user_id' => null,
            'event' => 'platform_test',
            'auditable_type' => 'platform',
            'auditable_id' => 1,
            'old_values' => null,
            'new_values' => null,
            'ip_address' => null,
            'user_agent' => null,
            'created_at' => now(),
        ]);

        TenantContext::runAs($shop, function () use ($globalRoleId): void {
            $this->assertNull(Role::find($globalRoleId));
        });

        $unitRouteNames = collect(RouteFacade::getRoutes())
            ->map(fn (Route $route): ?string => $route->getName())
            ->filter(fn (?string $name): bool => $name !== null && str_starts_with($name, 'units.'))
            ->values()
            ->all();

        $this->assertSame(['units.index'], $unitRouteNames);

        $auditRoutes = collect(RouteFacade::getRoutes())
            ->filter(fn (Route $route): bool => str_contains($route->getActionName(), 'AuditLog'));
        $this->assertCount(0, $auditRoutes, 'Global audit rows have no tenant-facing routes.');
    }

    public function test_security_headers_are_attached_to_web_responses(): void
    {
        $response = $this->get(route('home'));

        $response->assertOk()
            ->assertHeader('X-Frame-Options', 'SAMEORIGIN')
            ->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin')
            ->assertHeader('Permissions-Policy', 'camera=(), microphone=(), geolocation=()')
            ->assertHeaderMissing('Strict-Transport-Security');

        $this->assertNotEmpty($response->headers->get('Content-Security-Policy-Report-Only'));

        $environment = app()->environment();
        app()->detectEnvironment(fn (): string => 'production');
        try {
            $secureRequest = Request::create('https://example.test/');
            $secureResponse = (new SecurityHeaders)->handle(
                $secureRequest,
                fn () => new SymfonyResponse
            );

            $this->assertSame('max-age=31536000; includeSubDomains', $secureResponse->headers->get('Strict-Transport-Security'));
        } finally {
            app()->detectEnvironment(fn (): string => $environment);
        }
    }

    public function test_platform_routes_are_explicit_exceptions_in_tenant_matrix(): void
    {
        $exceptions = [
            'platform.shops.index' => 'Platform operator only; not tenant-scoped.',
            'platform.shops.toggle' => 'Platform operator only; not tenant-scoped.',
        ];

        foreach (array_keys($exceptions) as $name) {
            $this->assertTrue(RouteFacade::has($name), 'Platform route must exist: '.$name);
        }

        $platformRoutes = collect(RouteFacade::getRoutes())
            ->filter(fn (Route $route): bool => str_starts_with((string) $route->getName(), 'platform.'))
            ->map(fn (Route $route): ?string => $route->getName())
            ->values()
            ->all();

        $this->assertSame(array_keys($exceptions), $platformRoutes);
    }

    /** @return array{Shop, User} */
    private function createTenantMember(bool $owner): array
    {
        $shop = Shop::factory()->create();
        TenantContext::setShop($shop);
        $roles = app(RbacService::class)->createDefaultRolesForShop($shop);
        $role = $owner ? $roles['Shop Owner'] : Role::factory()->create(['name' => 'No Permissions']);
        $user = User::factory()->create();

        ShopUser::factory()->create([
            'shop_id' => $shop->id,
            'user_id' => $user->id,
            'role_id' => $role->id,
            'status' => 'active',
        ]);

        return [$shop, $user];
    }

    /** @return Collection<int, Route> */
    private function tenantRoutes(): Collection
    {
        return collect(RouteFacade::getRoutes())->filter(
            fn (Route $route): bool => in_array('tenant.resolve', $route->gatherMiddleware(), true)
        );
    }

    /** @return array<string, mixed> */
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
