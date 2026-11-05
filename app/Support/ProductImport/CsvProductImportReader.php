<?php

declare(strict_types=1);

namespace App\Support\ProductImport;

use App\Support\NepaliNumber;

class CsvProductImportReader implements ProductImportReader
{
    public function read(string $path): array
    {
        $content = file_get_contents($path);
        if ($content === false) {
            throw new \RuntimeException(__('products.import_error_read'));
        }
        if (str_starts_with($content, "\xEF\xBB\xBF")) {
            $content = substr($content, 3);
        }
        if (! mb_check_encoding($content, 'UTF-8')) {
            throw new \InvalidArgumentException(__('products.import_error_encoding'));
        }

        $lines = preg_split('/\r\n|\r|\n/', $content);
        $rows = [];
        foreach ($lines as $line) {
            if (trim($line) === '') {
                continue;
            }
            $delimiter = str_contains($line, ';') && ! str_contains($line, ',') ? ';' : ',';
            $rows[] = str_getcsv($line, $delimiter);
        }

        if (empty($rows)) {
            throw new \InvalidArgumentException(__('products.import_error_empty'));
        }

        $header = array_map(fn ($h) => strtolower(trim((string) $h)), $rows[0]);
        $dataRows = array_slice($rows, 1);
        $outRows = [];
        foreach ($dataRows as $r) {
            $outRows[] = array_map(fn ($c) => NepaliNumber::toAscii(trim((string) $c)), $r);
        }

        return ['header' => $header, 'rows' => $outRows];
    }
}
