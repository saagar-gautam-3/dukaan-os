<?php

declare(strict_types=1);

namespace Tests\Feature\Pos;

use App\Models\HeldSale;
use App\Models\Shop;
use App\Models\User;
use App\Services\RbacService;
use App\Tenancy\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class HeldSalesPruneTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        TenantContext::reset();
        parent::tearDown();
    }

    private function createShop(): Shop
    {
        $shop = Shop::factory()->create();
        TenantContext::setShop($shop);
        app(RbacService::class)->createDefaultRolesForShop($shop);

        return $shop;
    }

    public function test_prune_deletes_only_old_rows(): void
    {
        $shop = $this->createShop();

        $old = HeldSale::factory()->create([
            'shop_id' => $shop->id,
            'user_id' => User::factory()->create()->id,
            'created_at' => now()->subDays(10),
        ]);
        $new = HeldSale::factory()->create([
            'shop_id' => $shop->id,
            'user_id' => User::factory()->create()->id,
            'created_at' => now()->subDay(),
        ]);

        $this->artisan('held-sales:prune', ['--days' => 3])->assertExitCode(0);

        $this->assertNull(HeldSale::find($old->id));
        $this->assertNotNull(HeldSale::find($new->id));
    }

    public function test_prune_across_shops(): void
    {
        $shopA = $this->createShop();
        $shopB = Shop::factory()->create();
        TenantContext::setShop($shopB);
        app(RbacService::class)->createDefaultRolesForShop($shopB);

        $oldB = HeldSale::factory()->create([
            'shop_id' => $shopB->id,
            'user_id' => User::factory()->create()->id,
            'created_at' => now()->subDays(10),
        ]);

        TenantContext::setShop($shopA);
        $this->artisan('held-sales:prune', ['--days' => 3])->assertExitCode(0);

        $this->assertNull(HeldSale::find($oldB->id));
    }

    public function test_prune_idempotent(): void
    {
        $shop = $this->createShop();
        HeldSale::factory()->create([
            'shop_id' => $shop->id,
            'user_id' => User::factory()->create()->id,
            'created_at' => now()->subDays(10),
        ]);

        $this->artisan('held-sales:prune', ['--days' => 3])->assertExitCode(0);
        $this->artisan('held-sales:prune', ['--days' => 3])->assertExitCode(0);
        $this->assertSame(0, HeldSale::count());
    }

    public function test_schedule_lists_prune_command(): void
    {
        $this->artisan('schedule:list')->expectsOutputToContain('held-sales:prune');
    }
}
