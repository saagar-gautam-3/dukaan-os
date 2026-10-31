<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Category;
use App\Models\Product;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\SaleReturn;
use App\Models\SaleReturnItem;
use App\Models\Shop;
use App\Models\Unit;
use App\Tenancy\TenantContext;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SaleReturnItem>
 */
class SaleReturnItemFactory extends Factory
{
    protected $model = SaleReturnItem::class;

    public function definition(): array
    {
        $shop = Shop::factory()->create();
        $sale = TenantContext::runAs($shop, fn () => Sale::factory()->create(['shop_id' => $shop->id]));
        $saleReturn = TenantContext::runAs($shop, fn () => SaleReturn::factory()->create(['shop_id' => $shop->id, 'sale_id' => $sale->id]));

        $category = TenantContext::runAs($shop, fn () => Category::factory()->create(['shop_id' => $shop->id]));
        $unit = Unit::firstOrCreate(['symbol' => 'pc'], ['name' => 'Piece', 'is_fractional' => false]);
        $product = TenantContext::runAs($shop, fn () => Product::factory()->create([
            'shop_id' => $shop->id,
            'category_id' => $category->id,
            'unit_id' => $unit->id,
        ]));

        $saleItem = TenantContext::runAs($shop, function () use ($sale, $product) {
            return SaleItem::create([
                'sale_id' => $sale->id,
                'product_id' => $product->id,
                'quantity' => '1.000',
                'unit_price' => '10.00',
                'unit_cost' => '5.00',
                'discount' => '0.00',
                'tax' => '0.00',
                'line_total' => '10.00',
            ]);
        });

        return [
            'shop_id' => $shop->id,
            'sale_return_id' => $saleReturn->id,
            'sale_item_id' => $saleItem->id,
            'quantity' => '1.000',
            'unit_cost' => '5.00',
            'refund_amount' => '10.00',
        ];
    }
}
