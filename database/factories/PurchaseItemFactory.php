<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Product;
use App\Models\Purchase;
use App\Models\PurchaseItem;
use App\Models\Shop;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PurchaseItem>
 */
class PurchaseItemFactory extends Factory
{
    protected $model = PurchaseItem::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'shop_id' => Shop::factory(),
            'purchase_id' => Purchase::factory(),
            'product_id' => Product::factory(),
            'quantity' => 10.000,
            'unit_cost' => 100.00,
            'discount' => 0.00,
            'line_total' => 1000.00,
        ];
    }
}
