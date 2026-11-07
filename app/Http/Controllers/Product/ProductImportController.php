<?php

declare(strict_types=1);

namespace App\Http\Controllers\Product;

use App\Actions\Product\ConfirmProductImport;
use App\Actions\Product\ParseProductImport;
use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductImport;
use App\Models\Shop;
use App\Models\Unit;
use App\Support\ProductImport\CsvProductImportReader;
use App\Support\ProductImport\XlsxProductImportReader;
use App\Tenancy\TenantContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Cell\DataValidation;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Throwable;

class ProductImportController extends Controller
{
    public function create(): View
    {
        abort_unless(auth()->user()?->can('create', Product::class), 403);
        abort_unless(auth()->user()?->can('inventory.manage'), 403);

        return view('products.import');
    }

    public function store(Request $request, ParseProductImport $parse): RedirectResponse
    {
        abort_unless(auth()->user()?->can('create', Product::class), 403);
        abort_unless(auth()->user()?->can('inventory.manage'), 403);

        $request->validate([
            'file' => ['required', 'file', 'max:2048', 'mimes:csv,txt,xlsx'],
        ]);

        $file = $request->file('file');
        $shopId = TenantContext::getShopId();
        $userId = (int) auth()->id();

        $ext = strtolower($file->getClientOriginalExtension());
        if (! in_array($ext, ['csv', 'txt', 'xlsx'], true)) {
            return back()->with('error', __('products.import_error_encoding'));
        }

        $dir = storage_path("app/imports/{$shopId}");
        if (! is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        $storedName = (string) Str::uuid().'.'.$ext;
        $storedPath = $dir.'/'.$storedName;
        $file->move($dir, $storedName);

        $hash = hash_file('sha256', $storedPath);

        $reader = $ext === 'xlsx'
            ? new XlsxProductImportReader
            : new CsvProductImportReader;

        try {
            $parsed = $reader->read($storedPath);
            $result = $parse->execute($parsed, $shopId);
        } catch (Throwable $e) {
            @unlink($storedPath);

            if ($e->getMessage() === __('products.import_error_empty')) {
                return back()->withErrors(['file' => $e->getMessage()]);
            }

            return back()->with('error', $e->getMessage());
        }

        $import = ProductImport::create([
            'shop_id' => $shopId,
            'user_id' => $userId,
            'original_name' => $file->getClientOriginalName(),
            'stored_path' => 'imports/'.$shopId.'/'.$storedName,
            'file_hash' => $hash,
            'status' => 'pending',
            'total_rows' => $result['total'],
            'valid_rows' => count($result['valid']),
            'invalid_rows' => count($result['invalid']),
            'skipped_rows' => $result['skipped'],
            'errors_json' => $result['errors'] ?? [],
            'warnings_json' => $result['warnings'] ?? [],
            'valid_rows_json' => $result['valid'],
        ]);

        return redirect()->route('products.import.preview', $import);
    }

    public function preview(ProductImport $productImport): View
    {
        $this->authorize('view', $productImport);

        return view('products.import-preview', ['import' => $productImport]);
    }

    public function confirm(ProductImport $productImport, ConfirmProductImport $confirm): RedirectResponse
    {
        $this->authorize('update', $productImport);

        try {
            $confirm->execute($productImport, (int) auth()->id());
        } catch (Throwable $e) {
            report($e);

            return back()->with('error', __('common.error_generic'));
        }

        return redirect()->route('products.import.success', $productImport);
    }

    public function success(ProductImport $productImport): View
    {
        $this->authorize('view', $productImport);

        return view('products.import-success', ['import' => $productImport]);
    }

    public function errorsCsv(ProductImport $productImport)
    {
        $this->authorize('view', $productImport);

        $out = "\xEF\xBB\xBF";
        $f = fopen('php://temp', 'w');
        fputcsv($f, ['line', 'reason']);
        foreach ($productImport->errors_json ?? [] as $e) {
            $reason = (string) ($e['reason'] ?? '');
            if (preg_match('/^[=+\-@]/', $reason)) {
                $reason = "'".$reason;
            }
            fputcsv($f, [$e['line'] ?? '', $reason]);
        }
        rewind($f);
        $out .= stream_get_contents($f);
        fclose($f);

        return response($out, 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="import-errors.csv"',
        ]);
    }

