<?php

declare(strict_types=1);

namespace App\Actions\Product;

use App\Models\Unit;
use Illuminate\Support\Facades\DB;

class CreateUnit
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function execute(array $data): Unit
    {
        return DB::transaction(function () use ($data) {
            return Unit::create([
                'name' => $data['name'],
                'symbol' => $data['symbol'],
                'is_fractional' => (bool) ($data['is_fractional'] ?? false),
            ]);
        });
    }
}
