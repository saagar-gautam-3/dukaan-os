<?php

declare(strict_types=1);

namespace App\Actions\Product;

use App\Models\Category;
use App\Models\Unit;

final class QueryProductFormData
{
    public function categories(): mixed
    {
        return Category::orderBy('name')->get();
    }

    public function units(): mixed
    {
        return Unit::orderBy('name')->get();
    }
}
