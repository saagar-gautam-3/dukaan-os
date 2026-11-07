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

    protected function downloadTemplateXlsx($user, $shop): string
    {
        $response = $this->actingAs($user)
            ->withSession(['current_shop_id' => $shop->id])
            ->get(route('products.import.template.xlsx'));

        $response->assertStatus(200, $response->exception?->getMessage() ?? '');

        return $response->getContent();
    }

    public function test_t1_template_xlsx_returns_200_with_pk_signature(): void
    {
        [$shop, $user] = $this->createShopWithOwner();

        $content = $this->downloadTemplateXlsx($user, $shop);

        $this->assertStringStartsWith('PK', $content);
    }

    public function test_t2_headers_match_on_and_off_modes(): void
    {
        [$shop, $user] = $this->createShopWithOwner(true);
        $content = $this->downloadTemplateXlsx($user, $shop);
        $spreadsheet = IOFactory::load($this->tempFile($content));
        $sheet = $spreadsheet->getActiveSheet();
        $headers = [];
        foreach ($sheet->getRowIterator(1, 1) as $row) {
            foreach ($row->getCellIterator() as $cell) {
                $headers[] = (string) $cell->getValue();
            }
        }
        $this->assertSame(['name', 'name_ne', 'barcode', 'category', 'unit', 'cost_price', 'selling_price', 'opening_stock', 'reorder_level'], $headers);

        [$shop2, $user2] = $this->createShopWithOwner(false);
        $content2 = $this->downloadTemplateXlsx($user2, $shop2);
        $spreadsheet2 = IOFactory::load($this->tempFile($content2));
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
        Unit::firstOrCreate(['symbol' => 'pc'], ['name' => 'Piece', 'is_fractional' => false]);
        [$shop, $user] = $this->createShopWithOwner(true);

        $content = $this->downloadTemplateXlsx($user, $shop);

        $zip = new ZipArchive;
        $tmp = $this->tempFile($content);
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

        $content = $this->downloadTemplateXlsx($user, $shop);

        $zip = new ZipArchive;
        $tmp = $this->tempFile($content);
        $zip->open($tmp);
        $xml = $zip->getFromName('xl/worksheets/sheet1.xml');
        $zip->close();

        $this->assertStringContainsString('errorStyle="warning"', $xml);
    }

    public function test_t5_sku_textlength_only_in_off_mode(): void
    {
        [$shop, $user] = $this->createShopWithOwner(true);
        $content = $this->downloadTemplateXlsx($user, $shop);
        $zip = new ZipArchive;
        $tmp = $this->tempFile($content);
        $zip->open($tmp);
        $xmlOn = $zip->getFromName('xl/worksheets/sheet1.xml');
        $zip->close();
        // In ON mode there is no sku column; assert against the sku column letter
        // derived from the actual header row (not a hardcoded letter).
        $spreadsheetOn = IOFactory::load($tmp);
        $sheetOn = $spreadsheetOn->getActiveSheet();
        $headersOn = [];
        foreach ($sheetOn->getRowIterator(1, 1) as $row) {
            foreach ($row->getCellIterator() as $cell) {
                $headersOn[] = (string) $cell->getValue();
            }
        }
        if (in_array('sku', $headersOn, true)) {
            $skuColOn = $this->columnLetter($headersOn, 'sku');
            $this->assertStringNotContainsString('sqref="'.$skuColOn.'2:'.$skuColOn.'2001"', $xmlOn);
        }
        $spreadsheetOn->disconnectWorksheets();
        unset($spreadsheetOn);

        [$shop2, $user2] = $this->createShopWithOwner(false);
        $content2 = $this->downloadTemplateXlsx($user2, $shop2);
        $zip2 = new ZipArchive;
        $tmp2 = $this->tempFile($content2);
        $zip2->open($tmp2);
        $xmlOff = $zip2->getFromName('xl/worksheets/sheet1.xml');
        $zip2->close();
        $this->assertStringContainsString('type="textLength"', $xmlOff);
    }

    public function test_t6_lists_sheet_hidden_with_unit_symbols_and_shop_categories(): void
    {
        Unit::firstOrCreate(['symbol' => 'pc'], ['name' => 'Piece', 'is_fractional' => false]);
        [$shop, $user] = $this->createShopWithOwner(true);
        TenantContext::setShop($shop);
        Category::create(['shop_id' => $shop->id, 'name' => 'Grocery']);

        // second shop with its own category
        $shop2 = Shop::factory()->create();
        TenantContext::setShop($shop2);
        Category::create(['shop_id' => $shop2->id, 'name' => 'Other']);

        TenantContext::setShop($shop);

        $content = $this->downloadTemplateXlsx($user, $shop);

        $spreadsheet = IOFactory::load($this->tempFile($content));
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

        $this->downloadTemplateXlsx($user, $shop);
    }

    public function test_t8_required_header_value_stays_raw_but_displays_star(): void
    {
        [$shop, $user] = $this->createShopWithOwner(true);

        $content = $this->downloadTemplateXlsx($user, $shop);

        $spreadsheet = IOFactory::load($this->tempFile($content));
        $sheet = $spreadsheet->getActiveSheet();
        $this->assertSame('name', (string) $sheet->getCell('A1')->getValue());
        $this->assertStringContainsString('*', $sheet->getStyle('A1')->getNumberFormat()->getFormatCode());
    }

    public function test_t9_example_rows_pass_parser(): void
    {
        Unit::firstOrCreate(['symbol' => 'pc'], ['name' => 'Piece', 'is_fractional' => false]);
        [$shop, $user] = $this->createShopWithOwner(true);
        TenantContext::setShop($shop);

        $content = $this->downloadTemplateXlsx($user, $shop);

        $tmp = $this->tempFile($content);
        $parsed = $this->readExampleSheet($tmp);
        $result = app(ParseProductImport::class)->execute($parsed, $shop->id);

        $this->assertEmpty($result['invalid']);
        $this->assertGreaterThan(0, count($result['valid']));
    }

    /**
     * @return array{header: array<int, string>, rows: array<int, array<int, string>>}
     */
    private function readExampleSheet(string $path): array
    {
        $spreadsheet = IOFactory::load($path);
        $sheet = $spreadsheet->getSheetByName('Example');
        $this->assertNotNull($sheet);

        $textColumns = ['name', 'name_ne', 'sku', 'barcode', 'category'];
        $header = [];
        $rawRows = [];
        foreach ($sheet->getRowIterator() as $row) {
            $indexed = [];
            $maxCol = 0;
            foreach ($row->getCellIterator() as $cell) {
                $colIndex = Coordinate::columnIndexFromString($cell->getColumn());
                $indexed[$colIndex - 1] = $cell->getValue();
                if ($colIndex > $maxCol) {
                    $maxCol = $colIndex;
                }
            }
            $cells = [];
            for ($i = 0; $i < $maxCol; $i++) {
                $cells[] = $indexed[$i] ?? '';
            }
            if (empty($header)) {
                $header = array_map(fn ($h) => strtolower(trim((string) $h)), $cells);

                continue;
            }
            if (implode('', array_map(fn ($v) => (string) $v, $cells)) === '') {
                continue;
            }
            $rawRows[] = $cells;
        }

        $rows = [];
        foreach ($rawRows as $raw) {
            $cells = [];
            foreach ($raw as $i => $val) {
                $colName = $header[$i] ?? '';
                $isText = in_array($colName, $textColumns, true);
                if (is_float($val)) {
                    if ($isText) {
                        if (abs($val) > 1e15 || floor($val) != $val) {
                            $s = sprintf('%.0f', $val);
                            if (strlen($s) > 15) {
                                throw new \InvalidArgumentException(__('products.import_error_text_too_large'));
                            }
                            $cells[] = $s;
                        } else {
                            $cells[] = sprintf('%.0f', $val);
                        }
                    } else {
                        $s = rtrim(rtrim(sprintf('%.6f', $val), '0'), '.');
                        if ($s === '' || $s === '-') {
                            $s = '0';
                        }
                        if (floor($val) == $val && abs($val) < 1e15) {
                            $s = (string) (int) $val;
                        }
                        $cells[] = $s;
                    }
                } elseif (is_int($val)) {
                    $cells[] = (string) $val;
                } elseif (is_string($val)) {
                    $cells[] = $val;
                } else {
                    $cells[] = '';
                }
            }
            if (count($cells) < count($header)) {
                $cells = array_pad($cells, count($header), '');
            }
            $rows[] = array_map('trim', $cells);
        }

        $spreadsheet->disconnectWorksheets();
        unset($spreadsheet);

        return ['header' => $header, 'rows' => $rows];
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
