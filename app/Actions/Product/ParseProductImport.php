<?php

declare(strict_types=1);

namespace App\Actions\Product;

use App\Models\Category;
use App\Models\Product;
use App\Models\Shop;
use App\Models\Unit;
use App\Support\Money;
use App\Support\NepaliNumber;

class ParseProductImport
{
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
        $errorEntries = [];
        $seenSkus = [];
        $seenBarcodes = [];
        $seenNameUnit = [];

        $lineNo = 2;
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

            $rowErrors = [];

            $rowSnapshot = [
                'name' => $name,
                'name_ne' => $nameNe,
                'sku' => $sku,
                'barcode' => $barcode,
                'category' => $categoryName,
                'unit' => $get('unit'),
                'cost_price' => $get('cost_price'),
                'selling_price' => $get('selling_price'),
                'opening_stock' => $get('opening_stock'),
                'reorder_level' => $get('reorder_level'),
            ];

            $add = function (string $field, string $code, string $key, array $params = [], string $value = '') use (&$rowErrors, $lineNo, $rowSnapshot): void {
                $rowErrors[] = [
                    'line' => $lineNo,
                    'field' => $field,
                    'code' => $code,
                    'message' => __($key, $params),
                    'value' => mb_substr($value, 0, 200),
                    'row' => $rowSnapshot,
                ];
            };

            if ($barcode !== '' && preg_match('/^[0-9]+([.][0-9]+)?[eE][+][0-9]+$/', $barcode)) {
                $add('barcode', 'barcode_scientific', 'products.import_error_barcode_scientific', [], $barcode);
            }

            if ($name === '') {
                $add('name', 'name_required', 'products.import_error_name_required', [], $name);
            } elseif (mb_strlen($name) > 255) {
                $add('name', 'name_too_long', 'products.import_error_name_too_long', [], $name);
            }
            if ($nameNe !== '' && mb_strlen($nameNe) > 255) {
                $add('name_ne', 'name_ne_too_long', 'products.import_error_name_ne_too_long', [], $nameNe);
            }
            if ($sku !== '' && mb_strlen($sku) > 100) {
                $add('sku', 'sku_too_long', 'products.import_error_sku_too_long', [], $sku);
            }
            if ($barcode !== '' && mb_strlen($barcode) > 100) {
                $add('barcode', 'barcode_too_long', 'products.import_error_barcode_too_long', [], $barcode);
            }
            if ($categoryName !== '' && mb_strlen($categoryName) > 255) {
                $add('category', 'category_too_long', 'products.import_error_category_too_long', [], $categoryName);
            }

            $unit = $units->get($unitKey);
            if ($unit === null) {
                $add('unit', 'unknown_unit', 'products.import_error_unknown_unit', ['value' => $get('unit')], $get('unit'));
            }

            $costStr = NepaliNumber::toAscii($costStr);
            $sellingStr = NepaliNumber::toAscii($sellingStr);
            $stockStr = NepaliNumber::toAscii($stockStr);
            $reorderStr = NepaliNumber::toAscii($reorderStr);

            $costPrice = null;
            if ($costStr === '') {
                $add('cost_price', 'cost_required', 'products.import_error_cost_required', [], $costStr);
            } elseif (! is_numeric($costStr) || stripos($costStr, 'e') !== false) {
                $add('cost_price', 'cost_number', 'products.import_error_cost_number', [], $costStr);
            } elseif (Money::compare($costStr, '0', 2) < 0) {
                $add('cost_price', 'cost_negative', 'products.import_error_cost_negative', [], $costStr);
            } elseif (preg_match('/\.\d{3,}/', $costStr)) {
                $add('cost_price', 'cost_decimals', 'products.import_error_cost_decimals', [], $costStr);
            } elseif (Money::compare($costStr, '1000000000', 2) >= 0) {
                $add('cost_price', 'cost_large', 'products.import_error_cost_large', [], $costStr);
            } else {
                $costPrice = $costStr;
            }

