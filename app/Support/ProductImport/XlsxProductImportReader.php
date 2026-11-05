<?php

declare(strict_types=1);

namespace App\Support\ProductImport;

use PhpOffice\PhpSpreadsheet\Reader\Xlsx as XlsxReader;

class XlsxProductImportReader implements ProductImportReader
{
    public function read(string $path): array
    {
        // Validate zip signature (xlsx is a zip)
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
        $rows = [];
        foreach ($sheet->getRowIterator() as $row) {
            $cells = [];
            foreach ($row->getCellIterator() as $cell) {
                $val = $cell->getValue();
                if (is_float($val)) {
                    // convert float to decimal string
                    $s = rtrim(rtrim(sprintf('%.6f', $val), '0'), '.');
                    if ($s === '' || $s === '-') {
                        $s = '0';
                    }
                    if (floor($val) == $val && abs($val) < 1e15) {
                        $s = (string) (int) $val;
                    }
                    $cells[] = $s;
                } elseif (is_int($val) || is_string($val)) {
                    $cells[] = (string) $val;
                } else {
                    $cells[] = '';
                }
            }
            if (empty($header)) {
                $header = array_map(fn ($h) => strtolower(trim((string) $h)), $cells);

                continue;
            }
            // skip fully empty rows
            if (implode('', $cells) === '') {
                continue;
            }
            $rows[] = array_map('trim', $cells);
        }

        $spreadsheet->disconnectWorksheets();
        unset($spreadsheet);

        if (empty($header)) {
            throw new \InvalidArgumentException(__('products.import_error_empty'));
        }

        return ['header' => $header, 'rows' => $rows];
    }
}
