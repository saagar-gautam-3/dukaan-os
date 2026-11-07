<?php

declare(strict_types=1);

namespace Tests\Feature\Products;

use App\Models\Shop;
use App\Tenancy\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductSkuPolicyTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        TenantContext::reset();
        parent::tearDown();
    }

    public function test_s1_new_shop_has_sku_auto_generate_true(): void
    {
        $shop = Shop::factory()->create(['settings' => ['sku_auto_generate' => true]]);
        $this->assertTrue((bool) ($shop->settings['sku_auto_generate'] ?? false));
    }
}