            $sellingPrice = null;
            if ($sellingStr === '') {
                $add('selling_price', 'selling_required', 'products.import_error_selling_required', [], $sellingStr);
            } elseif (! is_numeric($sellingStr) || stripos($sellingStr, 'e') !== false) {
                $add('selling_price', 'selling_number', 'products.import_error_selling_number', [], $sellingStr);
            } elseif (Money::compare($sellingStr, '0', 2) < 0) {
                $add('selling_price', 'selling_negative', 'products.import_error_selling_negative', [], $sellingStr);
            } elseif (preg_match('/\.\d{3,}/', $sellingStr)) {
                $add('selling_price', 'selling_decimals', 'products.import_error_selling_decimals', [], $sellingStr);
            } elseif (Money::compare($sellingStr, '1000000000', 2) >= 0) {
                $add('selling_price', 'selling_large', 'products.import_error_selling_large', [], $sellingStr);
            } else {
                $sellingPrice = $sellingStr;
            }

            $openingStock = '0';
            if ($stockStr !== '') {
                if (! is_numeric($stockStr) || stripos($stockStr, 'e') !== false) {
                    $add('opening_stock', 'stock_number', 'products.import_error_stock_number', [], $stockStr);
                } elseif (Money::compare($stockStr, '0', 3) < 0) {
                    $add('opening_stock', 'stock_negative', 'products.import_error_stock_negative', [], $stockStr);
                } elseif (preg_match('/\.\d{4,}/', $stockStr)) {
                    $add('opening_stock', 'stock_decimals', 'products.import_error_stock_decimals', [], $stockStr);
                } elseif (Money::compare($stockStr, '1000000000', 3) >= 0) {
                    $add('opening_stock', 'stock_large', 'products.import_error_stock_large', [], $stockStr);
                } else {
                    $openingStock = $stockStr;
                }
            }

            $reorderLevel = '0';
            if ($reorderStr !== '') {
                if (! is_numeric($reorderStr) || stripos($reorderStr, 'e') !== false) {
                    $add('reorder_level', 'reorder_number', 'products.import_error_reorder_number', [], $reorderStr);
                } else {
                    $reorderLevel = $reorderStr;
                }
            }

            if ($costPrice !== null && $sellingPrice !== null && Money::compare($openingStock, '0', 3) > 0 && Money::compare((string) $costPrice, '0', 2) <= 0) {
                $add('opening_stock', 'stock_requires_cost', 'products.import_error_stock_requires_cost', [], $stockStr);
            }

            if ($sku !== '') {
                if (isset($seenSkus[$sku])) {
                    $add('sku', 'duplicate_sku', 'products.import_error_duplicate_sku', [], $sku);
                }
            }
            if ($barcode !== '') {
                if (isset($seenBarcodes[$barcode])) {
                    $add('barcode', 'duplicate_barcode', 'products.import_error_duplicate_barcode', [], $barcode);
                }
            }
            $nameUnitKey = strtolower($name).'|'.($unit?->id ?? '');
            if ($sku === '' && isset($seenNameUnit[$nameUnitKey])) {
                $add('name', 'duplicate_product', 'products.import_error_duplicate_product', [], $name);
            }

            $skip = false;
            if (empty($rowErrors)) {
                if ($sku !== '' && Product::where('shop_id', $shopId)->where('sku', $sku)->exists()) {
                    $skip = true;
                } elseif ($barcode !== '' && Product::where('shop_id', $shopId)->where('barcode', $barcode)->exists()) {
                    $skip = true;
                } elseif ($sku === '' && $unit !== null && Product::where('shop_id', $shopId)->where('name', $name)->where('unit_id', $unit->id)->exists()) {
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

            if (! empty($rowErrors)) {
                $invalid[] = ['line' => $lineNo, 'errors' => $rowErrors];
                foreach ($rowErrors as $e) {
                    $errorEntries[] = $e;
                }
            } elseif ($skip) {
                // counted as skipped
            } else {
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
            'errors' => $errorEntries,
            'skipped' => count($dataRows) - count($valid) - count($invalid),
            'headers' => $header,
            'warnings' => $warnings,
        ];
    }
}
