<?php

declare(strict_types=1);

namespace Tests\Feature\Products\Import;

use App\Models\Product;
use App\Models\ProductImport;
use App\Models\Shop;
use App\Models\ShopUser;
use App\Models\Unit;
use App\Models\User;
use App\Services\RbacService;
use App\Tenancy\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class ProductImportConfirmTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        TenantContext::reset();
        parent::tearDown();
    }

    protected function createShopWithOwner(): array
    {
        $shop = Shop::factory()->create();
        TenantContext::setShop($shop);
        $rbac = app(RbacService::class);
        $roles = $rbac->createDefaultRolesForShop($shop);
        $user = User::factory()->create();
        ShopUser::create([
            'shop_id' => $shop->id,
            'user_id' => $user->id,
            'role_id' => $roles['Shop Owner']->id,
            'status' => 'active',
        ]);

        return [$shop, $user];
    }

    public function test_c4_second_confirm_is_noop(): void
    {
        [$shop, $user] = $this->createShopWithOwner();
        $unit = Unit::firstOrCreate(['symbol' => 'pc'], ['name' => 'Piece', 'is_fractional' => false]);
        $csv = "name,unit,selling_price\nItem,{$unit->symbol},10.00\n";
        $file = UploadedFile::fake()->createWithContent('t.csv', $csv);

        // CSV relies on the factory Unit's symbol and one valid data row.
        $response = $this->actingAs($user)
            ->withSession(['current_shop_id' => $shop->id])
            ->post(route('products.import.store'), ['file' => $file]);

        $response->assertSessionHasNoErrors();
        $response->assertSessionMissing('error');
        $this->assertNotSame(403, $response->getStatusCode());
        $response->assertRedirect();

        TenantContext::setShop($shop);
        $import = ProductImport::where('shop_id', $shop->id)->firstOrFail();
        $response->assertRedirect(route('products.import.preview', $import));

        $this->actingAs($user)
            ->withSession(['current_shop_id' => $shop->id])
            ->post(route('products.import.confirm', $import));

        $countAfterFirst = Product::where('shop_id', $shop->id)->count();

        $this->actingAs($user)
            ->withSession(['current_shop_id' => $shop->id])
            ->post(route('products.import.confirm', $import));

        $this->assertSame($countAfterFirst, Product::where('shop_id', $shop->id)->count());
    }
}
