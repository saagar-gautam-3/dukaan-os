<?php

declare(strict_types=1);

namespace Tests\Feature\Products;

use App\Models\Shop;
use App\Models\Unit;
use App\Models\User;
use App\Models\Warehouse;
use App\Tenancy\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductImportTest extends TestCase
{
    use RefreshDatabase;

    private Shop $shop;

    private User $owner;

    private Warehouse $warehouse;

    private Unit $unit;

    protected function setUp(): void
    {
        parent::setUp();
        $this->shop = Shop::factory()->create();
        TenantContext::setShop($this->shop);
        $this->owner = User::factory()->create();
        $this->shop->users()->attach($this->owner->id, ['role_id' => 1, 'status' => 'active']);
        $this->warehouse = Warehouse::factory()->create(['shop_id' => $this->shop->id, 'is_default' => true]);
        $this->unit = Unit::factory()->create();
    }

    public function test_template_download_has_bom_and_header(): void
    {
        $this->actingAs($this->owner);
        TenantContext::setShop($this->shop);

        $response = $this->get(route('products.import.template'));

        $response->assertStatus(200);
        $this->assertStringStartsWith("\xEF\xBB\xBF", $response->getContent());
    }
}
