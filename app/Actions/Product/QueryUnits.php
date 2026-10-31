<?php

declare(strict_types=1);

namespace App\Actions\Product;

use App\Models\Unit;

final class QueryUnits
{
    public function index(): mixed
    {
        return Unit::withCount('products')->orderBy('name')->get();
    }
}
