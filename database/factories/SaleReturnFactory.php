<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\ReturnStatus;
use App\Models\Sale;
use App\Models\SaleReturn;
use App\Models\Shop;
use App\Tenancy\TenantContext;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SaleReturn>
 */
class SaleReturnFactory extends Factory
{
    protected $model = SaleReturn::class;

    public function definition(): array
    {
        $shop = Shop::factory()->create();
        $sale = TenantContext::runAs($shop, fn () => Sale::factory()->create(['shop_id' => $shop->id]));

        return [
            'shop_id' => $shop->id,
            'sale_id' => $sale->id,
            'return_no' => 'RET-'.str_pad((string) $this->faker->unique()->numberBetween(1, 99999), 6, '0', STR_PAD_LEFT),
            'status' => ReturnStatus::Pending,
            'total' => '0.00',
            'reason' => null,
            'processed_at' => null,
            'processed_by' => null,
        ];
    }
}