    public function errorsXlsx(ProductImport $productImport)
    {
        $this->authorize('view', $productImport);

        $shop = Shop::findOrFail($productImport->shop_id);
        $autoSku = (bool) ($shop->settings['sku_auto_generate'] ?? true);

        $headers = ['name', 'name_ne'];
        if (! $autoSku) {
            $headers[] = 'sku';
        }
        $headers = array_merge($headers, ['barcode', 'category', 'unit', 'cost_price', 'selling_price', 'opening_stock', 'reorder_level']);

        $allHeaders = array_merge(['line'], $headers, ['error']);

        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Errors');

        foreach ($allHeaders as $i => $h) {
            $col = Coordinate::stringFromColumnIndex($i + 1);
            $sheet->setCellValueExplicit($col.'1', $h, DataType::TYPE_STRING);
            $sheet->getStyle($col.'1')->getFont()->setBold(true);
        }

        $grouped = [];
        foreach ($productImport->errors_json ?? [] as $e) {
            $line = (int) ($e['line'] ?? 0);
            if (! isset($grouped[$line])) {
                $grouped[$line] = ['errors' => [], 'row' => $e['row'] ?? []];
            }
            $grouped[$line]['errors'][] = trim(($e['field'] ?? '').': '.($e['message'] ?? ''), ': ');
        }

        $r = 2;
        foreach ($grouped as $line => $data) {
            $sheet->setCellValueExplicit('A'.$r, (string) $line, DataType::TYPE_STRING);
            $row = $data['row'] ?? [];
            foreach ($headers as $i => $h) {
                $col = Coordinate::stringFromColumnIndex($i + 2);
                $sheet->setCellValueExplicit($col.$r, (string) ($row[$h] ?? ''), DataType::TYPE_STRING);
            }
            $errorCol = Coordinate::stringFromColumnIndex(count($allHeaders));
            $sheet->setCellValueExplicit($errorCol.$r, implode('; ', $data['errors']), DataType::TYPE_STRING);
            $sheet->getStyle($errorCol.$r)->getFont()->getColor()->setARGB('FFFF0000');
            $r++;
        }

        foreach ($allHeaders as $i => $h) {
            $col = Coordinate::stringFromColumnIndex($i + 1);
            $sheet->getColumnDimension($col)->setWidth($h === 'error' ? 60 : 16);
        }
        if ($r > 2) {
            $errorCol = Coordinate::stringFromColumnIndex(count($allHeaders));
            $sheet->getStyle($errorCol.'2:'.$errorCol.($r - 1))->getAlignment()->setWrapText(true);
        }

        $tmp = tempnam(sys_get_temp_dir(), 'xlsx');
        try {
            $writer = new Xlsx($spreadsheet);
            $writer->save($tmp);
            $content = file_get_contents($tmp) ?: '';
        } finally {
            @unlink($tmp);
            $spreadsheet->disconnectWorksheets();
            unset($spreadsheet);
        }

        return response($content, 200, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Content-Disposition' => 'attachment; filename="import-errors.xlsx"',
        ]);
    }

    public function templateXlsx()
    {
        abort_unless(auth()->user()?->can('create', Product::class), 403);
        abort_unless(auth()->user()?->can('inventory.manage'), 403);

        $shopId = TenantContext::getShopId();
        $shop = Shop::findOrFail($shopId);
        $autoSku = (bool) ($shop->settings['sku_auto_generate'] ?? true);

        $spreadsheet = new Spreadsheet;

        $headers = ['name', 'name_ne'];
        if (! $autoSku) {
            $headers[] = 'sku';
        }
        $headers = array_merge($headers, ['barcode', 'category', 'unit', 'cost_price', 'selling_price', 'opening_stock', 'reorder_level']);

        $required = ['name', 'unit', 'selling_price'];
        if (! $autoSku) {
            $required[] = 'sku';
        }

        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Products');
        foreach ($headers as $i => $h) {
            $col = Coordinate::stringFromColumnIndex($i + 1);
            $sheet->setCellValue($col.'1', $h);
            if (in_array($h, $required, true)) {
                $sheet->getStyle($col.'1')->getFont()->setBold(true)->getColor()->setARGB('FFC00000');
                $sheet->getStyle($col.'1')->getFill()->setFillType(Fill::FILL_SOLID)
                    ->getStartColor()->setARGB('FFDDEBF7');
                $sheet->getStyle($col.'1')->getNumberFormat()->setFormatCode('@ " *"');
            }
        }
        foreach (['barcode', 'sku'] as $h) {
            $idx = array_search($h, $headers, true);
            if ($idx !== false) {
                $col = Coordinate::stringFromColumnIndex($idx + 1);
                $sheet->getStyle($col.'2:'.$col.'2001')->getNumberFormat()->setFormatCode('@');
            }
        }

        // Column letters keyed by header name (sku mode aware).
        $col = [];
        foreach ($headers as $i => $h) {
            $col[$h] = Coordinate::stringFromColumnIndex($i + 1);
        }

        $units = Unit::all();
        $unitCount = $units->count();
        $categories = Category::where('shop_id', $shopId)->get();
        $categoryCount = $categories->count();

        // name: text length 1..255
        $v = new DataValidation;
        $v->setType(DataValidation::TYPE_TEXTLENGTH)
            ->setOperator(DataValidation::OPERATOR_BETWEEN)
            ->setFormula1('1')
            ->setFormula2('255')
            ->setAllowBlank(false)
            ->setShowErrorMessage(true)
            ->setErrorStyle(DataValidation::STYLE_STOP)
            ->setErrorTitle(__('products.import_validation_name'))
            ->setError(__('products.import_validation_name'));
        $sheet->setDataValidation($col['name'].'2:'.$col['name'].'2001', $v);

        // sku (OFF mode only): text length 1..100
        if (! $autoSku && isset($col['sku'])) {
            $v = new DataValidation;
            $v->setType(DataValidation::TYPE_TEXTLENGTH)
                ->setOperator(DataValidation::OPERATOR_BETWEEN)
                ->setFormula1('1')
                ->setFormula2('100')
                ->setAllowBlank(false)
                ->setShowErrorMessage(true)
                ->setErrorStyle(DataValidation::STYLE_STOP)
                ->setErrorTitle(__('products.import_validation_sku'))
                ->setError(__('products.import_validation_sku'));
            $sheet->setDataValidation($col['sku'].'2:'.$col['sku'].'2001', $v);
        }

        // unit: list (only when units exist)
        if ($unitCount > 0) {
            $v = new DataValidation;
            $v->setType(DataValidation::TYPE_LIST)
                ->setFormula1('=Lists!$A$2:$A$'.($unitCount + 1))
                ->setAllowBlank(false)
                ->setShowDropDown(true)
                ->setShowErrorMessage(true)
                ->setErrorStyle(DataValidation::STYLE_STOP)
                ->setErrorTitle(__('products.import_validation_unit'))
                ->setError(__('products.import_validation_unit'));
            $sheet->setDataValidation($col['unit'].'2:'.$col['unit'].'2001', $v);
        }

        // category: list (optional, warning when invalid)
        if ($categoryCount > 0) {
            $v = new DataValidation;
            $v->setType(DataValidation::TYPE_LIST)
                ->setFormula1('=Lists!$B$2:$B$'.($categoryCount + 1))
                ->setAllowBlank(true)
                ->setShowDropDown(true)
                ->setShowErrorMessage(true)
                ->setErrorStyle(DataValidation::STYLE_WARNING)
                ->setErrorTitle(__('products.import_validation_category'))
                ->setError(__('products.import_validation_category'));
            $sheet->setDataValidation($col['category'].'2:'.$col['category'].'2001', $v);
        }

        // numeric columns: decimal 0..999999999.99
        foreach (['cost_price', 'selling_price', 'opening_stock', 'reorder_level'] as $numHeader) {
            if (! isset($col[$numHeader])) {
                continue;
            }
            $v = new DataValidation;
            $v->setType(DataValidation::TYPE_DECIMAL)
                ->setOperator(DataValidation::OPERATOR_BETWEEN)
                ->setFormula1('0')
                ->setFormula2('999999999.99')
                ->setAllowBlank($numHeader !== 'selling_price')
                ->setShowErrorMessage(true)
                ->setErrorStyle(DataValidation::STYLE_STOP)
                ->setErrorTitle(__('products.import_validation_number'))
                ->setError(__('products.import_validation_number'));
            $sheet->setDataValidation($col[$numHeader].'2:'.$col[$numHeader].'2001', $v);
        }

        $sheet->freezePane('A2');

        $example = $spreadsheet->createSheet();
        $example->setTitle('Example');
        foreach ($headers as $i => $h) {
            $col = Coordinate::stringFromColumnIndex($i + 1);
            $example->setCellValue($col.'1', $h);
        }
        $units = Unit::all()->take(2)->values();
        $u1 = $units->get(0)?->symbol ?? $units->get(0)?->name ?? '';
        $u2 = $units->get(1)?->symbol ?? $units->get(1)?->name ?? $u1;
        $example->setCellValue('A2', 'Sample Product');
        $example->setCellValue('B2', 'नमुना वस्तु');
        $example->setCellValue('D2', 'Grocery');
        $example->setCellValue('E2', $u1);
        $example->setCellValue('F2', '10.00');
        $example->setCellValue('G2', '15.00');
        $example->setCellValue('H2', '5');
        $example->setCellValue('I2', '2');

        $instr = $spreadsheet->createSheet();
        $instr->setTitle('Instructions');
        $instr->setCellValue('A1', 'Required: name, unit, selling_price'.($autoSku ? '' : ', sku'));
        $instr->setCellValue('A2', 'Limits: 2 MB, 2000 rows.');
        $instr->setCellValue('A3', 'Save as .xlsx. Do not rename headers.');
        $instr->setCellValue('A4', 'Excel cannot enforce blank cells; server validates again.');
        $instr->setCellValue('A5', 'For barcodes use the Excel template; CSV opened in Excel can corrupt long numbers.');
        $instr->setCellValue('A6', '* = required column.');
        $r = 8;
        $instr->setCellValue('A'.$r, 'Units (accepted symbol = name):');
        $r++;
        foreach (Unit::all() as $u) {
            $instr->setCellValue('A'.$r, ($u->symbol ?? '').' = '.($u->name ?? ''));
            $r++;
        }

        $lists = $spreadsheet->createSheet();
        $lists->setTitle('Lists');
        $r = 2;
        foreach (Unit::all() as $u) {
            $lists->setCellValue('A'.$r, $u->symbol ?? $u->name);
            $r++;
        }
        $r = 2;
        foreach (Category::where('shop_id', $shopId)->get() as $c) {
            $lists->setCellValue('B'.$r, $c->name);
            $r++;
        }
        $lists->setSheetState(Worksheet::SHEETSTATE_HIDDEN);

        $spreadsheet->setActiveSheetIndex(0);

        $tmp = tempnam(sys_get_temp_dir(), 'xlsx');
        try {
            $writer = new Xlsx($spreadsheet);
            $writer->save($tmp);
            $content = file_get_contents($tmp) ?: '';
        } finally {
            @unlink($tmp);
            $spreadsheet->disconnectWorksheets();
            unset($spreadsheet);
        }

        return response($content, 200, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Content-Disposition' => 'attachment; filename="dukaan-products-template.xlsx"',
        ]);
    }

    public function template()
    {
        abort_unless(auth()->user()?->can('create', Product::class), 403);
        abort_unless(auth()->user()?->can('inventory.manage'), 403);

        $shopId = TenantContext::getShopId();
        $shop = Shop::findOrFail($shopId);
        $autoSku = (bool) ($shop->settings['sku_auto_generate'] ?? true);

        $units = Unit::all()->take(2)->values();
        $u1 = $units->get(0)?->symbol ?? $units->get(0)?->name ?? '';
        $u2 = $units->get(1)?->symbol ?? $units->get(1)?->name ?? $u1;

        $header = ['name', 'name_ne'];
        if (! $autoSku) {
            $header[] = 'sku';
        }
        $header = array_merge($header, ['barcode', 'category', 'unit', 'cost_price', 'selling_price', 'opening_stock', 'reorder_level']);

        $rows = [];
        $r1 = ['Sample Product', ''];
        if (! $autoSku) {
            $r1[] = 'SKU-001';
        }
        $r1 = array_merge($r1, ['', 'Grocery', $u1, '10.00', '15.00', '5', '2']);
        $rows[] = $r1;

        $r2 = ['नमुना वस्तु', 'नमुना'];
        if (! $autoSku) {
            $r2[] = 'SKU-002';
        }
        $r2 = array_merge($r2, ['1234567890', 'किराना', $u2, '20.50', '30.00', '0', '1']);
        $rows[] = $r2;

        $out = "\xEF\xBB\xBF";
        $f = fopen('php://temp', 'w');
        fputcsv($f, $header);
        foreach ($rows as $r) {
            fputcsv($f, $r);
        }
        rewind($f);
        $out .= stream_get_contents($f);
        fclose($f);

        return response($out, 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="products-import-template.csv"',
        ]);
    }
}
