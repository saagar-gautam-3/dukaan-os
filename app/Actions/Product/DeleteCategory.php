<?php

declare(strict_types=1);

namespace App\Actions\Product;

use App\Models\Category;
use Illuminate\Support\Facades\DB;

class DeleteCategory
{
    public function execute(Category $category): void
    {
        DB::transaction(function () use ($category) {
            $category->delete();
        });
    }
}
