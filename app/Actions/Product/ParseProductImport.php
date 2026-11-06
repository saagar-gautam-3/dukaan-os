<?php

declare(strict_types=1);

namespace App\Actions\Product;

use App\Models\Category;
use App\Models\Shop;
use App\Models\Unit;
use App\Support\Money;
use App\Support\NepaliNumber;

class ParseProductImport
{
    /**
     * Parse and validate a CSV file. Returns an array with keys:
     * total, valid (array of rows), invalid (array of {line, reason}), skipped_existing (int),
     * headers (array), warnings (array).
     *
     * @return array<string, mixed>
     */
    public function execute(array $parsed, int $shopId): array
    {
        $header = array_map(fn ($h) => strtolower(trim((string) $h)), $parsed['header'] ?? []);
        $dataRows = $parsed['rows'] ?? [];

        if (empty($header)) {
            throw new \InvalidArgumentException(__('products.import_error_empty'));
        }

        if (empty($dataRows)) {
            throw new \InvalidArgumentException(__('products.import_error_empty'));
        }

        // Map aliases
        $aliases = [
            'name' => ['name', 'product_name', 'product'],
            'name_ne' => ['name_ne', 'name_nepali'],
            'sku' => ['sku', 'code'],
            'barcode' => ['barcode', 'bar_code'],
            'category' => ['category', 'category_name'],
            'unit' => ['unit', 'unit_code', 'unit_name'],
            'cost_price' => ['cost_price', 'cost', 'purchase_price'],
            'selling_price' => ['selling_price', 'price', 'sale_price', 'mrp'],
            'opening_stock' => ['opening_stock', 'stock', 'opening_qty', 'quantity'],
            'reorder_level' => ['reorder_level', 'min_stock', 'low_stock_threshold'],
        ];

        $colIndex = [];
        foreach ($aliases as $field => $names) {
            foreach ($names as $n) {
                $pos = array_search($n, $header, true);
                if ($pos !== false) {
                    $colIndex[$field] = $pos;
                    break;
                }
            }
        }

        if (! isset($colIndex['name'])) {
            throw new \InvalidArgumentException(__('products.import_error_missing_column', ['column' => 'name']));
        }
        if (! isset($colIndex['unit'])) {
            throw new \InvalidArgumentException(__('products.import_error_missing_column', ['column' => 'unit']));
        }
        if (! isset($colIndex['selling_price'])) {
            throw new \InvalidArgumentException(__('products.import_error_missing_column', ['column' => 'selling_price']));
        }

        $shop = Shop::findOrFail($shopId);
        $autoSku = (bool) ($shop->settings['sku_auto_generate'] ?? true);

        $units = Unit::all()->keyBy(fn ($u) => strtolower($u->symbol ?? $u->name));
        $categories = Category::where('shop_id', $shopId)->get()->keyBy(fn ($c) => strtolower($c->name));

        $valid = [];
        $invalid = [];
        $seenSkus = [];
        $seenBarcodes = [];
        $seenNameUnit = [];

        $lineNo = 2; // data starts at line 2
        foreach ($dataRows as $row) {
            if (count($row) === 1 && trim((string) $row[0]) === '') {
                $lineNo++;

                continue;
            }
            if (count($row) < count($header)) {
                $row = array_pad($row, count($header), '');
            }
            $get = fn (string $field) => isset($colIndex[$field]) ? trim((string) ($row[$colIndex[$field]] ?? '')) : '';

            $name = $get('name');
            $nameNe = $get('name_ne');
            $sku = $get('sku');
            $barcode = $get('barcode');
            $categoryName = $get('category');
            $unitKey = strtolower($get('unit'));
            $costStr = $get('cost_price');
            $sellingStr = $get('selling_price');
            $stockStr = $get('opening_stock');
            $reorderStr = $get('reorder_level');

            $errors = [];

            if ($barcode !== '' && preg_match('/^[0-9]+([.][0-9]+)?[eE][+][0-9]+$/', $barcode)) {
                $errors[] = __('products.import_error_barcode_scientific');
            }

            if ($name === '') {
                $errors[] = 'Name is required.';
            } elseif (mb_strlen($name) > 255) {
                $errors[] = 'Name too long.';
            }

            // unit
            $unit = $units->get($unitKey);
            if ($unit === null) {
                $errors[] = 'Unknown unit.';
            }

            // normalize Devanagari digits
            $costStr = NepaliNumber::toAscii($costStr);
            $sellingStr = NepaliNumber::toAscii($sellingStr);
            $stockStr = NepaliNumber::toAscii($stockStr);
            $reorderStr = NepaliNumber::toAscii($reorderStr);

            $validateMoney = function (string $v, string $field) use (&$errors) {
                if ($v === '') {
                    return null;
                }
                if (! is_numeric($v) || stripos($v, 'e') !== false) {
                    $errors[] = $field.' must be a number.';

                    return null;
                }
                if (Money::compare($v, '0', 2) < 0) {
                    $errors[] = $field.' must be non-negative.';

                    return null;
                }
                // reject 3 decimals
                if (preg_match('/\.\d{3,}/', $v)) {
                    $errors[] = $field.' has too many decimals.';

                    return null;
                }
                if (Money::compare($v, '1000000000', 2) >= 0) {
                    $errors[] = $field.' is too large.';

                    return null;
                }

                return $v;
            };

            $costPrice = $validateMoney($costStr === '' ? '0' : $costStr, 'Cost price');
            $sellingPrice = $validateMoney($sellingStr, 'Selling price');
            if ($sellingPrice === null && $sellingStr !== '') {
                // already errored
            }
            if ($sellingStr === '') {
                $errors[] = 'Selling price is required.';
            }

            // quantity
            $openingStock = '0';
            if ($stockStr !== '') {
                if (! is_numeric($stockStr) || stripos($stockStr, 'e') !== false) {
                    $errors[] = 'Opening stock must be a number.';
                } elseif (Money::compare($stockStr, '0', 3) < 0) {
                    $errors[] = 'Opening stock cannot be negative.';
                } elseif (preg_match('/\.\d{4,}/', $stockStr)) {
                    $errors[] = 'Opening stock has too many decimals.';
                } elseif (Money::compare($stockStr, '1000000000', 3) >= 0) {
                    $errors[] = 'Opening stock is too large.';
                } else {
                    $openingStock = $stockStr;
                }
            }
            $reorderLevel = '0';
            if ($reorderStr !== '') {
                if (! is_numeric($reorderStr) || stripos($reorderStr, 'e') !== false) {
                    $errors[] = 'Reorder level must be a number.';
                } else {
                    $reorderLevel = $reorderStr;
                }
            }

            // opening stock requires cost > 0
            if (Money::compare($openingStock, '0', 3) > 0 && Money::compare((string) $costPrice, '0', 2) <= 0) {
                $errors[] = 'Opening stock requires cost price > 0.';
            }

            // duplicate within file
            if ($sku !== '') {
                if (isset($seenSkus[$sku])) {
                    $errors[] = 'Duplicate SKU in file.';
                }
            }
            if ($barcode !== '') {
                if (isset($seenBarcodes[$barcode])) {
                    $errors[] = 'Duplicate barcode in file.';
                }
            }
            $nameUnitKey = strtolower($name).'|'.($unit?->id ?? '');
            if ($sku === '' && isset($seenNameUnit[$nameUnitKey])) {
                $errors[] = 'Duplicate product in file.';
            }

            // existing in shop
            $skip = false;
            if (empty($errors)) {
                if ($sku !== '' && Product::where('shop_id', $shopId)->where('sku', $sku)->exists()) {
                    $skip = true;
                } elseif ($barcode !== '' && Product::where('shop_id', $shopId)->where('barcode', $barcode)->exists()) {
                    $skip = true;
                } elseif ($sku === '' && Product::where('shop_id', $shopId)->where('name', $name)->where('unit_id', $unit->id)->exists()) {
                    $skip = true;
                }
            }

            if ($sku !== '') {
                $seenSkus[$sku] = true;
            }
            if ($barcode !== '') {
                $seenBarcodes[$barcode] = true;
            }
            $seenNameUnit[$nameUnitKey] = true;

            if (! empty($errors)) {
                $invalid[] = ['line' => $lineNo, 'reason' => implode(' ', $errors)];
            } elseif ($skip) {
                // counted as skipped
            } else {
                // category: match or create
                $categoryId = null;
                if ($categoryName !== '') {
                    $key = strtolower($categoryName);
                    if (isset($categories[$key])) {
                        $categoryId = $categories[$key]->id;
                    } else {
                        $cat = Category::create(['shop_id' => $shopId, 'name' => $categoryName]);
                        $categories[$key] = $cat;
                        $categoryId = $cat->id;
                    }
                }

                $valid[] = [
                    'name' => $name,
                    'name_ne' => $nameNe ?: null,
                    'sku' => $autoSku ? null : ($sku ?: null),
                    'barcode' => $barcode ?: null,
                    'category_id' => $categoryId,
                    'unit_id' => $unit->id,
                    'cost_price' => $costPrice ?? '0',
                    'selling_price' => $sellingPrice ?? '0',
                    'opening_stock' => $openingStock,
                    'low_stock_threshold' => $reorderLevel,
                ];
            }

            $lineNo++;
        }

        if (count($dataRows) > 2000) {
            throw new \InvalidArgumentException(__('products.import_error_too_many_rows', ['max' => 2000]));
        }

        $warnings = [];
        if ($autoSku && isset($colIndex['sku'])) {
            $warnings[] = 'SKU is generated automatically; the sku column was ignored.';
        }

        return [
            'total' => count($dataRows),
            'valid' => $valid,
            'invalid' => $invalid,
            'skipped' => count($dataRows) - count($valid) - count($invalid),
            'headers' => $header,
            'warnings' => $warnings,
        ];
    }
}
