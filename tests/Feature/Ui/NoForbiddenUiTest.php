<?php

declare(strict_types=1);

namespace Tests\Feature\Ui;

use App\Enums\LedgerEntryType;
use App\Models\CashSession;
use App\Models\Category;
use App\Models\Customer;
use App\Models\CustomerLedgerEntry;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\Product;
use App\Models\Purchase;
use App\Models\Sale;
use App\Models\Shop;
use App\Models\ShopUser;
use App\Models\StockBalance;
use App\Models\Supplier;
use App\Models\Unit;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\RbacService;
use App\Support\RoutePermissionMap;
use App\Tenancy\TenantContext;
use DOMDocument;
use DOMXPath;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Routing\Route;
use Illuminate\Support\Facades\Route as RouteFacade;
use Tests\TestCase;
use Throwable;

/**
 * Proves that no page renders a link or form the signed-in role may not use,
 * and that every rendered GET link still returns a real page for that role.
 *
 * The server stays authoritative; this test mirrors the authorization map that
 * drives @canRoute and then exercises the real HTTP endpoints.
 */
final class NoForbiddenUiTest extends TestCase
{
    use RefreshDatabase;

    private const ROLES = [
        'Shop Owner',
        'Manager',
        'Cashier',
        'Inventory Staff',
        'Accountant',
    ];

    /** @var array<string, int> */
    private array $visitedGetUrls = [];

    protected function tearDown(): void
    {
        TenantContext::reset();
        parent::tearDown();
    }

    public function test_pages_only_show_actions_the_role_can_use(): void
    {
        [$shop, $memberships, $fixtures] = $this->createShopFixtures();
        $violations = [];

        foreach (self::ROLES as $roleName) {
            $membership = $memberships[$roleName];
            $user = $membership->user;
            $this->actingAs($user)->withSession(['current_shop_id' => $shop->id]);
            $this->visitedGetUrls = [];

            foreach ($this->pages($fixtures) as [$routeName, $parameters]) {
                $model = $parameters[0] ?? null;
                $url = route($routeName, $parameters);
                $allowed = $this->allowsFor($shop, $membership, $routeName, $model);
                $response = $this->get($url);
                $status = $response->getStatusCode();

                if ($allowed && $status !== 200) {
                    $violations[] = sprintf('%s: %s should be allowed but returned %d', $roleName, $routeName, $status);

                    continue;
                }

                if (! $allowed && $status !== 403) {
                    $violations[] = sprintf('%s: %s should be forbidden but returned %d', $roleName, $routeName, $status);

                    continue;
                }

                if (! $allowed) {
                    continue;
                }

                $html = (string) $response->getContent();
                [$links, $forms] = $this->extractNavigation($html);

                foreach ($links as $href) {
                    $this->inspectLink($shop, $membership, $roleName, $routeName, $href, $fixtures, $violations);
                }

                foreach ($forms as $form) {
                    $this->inspectForm($shop, $membership, $roleName, $routeName, $form, $fixtures, $violations);
                }
            }
        }

        $this->assertSame([], $violations, "Forbidden UI actions were rendered:\n".implode("\n", $violations));
    }

    public function test_permission_map_covers_every_named_tenant_route(): void
    {
        $missing = [];

        foreach (RouteFacade::getRoutes() as $route) {
            if (! in_array('tenant.resolve', $route->gatherMiddleware(), true)) {
                continue;
            }

            $name = $route->getName();

            if ($name !== null && ! RoutePermissionMap::has($name)) {
                $missing[] = $name;
            }
        }

        $this->assertSame([], $missing, "Tenant routes without a permission map entry:\n".implode("\n", $missing));
    }

    public function test_permission_map_only_references_existing_routes(): void
    {
        foreach (array_keys(RoutePermissionMap::all()) as $name) {
            $this->assertTrue(RouteFacade::has($name), 'Permission map references a missing route: '.$name);
        }
    }

