<?php

declare(strict_types=1);

namespace Tests\Feature\Platform;

use App\Actions\Platform\ReactivateShop;
use App\Actions\Platform\SuspendShop;
use App\Enums\PurchaseStatus;
use App\Enums\SaleStatus;
use App\Models\AuditLog;
use App\Models\Customer;
use App\Models\Plan;
use App\Models\Product;
use App\Models\Purchase;
use App\Models\Sale;
use App\Models\Shop;
use App\Models\ShopUser;
use App\Models\Subscription;
use App\Models\User;
use App\Services\Platform\PlatformShopInsights;
use App\Services\RbacService;
use App\Support\PlatformAuditEvents;
use App\Tenancy\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class PlatformDetailAuditTest extends TestCase
{
    use RefreshDatabase;

    private function admin(?string $email = null): User
    {
        return User::factory()->create([
            'is_platform_admin' => true,
            'email_verified_at' => now(),
            'email' => $email ?? 'admin-'.fake()->unique()->safeEmail(),
            'locale' => 'en',
        ]);
    }

    private function member(Shop $shop, string $roleName, array $attributes = []): User
    {
        $user = User::factory()->create(array_merge([
            'email_verified_at' => now(),
            'locale' => 'en',
        ], $attributes));

        TenantContext::runAs($shop, function () use ($shop, $user, $roleName): void {
            $roles = app(RbacService::class)->createDefaultRolesForShop($shop);

            ShopUser::create([
                'shop_id' => $shop->id,
                'user_id' => $user->id,
                'role_id' => $roles[$roleName]->id,
                'status' => 'active',
            ]);
        });

        return $user;
    }

    private function platformAudit(User $admin, Shop $shop, string $event, ?string $reason, Carbon $createdAt): AuditLog
    {
        $newValues = [
            'shop_id' => $shop->id,
            'shop_name' => $shop->name,
        ];

        if ($reason !== null) {
            $newValues['reason'] = $reason;
        }

        return AuditLog::create([
            'shop_id' => null,
            'user_id' => $admin->id,
            'event' => $event,
            'auditable_type' => (new Shop)->getMorphClass(),
            'auditable_id' => $shop->id,
            'old_values' => null,
            'new_values' => $newValues,
            'ip_address' => null,
            'user_agent' => null,
            'created_at' => $createdAt,
        ]);
    }

    private function tenantAudit(Shop $shop, string $event, array $newValues): AuditLog
    {
        return AuditLog::create([
            'shop_id' => $shop->id,
            'user_id' => null,
            'event' => $event,
            'auditable_type' => 'App\\Models\\Customer',
            'auditable_id' => 1,
            'old_values' => null,
            'new_values' => $newValues,
            'ip_address' => null,
            'user_agent' => null,
            'created_at' => now(),
        ]);
    }

    public function test_authorization_for_detail_and_audit(): void
    {
        $shop = Shop::factory()->create();
        $admin = $this->admin('platform-admin@example.test');

        $this->get(route('platform.shops.show', $shop))->assertRedirect('/login');
        $this->get(route('platform.audit.index'))->assertRedirect('/login');

        $owner = $this->member($shop, 'Shop Owner');
        $cashier = $this->member($shop, 'Cashier');

        foreach ([$owner, $cashier] as $tenantUser) {
            $this->actingAs($tenantUser);
            $this->get(route('platform.shops.show', $shop))->assertStatus(403);
            $this->get(route('platform.audit.index'))->assertStatus(403);
        }

        $unverified = User::factory()->create([
            'is_platform_admin' => true,
            'email_verified_at' => null,
        ]);
        $this->actingAs($unverified);
        $this->get(route('platform.shops.show', $shop))->assertRedirect(route('verification.notice'));
        $this->get(route('platform.audit.index'))->assertRedirect(route('verification.notice'));

        $this->actingAs($admin);
        $this->get(route('platform.shops.show', $shop))
            ->assertOk()
            ->assertSee(route('platform.audit.index', ['shop' => $shop->id]), false);
        $this->get(route('platform.audit.index'))
            ->assertOk()
            ->assertSee(route('platform.shops.index'), false);
        $this->get(route('platform.shops.index'))
            ->assertOk()
            ->assertSee(route('platform.shops.show', $shop), false);
    }

    public function test_insights_are_correct_isolated_and_rendered(): void
    {
        $this->travelTo(Carbon::parse('2026-11-17 17:45:00', 'UTC'));

        $admin = $this->admin();
        $shop = Shop::factory()->create([
            'name' => 'Insights Shop',
            'slug' => 'insights-shop',
            'timezone' => 'Asia/Kathmandu',
            'settings' => ['auto_reminders_enabled' => true],
        ]);
        $otherShop = Shop::factory()->create([
            'name' => 'Other Shop',
            'timezone' => 'Asia/Kathmandu',
        ]);

        $owner = $this->member($shop, 'Shop Owner', [
            'name' => 'Insights Owner',
            'email' => 'owner-insights@example.test',
        ]);
        $this->member($shop, 'Cashier');

        $nowLocal = Carbon::parse('2026-11-17 23:30:00', 'Asia/Kathmandu');
        $includedEdge = $nowLocal->copy()->subDays(30)->addMinute();
        $excludedEdge = $nowLocal->copy()->subDays(30)->subMinute();
        $lastSaleAt = $nowLocal->copy()->subHour();
        $postedPurchaseAt = $nowLocal->copy()->subDays(2);

        TenantContext::runAs($shop, function () use ($shop, $nowLocal, $includedEdge, $excludedEdge, $lastSaleAt, $postedPurchaseAt): void {
            Product::factory()->count(2)->create(['shop_id' => $shop->id]);
            Customer::factory()->create(['shop_id' => $shop->id]);

            foreach ([$includedEdge, $excludedEdge, $lastSaleAt] as $createdAt) {
                Sale::factory()->create([
                    'shop_id' => $shop->id,
                    'status' => SaleStatus::Posted,
                    'created_at' => $createdAt->copy()->utc(),
                    'updated_at' => $createdAt->copy()->utc(),
                ]);
            }

            Purchase::factory()->posted()->create([
                'shop_id' => $shop->id,
                'status' => PurchaseStatus::Posted,
                'posted_at' => $postedPurchaseAt->copy()->utc(),
                'subtotal' => '1000.00',
                'tax_total' => '0.00',
                'discount_total' => '0.00',
                'grand_total' => '1000.00',
            ]);

            $plan = Plan::factory()->create(['name' => 'Pilot Plan', 'slug' => 'pilot-plan']);
            Subscription::factory()->create([
                'shop_id' => $shop->id,
                'plan_id' => $plan->id,
                'status' => 'active',
                'starts_at' => $nowLocal->copy()->subDays(10)->utc(),
                'ends_at' => $nowLocal->copy()->addDays(20)->utc(),
            ]);
        });

        TenantContext::runAs($otherShop, function () use ($otherShop): void {
            Product::factory()->count(4)->create(['shop_id' => $otherShop->id]);
            Customer::factory()->count(3)->create(['shop_id' => $otherShop->id]);
            Sale::factory()->count(9)->create(['shop_id' => $otherShop->id]);
        });

        $importDir = storage_path('app/imports/'.$shop->id);
        $importFile = $importDir.'/'.uniqid('insights-', true).'.csv';
        $existingImportBytes = $this->importBytesFor($shop);
        File::ensureDirectoryExists($importDir);
        File::put($importFile, '1234567890');

        try {
            $insights = app(PlatformShopInsights::class)->for($shop);

            $this->assertSame(2, $insights['users_count']);
            $this->assertSame(2, $insights['products_count']);
            $this->assertSame(1, $insights['customers_count']);
            $this->assertSame(3, $insights['sales_count']);
            $this->assertSame(2, $insights['sales_last_30_days']);
            $this->assertTrue($insights['last_sale_at']->equalTo($lastSaleAt));
            $this->assertTrue($insights['last_posted_purchase_at']->equalTo($postedPurchaseAt));
            $this->assertSame($existingImportBytes + 10, $insights['import_bytes']);
            $this->assertTrue($insights['auto_reminders_enabled']);
            $this->assertSame('Pilot Plan', $insights['subscription']['plan']);
            $this->assertSame('active', $insights['subscription']['status']);

            $this->actingAs($admin)
                ->get(route('platform.shops.show', $shop))
                ->assertOk()
                ->assertSee('Insights Shop')
                ->assertSee('insights-shop', false)
                ->assertSee('Insights Owner')
                ->assertSee('owner-insights@example.test')
                ->assertSee('Pilot Plan');
        } finally {
            File::delete($importFile);
        }
    }

    public function test_shop_with_no_data_renders_with_zero_insights(): void
    {
        $admin = $this->admin();
        $shop = Shop::factory()->create(['name' => 'Empty Shop']);

        $insights = app(PlatformShopInsights::class)->for($shop);

        $this->assertSame(0, $insights['users_count']);
        $this->assertSame(0, $insights['products_count']);
        $this->assertSame(0, $insights['customers_count']);
        $this->assertSame(0, $insights['sales_count']);
        $this->assertSame(0, $insights['sales_last_30_days']);
        $this->assertNull($insights['last_sale_at']);
        $this->assertNull($insights['last_posted_purchase_at']);
        $this->assertSame($this->importBytesFor($shop), $insights['import_bytes']);
        $this->assertFalse($insights['auto_reminders_enabled']);
        $this->assertNull($insights['subscription']);

        $this->actingAs($admin)
            ->get(route('platform.shops.show', $shop))
            ->assertOk()
            ->assertSee(__('platform.empty_history'));
    }

    public function test_insight_query_count_is_flat_as_rows_grow(): void
    {
        $shop = Shop::factory()->create();
        $otherShop = Shop::factory()->create();

        $insights = app(PlatformShopInsights::class);
        $queryCount = 0;
        DB::listen(static function () use (&$queryCount): void {
            $queryCount++;
        });

        $freshShop = Shop::query()->findOrFail($shop->id);
        $queryCount = 0;
        $insights->for($freshShop);
        $baseline = $queryCount;

        $this->assertLessThanOrEqual(12, $baseline, 'Insights must use a bounded number of aggregate queries.');

        foreach ([$shop, $otherShop] as $targetShop) {
            TenantContext::runAs($targetShop, function () use ($targetShop): void {
                Product::factory()->count(8)->create(['shop_id' => $targetShop->id]);
                Customer::factory()->count(8)->create(['shop_id' => $targetShop->id]);
                Sale::factory()->count(8)->create(['shop_id' => $targetShop->id]);
            });
        }

        $freshShop = Shop::query()->findOrFail($shop->id);
        $queryCount = 0;
        $insights->for($freshShop);

        $this->assertSame($baseline, $queryCount, 'Insights query count must not grow with tenant rows.');
    }

    public function test_platform_pages_never_render_tenant_pii(): void
    {
        $admin = $this->admin();
        $shop = Shop::factory()->create();

        TenantContext::runAs($shop, function () use ($shop): void {
            Customer::factory()->create([
                'shop_id' => $shop->id,
                'name' => 'Secret Customer',
                'phone' => '9822222222',
            ]);
            Product::factory()->create([
                'shop_id' => $shop->id,
                'name' => 'Secret Product',
            ]);
            Sale::factory()->create([
                'shop_id' => $shop->id,
                'grand_total' => '1234.56',
            ]);
        });

        $this->tenantAudit($shop, 'updated', ['phone' => '9822222222']);

        $this->actingAs($admin);
        $detail = (string) $this->get(route('platform.shops.show', $shop))->getContent();
        $audit = (string) $this->get(route('platform.audit.index'))->getContent();

        foreach (['Secret Customer', '9822222222', 'Secret Product', '1234.56'] as $secret) {
            $this->assertStringNotContainsString($secret, $detail);
            $this->assertStringNotContainsString($secret, $audit);
        }
    }

    public function test_audit_viewer_hard_filters_events_and_all_filters_work(): void
    {
        $adminA = $this->admin('audit-a@example.test');
        $adminB = $this->admin('audit-b@example.test');
        $shopA = Shop::factory()->create(['name' => 'Audit Alpha']);
        $shopB = Shop::factory()->create(['name' => 'Audit Beta']);

        $this->platformAudit(
            $adminA,
            $shopA,
            PlatformAuditEvents::SHOP_SUSPENDED,
            'Policy violation alpha',
            Carbon::parse('2026-11-10 10:00:00', 'Asia/Kathmandu')->utc()
        );
        $this->platformAudit(
            $adminB,
            $shopB,
            PlatformAuditEvents::SHOP_REACTIVATED,
            null,
            Carbon::parse('2026-11-15 10:00:00', 'Asia/Kathmandu')->utc()
        );
        $this->tenantAudit($shopA, 'updated', ['phone' => '9800000099']);

        $this->actingAs($adminA);

        $all = $this->get(route('platform.audit.index'));
        $all->assertOk()
            ->assertSee(PlatformAuditEvents::SHOP_SUSPENDED)
            ->assertSee(PlatformAuditEvents::SHOP_REACTIVATED)
            ->assertSee('Policy violation alpha')
            ->assertSee('audit-a@example.test')
            ->assertSee('audit-b@example.test')
            ->assertSee('Audit Alpha')
            ->assertSee('Audit Beta')
            ->assertDontSee('updated')
            ->assertDontSee('9800000099');

        $this->get(route('platform.audit.index', ['admin' => $adminA->id]))
            ->assertOk()
            ->assertSee('<td>Policy violation alpha</td>', false)
            ->assertDontSee('<td>audit-b@example.test</td>', false)
            ->assertDontSee('<td>Audit Beta</td>', false);

        $this->get(route('platform.audit.index', ['shop' => $shopB->id]))
            ->assertOk()
            ->assertSee('<td>Audit Beta</td>', false)
            ->assertSee('<td>audit-b@example.test</td>', false)
            ->assertDontSee('<td>Policy violation alpha</td>', false)
            ->assertDontSee('<td>audit-a@example.test</td>', false);

        $this->get(route('platform.audit.index', ['event' => PlatformAuditEvents::SHOP_SUSPENDED]))
            ->assertOk()
            ->assertSee('<td>Policy violation alpha</td>', false)
            ->assertSee('<td>Audit Alpha</td>', false)
            ->assertDontSee('<td>Audit Beta</td>', false)
            ->assertDontSee('<td>audit-b@example.test</td>', false);

        $this->get(route('platform.audit.index', ['from' => '2026-11-15', 'to' => '2026-11-15']))
            ->assertOk()
            ->assertSee('<td>Audit Beta</td>', false)
            ->assertSee('<td>audit-b@example.test</td>', false)
            ->assertDontSee('<td>Policy violation alpha</td>', false)
            ->assertDontSee('<td>Audit Alpha</td>', false);

        $this->get(route('platform.audit.index', ['from' => '2026-13-99', 'to' => 'not-a-date']))
            ->assertOk()
            ->assertSee('<td>Policy violation alpha</td>', false)
            ->assertSee('<td>Audit Beta</td>', false);
    }

    public function test_audit_viewer_is_paginated_newest_first(): void
    {
        $admin = $this->admin();
        $shop = Shop::factory()->create();
        $now = Carbon::parse('2026-11-17 12:00:00', 'UTC');

        for ($i = 0; $i < 55; $i++) {
            $this->platformAudit(
                $admin,
                $shop,
                PlatformAuditEvents::SHOP_VIEWED,
                null,
                $now->copy()->addSeconds($i)
            );
        }

        $response = $this->actingAs($admin)->get(route('platform.audit.index'));
        $response->assertOk();

        $content = (string) $response->getContent();
        $this->assertSame(50, substr_count($content, '<td>'.PlatformAuditEvents::SHOP_VIEWED.'</td>'));
        $response->assertSee('page=2', false);
    }

    public function test_view_audit_is_deduplicated_and_history_shows_only_suspend_and_reactivate(): void
    {
        $admin = $this->admin('history-admin@example.test');
        $shop = Shop::factory()->create(['name' => 'History Shop']);

        $this->actingAs($admin);
        $this->get(route('platform.shops.show', $shop))->assertOk();
        $this->get(route('platform.shops.show', $shop))->assertOk();

        $this->assertSame(1, AuditLog::where('event', PlatformAuditEvents::SHOP_VIEWED)->count());

        $this->travel(61)->minutes();
        $this->get(route('platform.shops.show', $shop))->assertOk();
        $this->assertSame(2, AuditLog::where('event', PlatformAuditEvents::SHOP_VIEWED)->count());

        app(SuspendShop::class)->execute($shop, $admin, 'Late payment');
        $suspended = $this->get(route('platform.shops.show', $shop));
        $suspended->assertOk()
            ->assertSee('Late payment')
            ->assertSee('history-admin@example.test')
            ->assertSee(route('platform.shops.reactivate', $shop), false)
            ->assertSee(PlatformAuditEvents::SHOP_SUSPENDED)
            ->assertDontSee(PlatformAuditEvents::SHOP_VIEWED);

        $this->travel(1)->minute();
        app(ReactivateShop::class)->execute($shop->fresh(), $admin);

        $this->get(route('platform.shops.show', $shop))
            ->assertOk()
            ->assertSee(route('platform.shops.suspend', $shop), false)
            ->assertSeeInOrder([
                PlatformAuditEvents::SHOP_SUSPENDED,
                PlatformAuditEvents::SHOP_REACTIVATED,
            ], false)
            ->assertDontSee(PlatformAuditEvents::SHOP_VIEWED);
    }

    public function test_platform_pages_render_in_nepali_without_raw_translation_keys(): void
    {
        $admin = $this->admin();
        $admin->update(['locale' => 'ne']);
        $shop = Shop::factory()->create(['name' => 'नेपाली पसल']);

        $this->platformAudit(
            $admin,
            $shop,
            PlatformAuditEvents::SHOP_VIEWED,
            null,
            now()
        );

        $this->actingAs($admin);

        $detail = $this->visibleText((string) $this->get(route('platform.shops.show', $shop))->getContent());
        $audit = $this->visibleText((string) $this->get(route('platform.audit.index'))->getContent());

        foreach ([
            'platform.shop_detail',
            'platform.back_to_shops',
            'platform.audit',
            'platform.suspension_history',
            'platform.all_events',
            'platform.empty_audit',
            'common.platform_audit',
            'common.platform_shops',
        ] as $rawKey) {
            $this->assertStringNotContainsString($rawKey, $detail);
            $this->assertStringNotContainsString($rawKey, $audit);
        }

        $this->assertStringContainsString(__('platform.shop_detail', [], 'ne'), $detail);
        $this->assertStringContainsString(__('platform.audit', [], 'ne'), $audit);
    }

    private function importBytesFor(Shop $shop): int
    {
        $dir = storage_path('app/imports/'.$shop->id);
        if (! is_dir($dir)) {
            return 0;
        }

        $bytes = 0;
        foreach (File::allFiles($dir) as $file) {
            $bytes += $file->getSize();
        }

        return $bytes;
    }

    private function visibleText(string $html): string
    {
        $html = preg_replace('/<!--.*?-->/s', ' ', $html) ?? $html;
        $html = preg_replace('/<script\b[^>]*>.*?<\/script\s*>/is', ' ', $html) ?? $html;
        $html = preg_replace('/<style\b[^>]*>.*?<\/style\s*>/is', ' ', $html) ?? $html;

        return trim(html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
    }
}
