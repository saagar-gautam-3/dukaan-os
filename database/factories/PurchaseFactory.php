<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\PurchaseStatus;
use App\Models\Purchase;
use App\Models\Shop;
use App\Models\Supplier;
use App\Models\User;
use App\Models\Warehouse;
use App\Tenancy\TenantContext;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Purchase>
 */
class PurchaseFactory extends Factory
{
    protected $model = Purchase::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        if (TenantContext::has()) {
            $shop = TenantContext::shop();

            return [
                'shop_id' => $shop->id,
                'warehouse_id' => Warehouse::factory(),
                'supplier_id' => Supplier::factory(),
                'purchase_no' => fake()->unique()->lexify('PO-??????'),
                'status' => PurchaseStatus::Draft,
                'subtotal' => 1000.00,
                'tax_total' => 0.00,
                'discount_total' => 0.00,
                'grand_total' => 1000.00,
                'notes' => 'Stock replenishment order',
                'purchase_date' => now()->toDateString(),
                'posted_at' => null,
                'created_by' => User::factory(),
            ];
        }

        $shop = Shop::factory()->create();
        $warehouse = TenantContext::runAs($shop, fn () => Warehouse::factory()->create(['shop_id' => $shop->id]));
        $supplier = TenantContext::runAs($shop, fn () => Supplier::factory()->create(['shop_id' => $shop->id]));

        return [
            'shop_id' => $shop->id,
            'warehouse_id' => $warehouse->id,
            'supplier_id' => $supplier->id,
            'purchase_no' => fake()->unique()->lexify('PO-??????'),
            'status' => PurchaseStatus::Draft,
            'subtotal' => 1000.00,
            'tax_total' => 0.00,
            'discount_total' => 0.00,
            'grand_total' => 1000.00,
            'notes' => 'Stock replenishment order',
            'purchase_date' => now()->toDateString(),
            'posted_at' => null,
            'created_by' => User::factory(),
        ];
    }

    public function posted(): static
    {
        return $this->state(fn () => [
            'status' => PurchaseStatus::Posted,
            'posted_at' => now(),
        ]);
    }
}
