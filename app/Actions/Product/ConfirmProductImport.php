<?php

declare(strict_types=1);

namespace App\Actions\Product;

use App\Actions\Inventory\ReceiveStock;
use App\Enums\MovementType;
use App\Models\Product;
use App\Models\ProductImport;
use App\Models\Warehouse;
use App\Support\AuditTrail;
use App\Support\Money;
use Illuminate\Support\Facades\DB;

class ConfirmProductImport
{
    public function __construct(protected ReceiveStock $receiveStock) {}

    public function execute(ProductImport $import, int $userId): ProductImport
    {
        return DB::transaction(function () use ($import, $userId) {
            $import = ProductImport::where('id', $import->id)->lockForUpdate()->firstOrFail();

            if ($import->status !== 'pending') {
                return $import; // no-op
            }

            $rows = $import->valid_rows_json ?? [];
            $imported = 0;
            $warehouse = Warehouse::where('shop_id', $import->shop_id)->where('is_default', true)->first()
                ?? Warehouse::where('shop_id', $import->shop_id)->first();

            foreach ($rows as $row) {
                $product = Product::create([
                    'shop_id' => $import->shop_id,
                    'name' => $row['name'],
                    'name_ne' => $row['name_ne'],
                    'sku' => $row['sku'],
                    'barcode' => $row['barcode'],
                    'category_id' => $row['category_id'],
                    'unit_id' => $row['unit_id'],
                    'cost_price' => $row['cost_price'],
                    'selling_price' => $row['selling_price'],
                    'low_stock_threshold' => $row['low_stock_threshold'],
                    'track_stock' => true,
                    'is_active' => true,
                ]);

                if (Money::compare((string) $row['opening_stock'], '0', 3) > 0) {
                    $this->receiveStock->execute(
                        $product,
                        $warehouse,
                        $row['opening_stock'],
                        $row['cost_price'],
                        MovementType::Opening,
                        'product_import',
                        $import->id,
                        'Opening stock from import',
                        $userId
                    );
                }
                $imported++;
            }

            $import->status = 'completed';
            $import->imported_rows = $imported;
            $import->save();

            AuditTrail::record($import, $userId, 'products_imported', null, [
                'import_id' => $import->id,
                'imported' => $imported,
                'skipped' => $import->skipped_rows,
                'failed' => $import->invalid_rows,
                'file_hash' => $import->file_hash,
            ]);

            return $import;
        });
    }
}
