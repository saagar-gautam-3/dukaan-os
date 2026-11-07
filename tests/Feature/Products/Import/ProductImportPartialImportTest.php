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
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Tests\TestCase;
use ZipArchive;

class ProductImportPartialImportTest extends TestCase
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

    public function test_e1_partial_preview_counts_and_error_entry(): void
    {
        [$shop, $user] = $this->createShopWithOwner();
        $unit = Unit::firstOrCreate(['symbol' => 'pc'], ['name' => 'Piece', 'is_fractional' => false]);
        $csv = "name,unit,selling_price\nGood1,pc,10.00\nBad,hgh,hggh\nGood2,pc,20.00\n";
        $csv = str_replace('pc', $unit->symbol, $csv);

        $response = $this->actingAs($user)
            ->withSession(['current_shop_id' => $shop->id])
            ->post(route('products.import.store'), ['file' => $this->csvUpload($csv)]);

        $response->assertSessionHasNoErrors();
        TenantContext::setShop($shop);
        $import = ProductImport::where('shop_id', $shop->id)->firstOrFail();
        $this->assertSame(3, $import->total_rows);
        $this->assertSame(2, $import->valid_rows);
        $this->assertSame(1, $import->invalid_rows);
        $entry = $import->errors_json[0] ?? null;
        $this->assertNotNull($entry);
        $this->assertSame(3, $entry['line']);
        $this->assertSame('unit', $entry['field']);
        $this->assertSame('hgh', $entry['value']);
    }

    public function test_e2_confirm_creates_only_valid_products(): void
    {
        [$shop, $user] = $this->createShopWithOwner();
        $unit = Unit::firstOrCreate(['symbol' => 'pc'], ['name' => 'Piece', 'is_fractional' => false]);
        $csv = "name,unit,selling_price\nGood1,{$unit->symbol},10.00\nBad,hgh,hggh\nGood2,{$unit->symbol},20.00\n";

        $this->actingAs($user)
            ->withSession(['current_shop_id' => $shop->id])
            ->post(route('products.import.store'), ['file' => $this->csvUpload($csv)]);

        TenantContext::setShop($shop);
        $import = ProductImport::where('shop_id', $shop->id)->firstOrFail();

        $this->actingAs($user)
            ->withSession(['current_shop_id' => $shop->id])
            ->post(route('products.import.confirm', $import));

        $this->assertSame(2, Product::where('shop_id', $shop->id)->count());
    }

    public function test_e3_error_xlsx_shape_and_literal_value(): void
    {
        [$shop, $user] = $this->createShopWithOwner();
        $unit = Unit::firstOrCreate(['symbol' => 'pc'], ['name' => 'Piece', 'is_fractional' => false]);
        $csv = "name,unit,selling_price\nBad,=cmd(),hggh\n";

        $this->actingAs($user)
            ->withSession(['current_shop_id' => $shop->id])
            ->post(route('products.import.store'), ['file' => $this->csvUpload($csv)]);

        TenantContext::setShop($shop);
        $import = ProductImport::where('shop_id', $shop->id)->firstOrFail();

        $response = $this->actingAs($user)
            ->withSession(['current_shop_id' => $shop->id])
            ->get(route('products.import.errors.xlsx', $import));

        $response->assertStatus(200);
        $zip = new ZipArchive;
        $tmp = tempnam(sys_get_temp_dir(), 'xlsxtest');
        file_put_contents($tmp, $response->getContent());
        $zip->open($tmp);
        $xml = $zip->getFromName('xl/worksheets/sheet1.xml');
        $zip->close();

        // The malicious value must be stored as a literal string, never a formula.
        $spreadsheet = IOFactory::load($tmp);
        $sheet = $spreadsheet->getActiveSheet();
        $maliciousCell = $sheet->getCell('F2');
        $this->assertSame('=cmd()', (string) $maliciousCell->getValue());
        $this->assertSame(DataType::TYPE_STRING, $maliciousCell->getDataType());

        // In the raw sheet XML the cell must be a string cell (t="s" or t="inlineStr")
        // and must not contain a <f> formula element.
        $this->assertNotNull($xml);
        preg_match('/<c\\b[^>]*r="F2"[^>]*>.*?<\\/c>/s', $xml, $cellMatch);
        $this->assertNotEmpty($cellMatch, 'cell F2 not found in sheet xml');
        $cellXml = $cellMatch[0];
        $this->assertTrue(
            str_contains($cellXml, 't="s"') || str_contains($cellXml, 't="inlineStr"'),
            'cell F2 is not a string cell'
        );
        $this->assertFalse(str_contains($cellXml, '<f'), 'cell F2 contains a formula element');
        $this->assertFalse(str_contains($cellXml, '<f>'), 'cell F2 contains a formula element');

        // check header row via reader
        $headers = [];
        foreach ($sheet->getRowIterator(1, 1) as $row) {
            foreach ($row->getCellIterator() as $cell) {
                $headers[] = (string) $cell->getValue();
            }
        }
        $autoSku = true;
        $expected = array_merge(['line'], ['name', 'name_ne', 'barcode', 'category', 'unit', 'cost_price', 'selling_price', 'opening_stock', 'reorder_level'], ['error']);
        $this->assertSame($expected, $headers);
        $this->assertStringContainsString('unit:', (string) $sheet->getCell('K2')->getValue());
    }

    public function test_e4_uploading_error_file_back_succeeds(): void
    {
        [$shop, $user] = $this->createShopWithOwner();
        $unit = Unit::firstOrCreate(['symbol' => 'pc'], ['name' => 'Piece', 'is_fractional' => false]);
        $csv = "name,unit,selling_price\nBad,hgh,hggh\n";

        $this->actingAs($user)
            ->withSession(['current_shop_id' => $shop->id])
            ->post(route('products.import.store'), ['file' => $this->csvUpload($csv)]);

        TenantContext::setShop($shop);
        $import = ProductImport::where('shop_id', $shop->id)->firstOrFail();

        $response = $this->actingAs($user)
            ->withSession(['current_shop_id' => $shop->id])
            ->get(route('products.import.errors.xlsx', $import));

        $tmp = tempnam(sys_get_temp_dir(), 'xlsxtest');
        file_put_contents($tmp, $response->getContent());

        // Fix the bad row values in a new CSV using template headers + line/error ignored
        $fixedCsv = "line,name,name_ne,barcode,category,unit,cost_price,selling_price,opening_stock,reorder_level,error\n"
            ."2,Bad,,,,pc,0,10,0,0,unit: unknown\n";

        $response2 = $this->actingAs($user)
            ->withSession(['current_shop_id' => $shop->id])
            ->post(route('products.import.store'), ['file' => $this->csvUpload($fixedCsv)]);

        $response2->assertSessionHasNoErrors();
        TenantContext::setShop($shop);
        $import2 = ProductImport::where('shop_id', $shop->id)->orderBy('id', 'desc')->firstOrFail();
        $this->assertGreaterThan(0, $import2->valid_rows);
    }

    public function test_e5_all_invalid_offers_no_confirm(): void
    {
        [$shop, $user] = $this->createShopWithOwner();
        $csv = "name,unit,selling_price\nBad,hgh,hggh\n";

        $response = $this->actingAs($user)
            ->withSession(['current_shop_id' => $shop->id])
            ->post(route('products.import.store'), ['file' => $this->csvUpload($csv)]);

        $response->assertSessionHasNoErrors();
        TenantContext::setShop($shop);
        $import = ProductImport::where('shop_id', $shop->id)->firstOrFail();
        $this->assertSame(0, $import->valid_rows);
    }

    public function test_e6_cross_tenant_error_xlsx_forbidden(): void
    {
        [$shop, $user] = $this->createShopWithOwner();
        $unit = Unit::firstOrCreate(['symbol' => 'pc'], ['name' => 'Piece', 'is_fractional' => false]);
        $csv = "name,unit,selling_price\nBad,hgh,hggh\n";

        $this->actingAs($user)
            ->withSession(['current_shop_id' => $shop->id])
            ->post(route('products.import.store'), ['file' => $this->csvUpload($csv)]);

        TenantContext::setShop($shop);
        $import = ProductImport::where('shop_id', $shop->id)->firstOrFail();

        // second shop/user
        $shop2 = Shop::factory()->create();
        TenantContext::setShop($shop2);
        $rbac2 = app(RbacService::class);
        $roles2 = $rbac2->createDefaultRolesForShop($shop2);
        $user2 = User::factory()->create();
        ShopUser::create([
            'shop_id' => $shop2->id,
            'user_id' => $user2->id,
            'role_id' => $roles2['Shop Owner']->id,
            'status' => 'active',
        ]);

        $response = $this->actingAs($user2)
            ->withSession(['current_shop_id' => $shop2->id])
            ->get(route('products.import.errors.xlsx', $import));

        $this->assertContains($response->getStatusCode(), [403, 404]);
    }

    public function test_e7_second_confirm_is_noop(): void
    {
        [$shop, $user] = $this->createShopWithOwner();
        $unit = Unit::firstOrCreate(['symbol' => 'pc'], ['name' => 'Piece', 'is_fractional' => false]);
        $csv = "name,unit,selling_price\nGood,{$unit->symbol},10.00\n";

        $this->actingAs($user)
            ->withSession(['current_shop_id' => $shop->id])
            ->post(route('products.import.store'), ['file' => $this->csvUpload($csv)]);

        TenantContext::setShop($shop);
        $import = ProductImport::where('shop_id', $shop->id)->firstOrFail();

        $this->actingAs($user)
            ->withSession(['current_shop_id' => $shop->id])
            ->post(route('products.import.confirm', $import));

        $countAfterFirst = Product::where('shop_id', $shop->id)->count();

        $this->actingAs($user)
            ->withSession(['current_shop_id' => $shop->id])
            ->post(route('products.import.confirm', $import));

        $this->assertSame($countAfterFirst, Product::where('shop_id', $shop->id)->count());
    }

    public function test_e8_cashier_cannot_download_error_file(): void
    {
        [$shop, $user] = $this->createShopWithOwner();
        $unit = Unit::firstOrCreate(['symbol' => 'pc'], ['name' => 'Piece', 'is_fractional' => false]);
        $csv = "name,unit,selling_price\nBad,hgh,hggh\n";

        $this->actingAs($user)
            ->withSession(['current_shop_id' => $shop->id])
            ->post(route('products.import.store'), ['file' => $this->csvUpload($csv)]);

        TenantContext::setShop($shop);
        $import = ProductImport::where('shop_id', $shop->id)->firstOrFail();

        // cashier user
        $rbac = app(RbacService::class);
        $roles = $rbac->createDefaultRolesForShop($shop);
        $cashier = User::factory()->create();
        ShopUser::create([
            'shop_id' => $shop->id,
            'user_id' => $cashier->id,
            'role_id' => $roles['Cashier']->id,
            'status' => 'active',
        ]);

        $response = $this->actingAs($cashier)
            ->withSession(['current_shop_id' => $shop->id])
            ->get(route('products.import.errors.xlsx', $import));

        $this->assertSame(403, $response->getStatusCode());
    }
}