    public function test_cashier_sidebar_hides_staff_settings_and_reports_while_owner_sees_everything(): void
    {
        [$shop, $memberships] = $this->createShopFixtures();

        $cashier = $memberships['Cashier']->user;
        $this->actingAs($cashier)->withSession(['current_shop_id' => $shop->id]);
        $cashierSidebar = $this->sidebarLinks((string) $this->get(route('dashboard'))->assertOk()->getContent());

        $forbiddenForCashier = ['staff.index', 'settings.shop', 'reports.profit', 'reports.stock'];
        foreach ($forbiddenForCashier as $routeName) {
            $this->assertNotContains(
                route($routeName),
                $cashierSidebar,
                'Cashier sidebar must not link to '.$routeName
            );
        }

        $this->assertNotContains(route('shops.picker'), $cashierSidebar, 'Switch shop needs more than one active shop.');
        $this->assertNotContains(route('platform.shops.index'), $cashierSidebar, 'Platform shops are for platform admins only.');

        $owner = $memberships['Shop Owner']->user;
        $this->actingAs($owner)->withSession(['current_shop_id' => $shop->id]);
        $ownerSidebar = $this->sidebarLinks((string) $this->get(route('dashboard'))->assertOk()->getContent());

        foreach ([
            'dashboard',
            'pos',
            'sales.index',
            'customers.index',
            'udhar.outstanding',
            'products.index',
            'stock.index',
            'purchases.index',
            'suppliers.index',
            'expenses.index',
            'reports.profit',
            'cash.index',
            'staff.index',
            'settings.shop',
        ] as $routeName) {
            $this->assertContains(
                route($routeName),
                $ownerSidebar,
                'Owner sidebar must link to '.$routeName
            );
        }

        $this->assertNotContains(route('platform.shops.index'), $ownerSidebar, 'Platform shops are for platform admins only.');

        $owner->update(['is_platform_admin' => true]);
        $adminSidebar = $this->sidebarLinks((string) $this->get(route('dashboard'))->assertOk()->getContent());
        $this->assertContains(route('platform.shops.index'), $adminSidebar, 'Platform admin must see platform shops.');
    }

    public function test_switch_shop_only_appears_with_more_than_one_active_shop(): void
    {
        [$shop, $memberships] = $this->createShopFixtures();
        $owner = $memberships['Shop Owner']->user;

        $this->actingAs($owner)->withSession(['current_shop_id' => $shop->id]);
        $this->assertNotContains(
            route('shops.picker'),
            $this->sidebarLinks((string) $this->get(route('dashboard'))->assertOk()->getContent()),
            'A single-shop user must not see the shop switcher.'
        );

        $secondShop = Shop::factory()->create();
        TenantContext::runAs($secondShop, function () use ($secondShop, $owner): void {
            $roles = app(RbacService::class)->createDefaultRolesForShop($secondShop);
            ShopUser::create([
                'shop_id' => $secondShop->id,
                'user_id' => $owner->id,
                'role_id' => $roles['Shop Owner']->id,
                'status' => 'active',
            ]);
        });

        $this->actingAs($owner)->withSession(['current_shop_id' => $shop->id]);
        $this->assertContains(
            route('shops.picker'),
            $this->sidebarLinks((string) $this->get(route('dashboard'))->assertOk()->getContent()),
            'A multi-shop user must see the shop switcher.'
        );
    }

    public function test_forbidden_page_is_localized_and_offers_a_dashboard_link(): void
    {
        [$shop, $memberships] = $this->createShopFixtures();
        $cashier = $memberships['Cashier']->user;
        $cashier->update(['locale' => 'en']);

        $english = $this->actingAs($cashier)
            ->withSession(['current_shop_id' => $shop->id, 'locale' => 'en'])
            ->get(route('staff.index'));

        $english->assertForbidden();
        $english->assertSee(__('errors.forbidden_title', [], 'en'), false);
        $english->assertSee(route('dashboard'), false);

        $cashier->update(['locale' => 'ne']);

        $nepali = $this->actingAs($cashier)
            ->withSession(['current_shop_id' => $shop->id, 'locale' => 'ne'])
            ->get(route('staff.index'));

        $nepali->assertForbidden();
        $nepali->assertSee(__('errors.forbidden_title', [], 'ne'), false);
        $nepali->assertSee(route('dashboard'), false);
    }

    /**
     * @param  array<string, mixed>  $fixtures
     * @return list<array{0: string, 1: array<int, mixed>}>
     */
    private function pages(array $fixtures): array
    {
        return [
            ['dashboard', []],
            ['pos', []],
            ['products.index', []],
            ['products.create', []],
            ['products.edit', [$fixtures['product']]],
            ['categories.index', []],
            ['units.index', []],
            ['customers.index', []],
            ['customers.create', []],
            ['customers.show', [$fixtures['customer']]],
            ['customers.edit', [$fixtures['customer']]],
            ['udhar.outstanding', []],
            ['udhar.ageing', []],
            ['udhar.collect', [$fixtures['customer']]],
            ['sales.index', []],
            ['sales.show', [$fixtures['sale']]],
            ['sales.return', [$fixtures['sale']]],
            ['purchases.index', []],
            ['purchases.create', []],
            ['purchases.show', [$fixtures['purchase']]],
            ['suppliers.index', []],
            ['suppliers.create', []],
            ['stock.index', []],
            ['stock.adjust', []],
            ['stock.alerts', []],
            ['stock.history', [$fixtures['product']]],
            ['expenses.index', []],
            ['expenses.create', []],
            ['expense-categories.index', []],
            ['expense-categories.create', []],
            ['reports.profit', []],
            ['reports.stock', []],
            ['staff.index', []],
            ['settings.shop', []],
            ['products.import.create', []],
            ['cash.index', []],
            ['cash.show', [$fixtures['cashSession']]],
        ];
    }

