<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Shop;
use App\Tenancy\TenantContext;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ProductVariant>
 */
class ProductVariantFactory extends Factory
{
    protected $model = ProductVariant::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $shop = Shop::factory()->create();
        $product = TenantContext::runAs($shop, fn () => Product::factory()->create(['shop_id' => $shop->id]));

        return [
            'shop_id' => $shop->id,
            'product_id' => $product->id,
            'sku' => fake()->unique()->lexify('VAR-??????'),
            'barcode' => fake()->unique()->ean13(),
            'attributes' => ['size' => '1kg', 'flavor' => 'Regular'],
            'cost_price' => 100.00,
            'selling_price' => 125.00,
            'is_active' => true,
        ];
    }
}
