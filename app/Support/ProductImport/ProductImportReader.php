<?php

declare(strict_types=1);

namespace App\Support\ProductImport;

interface ProductImportReader
{
    /**
     * @return array{header: array<int,string>, rows: array<int,array<int,string>>}
     */
    public function read(string $path): array;
}
