<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Shop;
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
        return [
            'shop_id' => Shop::factory(),
            'product_id' => Product::factory(),
            'sku' => fake()->unique()->lexify('VAR-??????'),
            'barcode' => fake()->unique()->ean13(),
            'attributes' => ['size' => '1kg', 'flavor' => 'Regular'],
            'cost_price' => 100.00,
            'selling_price' => 125.00,
            'is_active' => true,
        ];
    }
}
