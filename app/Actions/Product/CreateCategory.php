<?php

declare(strict_types=1);

namespace App\Actions\Product;

use App\Models\Category;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class CreateCategory
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function execute(array $data): Category
    {
        return DB::transaction(function () use ($data) {
            $slugBase = ! empty($data['slug']) ? Str::slug($data['slug']) : Str::slug($data['name']);

            return Category::create([
                'name' => $data['name'],
                'slug' => $slugBase.'-'.uniqid(),
            ]);
        });
    }
}
