<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\ProductImport;
use App\Models\Shop;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class ProductImportFactory extends Factory
{
    protected $model = ProductImport::class;

    public function definition(): array
    {
        $shop = Shop::factory()->create();

        return [
            'shop_id' => $shop->id,
            'user_id' => User::factory(),
            'original_name' => 'import.csv',
            'stored_path' => 'imports/'.$shop->id.'/'.Str::uuid().'.csv',
            'file_hash' => str_repeat('a', 64),
            'status' => 'pending',
            'total_rows' => 0,
            'valid_rows' => 0,
            'invalid_rows' => 0,
            'skipped_rows' => 0,
            'imported_rows' => 0,
        ];
    }
}
