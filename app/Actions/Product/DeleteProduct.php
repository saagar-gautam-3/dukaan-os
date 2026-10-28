<?php

declare(strict_types=1);

namespace App\Actions\Product;

use App\Models\Product;
use Illuminate\Support\Facades\DB;

class DeleteProduct
{
    public function execute(Product $product): void
    {
        DB::transaction(function () use ($product) {
            $product->delete();
        });
    }
}
