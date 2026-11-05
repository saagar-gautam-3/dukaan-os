<?php

declare(strict_types=1);

namespace App\Support\ProductImport;

use PhpOffice\PhpSpreadsheet\Reader\Xlsx as XlsxReader;

class XlsxProductImportReader implements ProductImportReader
{
    private const TEXT_COLUMNS = ['name', 'name_ne', 'sku', 'barcode', 'category'];

    public function read(string $path): array
    {
        $sig = file_get_contents($path, false, null, 0, 4);
        if ($sig !== "PK\x03\x04") {
            throw new \InvalidArgumentException(__('products.import_error_encoding'));
        }

        $reader = new XlsxReader;
        $reader->setReadDataOnly(true);
        $reader->setReadEmptyCells(false);

        $spreadsheet = $reader->load($path);
        $sheet = $spreadsheet->getSheetByName('Products') ?? $spreadsheet->getActiveSheet();

        $header = [];
        $rawRows = [];
        foreach ($sheet->getRowIterator() as $row) {
            $cells = [];
            foreach ($row->getCellIterator() as $cell) {
                $cells[] = $cell->getValue();
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

        $spreadsheet->disconnectWorksheets();
        unset($spreadsheet);

        if (empty($header)) {
            throw new \InvalidArgumentException(__('products.import_error_empty'));
        }

        $rows = [];
        foreach ($rawRows as $raw) {
            $cells = [];
            foreach ($raw as $i => $val) {
                $colName = $header[$i] ?? '';
                $isText = in_array($colName, self::TEXT_COLUMNS, true);

                if (is_float($val)) {
                    if ($isText) {
                        if (abs($val) > 1e15 || (floor($val) != $val)) {
                            // numeric cell in text column with too many digits or decimal
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
            $rows[] = array_map('trim', $cells);
        }

        return ['header' => $header, 'rows' => $rows];
    }
}
