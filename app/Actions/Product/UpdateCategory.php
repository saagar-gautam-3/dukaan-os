<?php

declare(strict_types=1);

namespace App\Actions\Product;

use App\Models\Category;
use Illuminate\Support\Facades\DB;

class UpdateCategory
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function execute(Category $category, array $data): Category
    {
        return DB::transaction(function () use ($category, $data) {
            $category->update([
                'name' => $data['name'],
            ]);

            return $category;
        });
    }
}
