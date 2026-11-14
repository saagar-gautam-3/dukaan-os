<?php

declare(strict_types=1);

namespace Tests\Feature\Inventory;

use App\Models\Product;
use App\Models\Shop;
use App\Models\ShopUser;
use App\Models\StockBalance;
use App\Models\Unit;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\RbacService;
use App\Tenancy\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class StockAlertsThresholdTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        TenantContext::reset();
        parent::tearDown();
    }

    public function test_alerts_render_the_low_stock_threshold_in_both_locales(): void
    {
        $shop = Shop::factory()->create();
        TenantContext::setShop($shop);
        $roles = app(RbacService::class)->createDefaultRolesForShop($shop);

        $user = User::factory()->create(['locale' => 'en']);
        ShopUser::create([
            'shop_id' => $shop->id,
            'user_id' => $user->id,
            'role_id' => $roles['Inventory Staff']->id,
            'status' => 'active',
        ]);

        $warehouse = Warehouse::factory()->create(['shop_id' => $shop->id]);
        $unit = Unit::factory()->create(['is_fractional' => true]);
        $product = Product::factory()->create([
            'shop_id' => $shop->id,
            'unit_id' => $unit->id,
            'low_stock_threshold' => '5.500',
            'track_stock' => true,
        ]);

        StockBalance::factory()->create([
            'shop_id' => $shop->id,
            'warehouse_id' => $warehouse->id,
            'product_id' => $product->id,
            'quantity' => '2.000',
        ]);

        $user->update(['locale' => 'en']);
        $english = $this->actingAs($user)
            ->withSession(['current_shop_id' => $shop->id, 'locale' => 'en'])
            ->get(route('stock.alerts'));

        $english->assertOk();
        $this->assertStringContainsString('5.500', (string) $english->getContent());

        $user->update(['locale' => 'ne']);
        $nepali = $this->actingAs($user)
            ->withSession(['current_shop_id' => $shop->id, 'locale' => 'ne'])
            ->get(route('stock.alerts'));

        $nepali->assertOk();
        $this->assertStringContainsString('५.५००', (string) $nepali->getContent());
    }
}
