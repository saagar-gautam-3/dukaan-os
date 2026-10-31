<?php

declare(strict_types=1);

namespace App\Actions\Product;

use App\Models\Category;

final class QueryCategories
{
    public function index(): mixed
    {
        return Category::withCount('products')->orderBy('name')->get();
    }
}
