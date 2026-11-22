<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\HeldSale;
use App\Models\Shop;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class HeldSaleFactory extends Factory
{
    protected $model = HeldSale::class;

    public function definition(): array
    {
        $shop = Shop::factory()->create();

        return [
            'shop_id' => $shop->id,
            'user_id' => User::factory(),
            'customer_id' => null,
            'label' => null,
            'cart' => [
                ['product_id' => 1, 'quantity' => '1.000'],
            ],
        ];
    }
}