    /**
     * @param  array<string, mixed>  $fixtures
     * @param  list<string>  $violations
     */
    private function inspectLink(
        Shop $shop,
        ShopUser $membership,
        string $roleName,
        string $page,
        string $href,
        array $fixtures,
        array &$violations
    ): void {
        if (! $this->isSameOrigin($href)) {
            return;
        }

        $route = $this->matchRoute($href, 'GET');

        if ($route === null || ! $this->isTenantRoute($route) || $route->getName() === null) {
            return;
        }

        $routeName = (string) $route->getName();

        if (! RoutePermissionMap::has($routeName)) {
            $violations[] = sprintf('%s on %s: link to unmapped tenant route %s (%s)', $roleName, $page, $routeName, $href);

            return;
        }

        if (! $this->allowsFor($shop, $membership, $routeName, $this->modelForRoute($route, $fixtures))) {
            $violations[] = sprintf('%s on %s: link to forbidden route %s (%s)', $roleName, $page, $routeName, $href);

            return;
        }

        $key = $roleName.'|'.$href;
        if (! array_key_exists($key, $this->visitedGetUrls)) {
            $this->visitedGetUrls[$key] = $this->get($href)->getStatusCode();
        }

        $status = $this->visitedGetUrls[$key];
        if (in_array($status, [403, 404], true)) {
            $violations[] = sprintf('%s on %s: visible link %s (%s) returned %d', $roleName, $page, $routeName, $href, $status);
        }
    }

    /**
     * @param  array{action: string, method: string}  $form
     * @param  array<string, mixed>  $fixtures
     * @param  list<string>  $violations
     */
    private function inspectForm(
        Shop $shop,
        ShopUser $membership,
        string $roleName,
        string $page,
        array $form,
        array $fixtures,
        array &$violations
    ): void {
        $action = $form['action'];
        $method = $form['method'];

        if (! $this->isSameOrigin($action)) {
            return;
        }

        $route = $this->matchRoute($action, $method);

        if ($route === null || ! $this->isTenantRoute($route) || $route->getName() === null) {
            return;
        }

        $routeName = (string) $route->getName();

        if (! RoutePermissionMap::has($routeName)) {
            $violations[] = sprintf('%s on %s: form to unmapped tenant route %s (%s)', $roleName, $page, $routeName, $action);

            return;
        }

        if (! $this->allowsFor($shop, $membership, $routeName, $this->modelForRoute($route, $fixtures))) {
            $violations[] = sprintf('%s on %s: forbidden form %s %s', $roleName, $page, $method, $action);
        }
    }

    private function allowsFor(Shop $shop, ShopUser $membership, string $routeName, mixed $model = null): bool
    {
        return (bool) TenantContext::runAs(
            $shop,
            static fn (): bool => RoutePermissionMap::allows($routeName, $model),
            $membership
        );
    }

    private function isTenantRoute(Route $route): bool
    {
        return in_array('tenant.resolve', $route->gatherMiddleware(), true);
    }

