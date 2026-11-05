<?php

declare(strict_types=1);

namespace App\Http\Controllers\Product;

use App\Actions\Product\ConfirmProductImport;
use App\Actions\Product\ParseProductImport;
use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\ProductImport;
use App\Tenancy\TenantContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;
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
        $this->authorize('create', Product::class);

        $request->validate([
            'file' => ['required', 'file', 'max:2048', 'mimes:csv,txt'],
        ]);

        $file = $request->file('file');
        $shopId = TenantContext::getShopId();
        $userId = (int) auth()->id();

        $dir = storage_path("app/imports/{$shopId}");
        if (! is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        $storedName = (string) Str::uuid().'.csv';
        $storedPath = $dir.'/'.$storedName;
        $file->move($dir, $storedName);

        $hash = hash_file('sha256', $storedPath);

        try {
            $result = $parse->execute($storedPath, $shopId);
        } catch (Throwable $e) {
            @unlink($storedPath);

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
            'errors_json' => $result['invalid'],
            'warnings_json' => $result['warnings'] ?? [],
            'valid_rows_json' => $result['valid'],
        ]);

        return redirect()->route('products.import.preview', $import);
    }

    public function preview(ProductImport $productImport): View
    {
        abort_unless(auth()->user()?->can('create', Product::class), 403);

        return view('products.import-preview', ['import' => $productImport]);
    }

    public function confirm(ProductImport $productImport, ConfirmProductImport $confirm): RedirectResponse
    {
        abort_unless(auth()->user()?->can('create', Product::class), 403);

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
        return view('products.import-success', ['import' => $productImport]);
    }

    public function template()
    {
        $filename = 'products-import-template.csv';
        $header = ['name', 'sku', 'barcode', 'category', 'unit', 'cost_price', 'selling_price', 'opening_stock', 'reorder_level'];
        $rows = [
            ['Sample Product', 'SKU-001', '', 'Grocery', 'pcs', '10.00', '15.00', '5', '2'],
            ['नमुना वस्तु', 'SKU-002', '1234567890', 'किराना', 'kg', '20.50', '30.00', '0', '1'],
        ];
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
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
        ]);
    }
}
