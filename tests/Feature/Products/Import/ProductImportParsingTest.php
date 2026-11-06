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

class ProductImportParsingTest extends TestCase
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

        return [$shop, $user, $roles['Shop Owner']];
    }

    private function csvUpload(string $csv, string $name = 't.csv'): UploadedFile
    {
        return UploadedFile::fake()->createWithContent($name, $csv);
    }

    public function test_p1_valid_csv_with_bom_and_devanagari_previews(): void
    {
        [$shop, $user] = $this->createShopWithOwner();
        $unit = Unit::firstOrCreate(['symbol' => 'pc'], ['name' => 'Piece', 'is_fractional' => false]);
        $csv = "\xEF\xBB\xBFname,unit,selling_price,cost_price,opening_stock\n"
            ."Item,{$unit->symbol},10.00,5.00,१२०\n";

        $response = $this->actingAs($user)
            ->withSession(['current_shop_id' => $shop->id])
            ->post(route('products.import.store'), ['file' => $this->csvUpload($csv)]);

        $response->assertSessionHasNoErrors();
        $this->assertNotSame(403, $response->getStatusCode());
        $response->assertRedirect();
        $import = ProductImport::where('shop_id', $shop->id)->firstOrFail();
        $this->assertSame(1, $import->valid_rows);
        $this->assertSame(0, $import->invalid_rows);
        $this->assertSame(120, (int) ($import->valid_rows_json[0]['opening_stock'] ?? 0));
    }

    public function test_p10_preview_creates_no_products(): void
    {
        [$shop, $user] = $this->createShopWithOwner();
        $unit = Unit::factory()->create();
        $csv = "name,unit,selling_price\nItem,{$unit->symbol},10.00\n";

        $this->actingAs($user)
            ->withSession(['current_shop_id' => $shop->id])
            ->post(route('products.import.store'), ['file' => $this->csvUpload($csv)]);

        $this->assertSame(0, Product::where('shop_id', $shop->id)->count());
    }

    public function test_p3_empty_file_rejected(): void
    {
        [$shop, $user] = $this->createShopWithOwner();

        $response = $this->actingAs($user)
            ->withSession(['current_shop_id' => $shop->id])
            ->post(route('products.import.store'), ['file' => $this->csvUpload('')]);

        $response->assertSessionHasErrors('file');
        $this->assertSame(0, ProductImport::where('shop_id', $shop->id)->count());
    }

    public function test_p3_header_only_file_rejected(): void
    {
        [$shop, $user] = $this->createShopWithOwner();

        $response = $this->actingAs($user)
            ->withSession(['current_shop_id' => $shop->id])
            ->post(route('products.import.store'), ['file' => $this->csvUpload("name,unit,selling_price\n")]);

        $response->assertSessionHasErrors('file');
        $this->assertSame(0, ProductImport::where('shop_id', $shop->id)->count());
    }
}
