<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Category;
use App\Models\Product;
use App\Models\Shop;
use App\Models\Unit;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Product>
 */
class ProductFactory extends Factory
{
    protected $model = Product::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $cost = fake()->randomFloat(2, 50, 500);
        $selling = round($cost * 1.25, 2);

        return [
            'shop_id' => Shop::factory(),
            'category_id' => Category::factory(),
            'unit_id' => Unit::factory(),
            'sku' => fake()->unique()->lexify('SKU-??????'),
            'barcode' => fake()->unique()->ean13(),
            'name' => fake()->words(3, true),
            'name_ne' => null,
            'cost_price' => $cost,
            'selling_price' => $selling,
            'track_stock' => true,
            'low_stock_threshold' => 5.000,
            'is_active' => true,
        ];
    }
}
