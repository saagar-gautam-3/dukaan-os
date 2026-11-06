<?php

declare(strict_types=1);

namespace Tests\Feature\Products\Import;

use App\Actions\Product\ParseProductImport;
use App\Models\Category;
use App\Models\Shop;
use App\Models\ShopUser;
use App\Models\Unit;
use App\Models\User;
use App\Services\RbacService;
use App\Support\ProductImport\XlsxProductImportReader;
use App\Tenancy\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use Tests\TestCase;
use ZipArchive;

class ProductImportXlsxTemplateTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        TenantContext::reset();
        parent::tearDown();
    }

    protected function createShopWithOwner(bool $autoSku = true): array
    {
        $shop = Shop::factory()->create(['settings' => ['sku_auto_generate' => $autoSku]]);
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

    public function test_t1_template_xlsx_returns_200_with_pk_signature(): void
    {
        [$shop, $user] = $this->createShopWithOwner();

        $response = $this->actingAs($user)
            ->withSession(['current_shop_id' => $shop->id])
            ->get(route('products.import.template.xlsx'));

        $response->assertStatus(200);
        $this->assertStringStartsWith('PK', $response->getContent());
    }

    public function test_t2_headers_match_on_and_off_modes(): void
    {
        [$shop, $user] = $this->createShopWithOwner(true);
        $response = $this->actingAs($user)
            ->withSession(['current_shop_id' => $shop->id])
            ->get(route('products.import.template.xlsx'));
        $spreadsheet = IOFactory::load($this->tempFile($response->getContent()));
        $sheet = $spreadsheet->getActiveSheet();
        $headers = [];
        foreach ($sheet->getRowIterator(1, 1) as $row) {
            foreach ($row->getCellIterator() as $cell) {
                $headers[] = (string) $cell->getValue();
            }
        }
        $this->assertSame(['name', 'name_ne', 'barcode', 'category', 'unit', 'cost_price', 'selling_price', 'opening_stock', 'reorder_level'], $headers);

        [$shop2, $user2] = $this->createShopWithOwner(false);
        $response2 = $this->actingAs($user2)
            ->withSession(['current_shop_id' => $shop2->id])
            ->get(route('products.import.template.xlsx'));
        $spreadsheet2 = IOFactory::load($this->tempFile($response2->getContent()));
        $sheet2 = $spreadsheet2->getActiveSheet();
        $headers2 = [];
        foreach ($sheet2->getRowIterator(1, 1) as $row) {
            foreach ($row->getCellIterator() as $cell) {
                $headers2[] = (string) $cell->getValue();
            }
        }
        $this->assertSame(['name', 'name_ne', 'sku', 'barcode', 'category', 'unit', 'cost_price', 'selling_price', 'opening_stock', 'reorder_level'], $headers2);
    }

    public function test_t3_raw_xml_has_unit_list_and_selling_price_decimal_validation(): void
    {
        Unit::factory()->create(['symbol' => 'pc']);
        [$shop, $user] = $this->createShopWithOwner(true);

        $response = $this->actingAs($user)
            ->withSession(['current_shop_id' => $shop->id])
            ->get(route('products.import.template.xlsx'));

        $zip = new ZipArchive;
        $tmp = $this->tempFile($response->getContent());
        $this->assertTrue($zip->open($tmp) === true);
        $xml = $zip->getFromName('xl/worksheets/sheet1.xml');
        $zip->close();

        $headers = ['name', 'name_ne', 'barcode', 'category', 'unit', 'cost_price', 'selling_price', 'opening_stock', 'reorder_level'];
        $unitCol = $this->columnLetter($headers, 'unit');
        $sellingCol = $this->columnLetter($headers, 'selling_price');

        $this->assertStringContainsString('sqref="'.$unitCol.'2:'.$unitCol.'2001"', $xml);
        $this->assertStringContainsString('type="list"', $xml);
        $this->assertStringNotContainsString('showDropDown="1"', $xml);
        $this->assertStringNotContainsString('showDropDown="true"', $xml);
        $this->assertStringContainsString('sqref="'.$sellingCol.'2:'.$sellingCol.'2001"', $xml);
        $this->assertStringContainsString('type="decimal"', $xml);
    }

    public function test_t4_category_validation_is_warning(): void
    {
        [$shop, $user] = $this->createShopWithOwner(true);
        TenantContext::setShop($shop);
        Category::create(['shop_id' => $shop->id, 'name' => 'Grocery']);

        $response = $this->actingAs($user)
            ->withSession(['current_shop_id' => $shop->id])
            ->get(route('products.import.template.xlsx'));

        $zip = new ZipArchive;
        $tmp = $this->tempFile($response->getContent());
        $zip->open($tmp);
        $xml = $zip->getFromName('xl/worksheets/sheet1.xml');
        $zip->close();

        $this->assertStringContainsString('errorStyle="warning"', $xml);
    }

    public function test_t5_sku_textlength_only_in_off_mode(): void
    {
        [$shop, $user] = $this->createShopWithOwner(true);
        $response = $this->actingAs($user)
            ->withSession(['current_shop_id' => $shop->id])
            ->get(route('products.import.template.xlsx'));
        $zip = new ZipArchive;
        $tmp = $this->tempFile($response->getContent());
        $zip->open($tmp);
        $xmlOn = $zip->getFromName('xl/worksheets/sheet1.xml');
        $zip->close();
        $this->assertStringNotContainsString('type="textLength"', $xmlOn);

        [$shop2, $user2] = $this->createShopWithOwner(false);
        $response2 = $this->actingAs($user2)
            ->withSession(['current_shop_id' => $shop2->id])
            ->get(route('products.import.template.xlsx'));
        $zip2 = new ZipArchive;
        $tmp2 = $this->tempFile($response2->getContent());
        $zip2->open($tmp2);
        $xmlOff = $zip2->getFromName('xl/worksheets/sheet1.xml');
        $zip2->close();
        $this->assertStringContainsString('type="textLength"', $xmlOff);
    }

    public function test_t6_lists_sheet_hidden_with_unit_symbols_and_shop_categories(): void
    {
        Unit::factory()->create(['symbol' => 'pc']);
        [$shop, $user] = $this->createShopWithOwner(true);
        TenantContext::setShop($shop);
        Category::create(['shop_id' => $shop->id, 'name' => 'Grocery']);

        // second shop with its own category
        $shop2 = Shop::factory()->create();
        TenantContext::setShop($shop2);
        Category::create(['shop_id' => $shop2->id, 'name' => 'Other']);

        TenantContext::setShop($shop);

        $response = $this->actingAs($user)
            ->withSession(['current_shop_id' => $shop->id])
            ->get(route('products.import.template.xlsx'));

        $spreadsheet = IOFactory::load($this->tempFile($response->getContent()));
        $lists = $spreadsheet->getSheetByName('Lists');
        $this->assertNotNull($lists);
        $this->assertSame(Worksheet::SHEETSTATE_HIDDEN, $lists->getSheetState());

        $symbols = [];
        $r = 2;
        while (true) {
            $v = $lists->getCell('A'.$r)->getValue();
            if ($v === null || $v === '') {
                break;
            }
            $symbols[] = (string) $v;
            $r++;
        }
        $this->assertContains('pc', $symbols);

        $cats = [];
        $r = 2;
        while (true) {
            $v = $lists->getCell('B'.$r)->getValue();
            if ($v === null || $v === '') {
                break;
            }
            $cats[] = (string) $v;
            $r++;
        }
        $this->assertContains('Grocery', $cats);
        $this->assertNotContains('Other', $cats);
    }

    public function test_t7_zero_categories_downloads_fine(): void
    {
        [$shop, $user] = $this->createShopWithOwner(true);

        $response = $this->actingAs($user)
            ->withSession(['current_shop_id' => $shop->id])
            ->get(route('products.import.template.xlsx'));

        $response->assertStatus(200);
    }

    public function test_t8_required_header_value_stays_raw_but_displays_star(): void
    {
        [$shop, $user] = $this->createShopWithOwner(true);

        $response = $this->actingAs($user)
            ->withSession(['current_shop_id' => $shop->id])
            ->get(route('products.import.template.xlsx'));

        $spreadsheet = IOFactory::load($this->tempFile($response->getContent()));
        $sheet = $spreadsheet->getActiveSheet();
        $this->assertSame('name', (string) $sheet->getCell('A1')->getValue());
        $this->assertStringContainsString('*', $sheet->getStyle('A1')->getNumberFormat()->getFormatCode());
    }

    public function test_t9_example_rows_pass_parser(): void
    {
        Unit::firstOrCreate(['symbol' => 'pc'], ['name' => 'Piece', 'is_fractional' => false]);
        [$shop, $user] = $this->createShopWithOwner(true);
        TenantContext::setShop($shop);

        $response = $this->actingAs($user)
            ->withSession(['current_shop_id' => $shop->id])
            ->get(route('products.import.template.xlsx'));

        $tmp = $this->tempFile($response->getContent());
        $reader = new XlsxProductImportReader;
        $parsed = $reader->read($tmp);
        $result = app(ParseProductImport::class)->execute($parsed, $shop->id);

        $this->assertEmpty($result['invalid']);
        $this->assertGreaterThan(0, count($result['valid']));
    }

    private function tempFile(string $content): string
    {
        $tmp = tempnam(sys_get_temp_dir(), 'xlsxtest');
        file_put_contents($tmp, $content);

        return $tmp;
    }

    private function columnLetter(array $headers, string $name): string
    {
        $idx = array_search($name, $headers, true);

        return Coordinate::stringFromColumnIndex($idx + 1);
    }
}