    private function matchRoute(string $url, string $method): ?Route
    {
        try {
            return RouteFacade::getRoutes()->match(Request::create($url, strtoupper($method)));
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * @param  array<string, mixed>  $fixtures
     */
    private function modelForRoute(Route $route, array $fixtures): mixed
    {
        foreach ($route->parameterNames() as $parameter) {
            if (array_key_exists($parameter, $fixtures)) {
                return $fixtures[$parameter];
            }
        }

        return null;
    }

    private function isSameOrigin(string $url): bool
    {
        $url = trim($url);

        if ($url === '' || str_starts_with($url, '#') || str_starts_with($url, 'javascript:')) {
            return false;
        }

        if (str_starts_with($url, 'mailto:') || str_starts_with($url, 'tel:')) {
            return false;
        }

        $parts = parse_url($url);

        if ($parts === false) {
            return false;
        }

        if (! isset($parts['scheme'])) {
            return true;
        }

        if (! in_array(strtolower($parts['scheme']), ['http', 'https'], true)) {
            return false;
        }

        $host = strtolower($parts['host'] ?? '');
        $appHost = strtolower((string) parse_url((string) config('app.url'), PHP_URL_HOST));

        return $host === 'localhost' || $host === '127.0.0.1' || ($appHost !== '' && $host === $appHost);
    }

    /**
     * @return array{0: list<string>, 1: list<array{action: string, method: string}>}
     */
    private function extractNavigation(string $html): array
    {
        $previous = libxml_use_internal_errors(true);
        $document = new DOMDocument;
        $document->loadHTML('<?xml encoding="utf-8" ?>'.$html);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        $xpath = new DOMXPath($document);
        $links = [];

        foreach ($xpath->query('//a[@href]') ?: [] as $node) {
            $links[] = (string) $node->getAttribute('href');
        }

        $forms = [];

        foreach ($xpath->query('//form[@action]') ?: [] as $node) {
            $method = strtoupper((string) ($node->getAttribute('method') ?: 'GET'));

            if ($method === 'POST') {
                foreach ($xpath->query('.//input[@name="_method"]', $node) ?: [] as $input) {
                    $spoofed = strtoupper((string) $input->getAttribute('value'));

                    if ($spoofed !== '') {
                        $method = $spoofed;
                    }
                }
            }

            $forms[] = [
                'action' => (string) $node->getAttribute('action'),
                'method' => $method,
            ];
        }

        return [$links, $forms];
    }

    /**
     * @return list<string>
     */
    private function sidebarLinks(string $html): array
    {
        $previous = libxml_use_internal_errors(true);
        $document = new DOMDocument;
        $document->loadHTML('<?xml encoding="utf-8" ?>'.$html);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        $xpath = new DOMXPath($document);
        $links = [];

        foreach ($xpath->query('//aside[@id="appSidebar"]//a[@href]') ?: [] as $node) {
            $links[] = (string) $node->getAttribute('href');
        }

        return $links;
    }

    /**
     * @return array{0: Shop, 1: array<string, ShopUser>, 2: array<string, mixed>}
     */
    private function createShopFixtures(): array
    {
        $shop = Shop::factory()->create();
        TenantContext::setShop($shop);
        $roles = app(RbacService::class)->createDefaultRolesForShop($shop);

        $memberships = [];

        foreach (self::ROLES as $roleName) {
            $user = User::factory()->create(['locale' => 'en']);
            $memberships[$roleName] = ShopUser::create([
                'shop_id' => $shop->id,
                'user_id' => $user->id,
                'role_id' => $roles[$roleName]->id,
                'status' => 'active',
            ])->setRelation('user', $user)->setRelation('role', $roles[$roleName]);
        }

        $owner = $memberships['Shop Owner']->user;

        $fixtures = TenantContext::runAs($shop, function () use ($shop, $owner, $roles): array {
            $warehouse = Warehouse::factory()->create([
                'shop_id' => $shop->id,
                'is_default' => true,
            ]);
            $unit = Unit::factory()->create();
            $category = Category::factory()->create(['shop_id' => $shop->id]);
            $product = Product::factory()->create([
                'shop_id' => $shop->id,
                'category_id' => $category->id,
                'unit_id' => $unit->id,
            ]);

            $stockBalance = StockBalance::factory()->create([
                'shop_id' => $shop->id,
                'warehouse_id' => $warehouse->id,
                'product_id' => $product->id,
                'quantity' => '50.000',
            ]);

            $customer = Customer::factory()->create([
                'shop_id' => $shop->id,
                'name' => 'Sita Sharma',
                'phone' => '9800000001',
            ]);

            CustomerLedgerEntry::create([
                'shop_id' => $shop->id,
                'customer_id' => $customer->id,
                'entry_type' => LedgerEntryType::ManualDebit->value,
                'debit' => '8000.00',
                'credit' => '0.00',
            ]);

            $supplier = Supplier::factory()->create(['shop_id' => $shop->id]);

            $purchase = Purchase::factory()->create([
                'shop_id' => $shop->id,
                'warehouse_id' => $warehouse->id,
                'supplier_id' => $supplier->id,
                'created_by' => $owner->id,
            ]);

            $sale = Sale::factory()->posted()->create([
                'shop_id' => $shop->id,
                'customer_id' => $customer->id,
            ]);

            $expenseCategory = ExpenseCategory::factory()->create(['shop_id' => $shop->id]);

            $expense = Expense::factory()->create([
                'shop_id' => $shop->id,
                'category_id' => $expenseCategory->id,
                'created_by' => $owner->id,
            ]);

            $cashSession = CashSession::factory()->create([
                'shop_id' => $shop->id,
                'opened_by' => $owner->id,
            ]);

            $staffMember = ShopUser::factory()->create([
                'shop_id' => $shop->id,
                'user_id' => User::factory()->create(['locale' => 'en'])->id,
                'role_id' => $roles['Cashier']->id,
                'status' => 'active',
            ]);

            return [
                'warehouse' => $warehouse,
                'unit' => $unit,
                'category' => $category,
                'product' => $product,
                'stockBalance' => $stockBalance,
                'customer' => $customer,
                'supplier' => $supplier,
                'purchase' => $purchase,
                'sale' => $sale,
                'expenseCategory' => $expenseCategory,
                'expense' => $expense,
                'cashSession' => $cashSession,
                'shopUser' => $staffMember,
                // Route parameter aliases used by named routes.
                'expense_category' => $expenseCategory,
                'productImport' => null,
            ];
        });

        return [$shop, $memberships, $fixtures];
    }
}
