<?php

declare(strict_types=1);

namespace Tests\Feature\Ui;

use App\Enums\LedgerEntryType;
use App\Models\Customer;
use App\Models\CustomerLedgerEntry;
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

final class RenderedPagesLocalizationTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        TenantContext::reset();
        parent::tearDown();
    }

    public function test_rendered_pages_never_show_a_double_currency_prefix(): void
    {
        [$shop, $user] = $this->createOwnerWithData();

        $this->actingAs($user)->withSession(['current_shop_id' => $shop->id]);

        foreach ($this->tenantGetRoutes() as $route) {
            $name = (string) $route->getName();
            if (in_array($name, ['products.import.template', 'products.import.template.xlsx', 'products.import.errors', 'products.import.errors.xlsx'], true)) {
                continue;
            }

            $response = $this->get(route($name, $this->routeParameters($route, $shop)));
            $content = $response->getContent();

            if ($response->getStatusCode() !== 200 || ! is_string($content)) {
                continue;
            }

            $this->assertDoesNotMatchRegularExpression('/Rs\.\s*Rs\./u', $content, 'Double Rs. prefix on '.$name);
            $this->assertDoesNotMatchRegularExpression('/रु\.\s*रु\./u', $content, 'Double रु. prefix on '.$name);
        }
    }

    public function test_customer_page_uses_locale_digits_for_money(): void
    {
        [$shop, $user, $customer] = $this->createOwnerWithData();

        $user->update(['locale' => 'en']);
        $english = $this->actingAs($user)
            ->withSession(['current_shop_id' => $shop->id, 'locale' => 'en'])
            ->get(route('customers.show', $customer));

        $english->assertOk();
        $englishHtml = (string) $english->getContent();
        $this->assertStringContainsString('Rs. 8,000.00', $englishHtml);
        $this->assertDoesNotMatchRegularExpression('/Rs\.\s*[०-९]/u', $englishHtml, 'English money must use ASCII digits.');

        $user->update(['locale' => 'ne']);
        $nepali = $this->actingAs($user)
            ->withSession(['current_shop_id' => $shop->id, 'locale' => 'ne'])
            ->get(route('customers.show', $customer));

        $nepali->assertOk();
        $nepaliHtml = (string) $nepali->getContent();
        $this->assertStringContainsString('रु. ८,०००.००', $nepaliHtml);
        $this->assertDoesNotMatchRegularExpression('/रु\.\s*[0-9]/u', $nepaliHtml, 'Nepali money must use Devanagari digits.');
    }

    public function test_rendered_pages_do_not_show_raw_translation_keys(): void
    {
        [$shop, $user] = $this->createOwnerWithData();

        $groups = array_map(
            static fn (string $file): string => basename($file, '.php'),
            glob(base_path('lang/en').'/*.php') ?: []
        );
        $this->assertNotEmpty($groups);

        $pattern = '/\b(?:'.implode('|', array_map(static fn (string $group): string => preg_quote($group, '/'), $groups)).')\.[A-Za-z0-9_]+(?:\.[A-Za-z0-9_]+)?\b/u';

        foreach (['en', 'ne'] as $locale) {
            $user->update(['locale' => $locale]);
            $this->actingAs($user)->withSession(['current_shop_id' => $shop->id, 'locale' => $locale]);

            foreach ($this->tenantGetRoutes() as $route) {
                $name = (string) $route->getName();
                if (in_array($name, ['products.import.template', 'products.import.template.xlsx', 'products.import.errors', 'products.import.errors.xlsx'], true)) {
                    continue;
                }

                $response = $this->get(route($name, $this->routeParameters($route, $shop)));
                $content = $response->getContent();

                if ($response->getStatusCode() !== 200 || ! is_string($content) || ! str_contains((string) $response->headers->get('Content-Type'), 'text/html')) {
                    continue;
                }

                $visible = $this->visibleText($content);
                preg_match_all($pattern, $visible, $matches);

                $this->assertSame(
                    [],
                    array_values(array_unique($matches[0] ?? [])),
                    'Raw translation keys rendered on '.$name.' ('.$locale.')'
                );
            }
        }
    }

    /** @return array{Shop, User, Customer} */
    private function createOwnerWithData(): array
    {
        $shop = Shop::factory()->create();
        TenantContext::setShop($shop);
        $roles = app(RbacService::class)->createDefaultRolesForShop($shop);
        $user = User::factory()->create(['locale' => 'en']);
        ShopUser::create([
            'shop_id' => $shop->id,
            'user_id' => $user->id,
            'role_id' => $roles['Shop Owner']->id,
            'status' => 'active',
        ]);

        $customer = Customer::factory()->create([
            'shop_id' => $shop->id,
            'name' => 'Ram Bahadur',
            'phone' => '9800000001',
            'credit_limit' => '10000.00',
        ]);

        CustomerLedgerEntry::create([
            'shop_id' => $shop->id,
            'customer_id' => $customer->id,
            'entry_type' => LedgerEntryType::ManualDebit->value,
            'debit' => '8000.00',
            'credit' => '0.00',
        ]);

        return [$shop, $user, $customer];
    }

    /** @return Collection<int, Route> */
    private function tenantGetRoutes(): Collection
    {
        return collect(RouteFacade::getRoutes())
            ->filter(fn (Route $route): bool => in_array('tenant.resolve', $route->gatherMiddleware(), true))
            ->filter(fn (Route $route): bool => in_array('GET', $route->methods(), true))
            ->filter(fn (Route $route): bool => $route->getName() !== null)
            ->values();
    }

    /** @return array<string, mixed> */
    private function routeParameters(Route $route, Shop $shop): array
    {
        $parameters = [];

        TenantContext::runAs($shop, function () use ($route, $shop, &$parameters): void {
            foreach ($route->parameterNames() as $name) {
                $modelClass = 'App\\Models\\'.Str::studly($name);
                if (! class_exists($modelClass) || ! method_exists($modelClass, 'factory')) {
                    $this->fail('No model fixture mapping for route parameter '.$name.' on '.$route->uri());
                }

                $parameters[$name] = $modelClass::factory()->create(['shop_id' => $shop->id])->getRouteKey();
            }
        });

        return $parameters;
    }

    private function visibleText(string $html): string
    {
        $html = preg_replace('/<!--.*?-->/s', ' ', $html) ?? $html;
        $html = preg_replace('/<script\b[^>]*>.*?<\/script\s*>/is', ' ', $html) ?? $html;
        $html = preg_replace('/<style\b[^>]*>.*?<\/style\s*>/is', ' ', $html) ?? $html;

        return trim(html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
    }
}
