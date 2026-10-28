<?php

declare(strict_types=1);

namespace App\Actions\Product;

use App\Models\Unit;
use Illuminate\Support\Facades\DB;

class UpdateUnit
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function execute(Unit $unit, array $data): Unit
    {
        return DB::transaction(function () use ($unit, $data) {
            $unit->update([
                'name' => $data['name'],
                'is_fractional' => isset($data['is_fractional']) ? (bool) $data['is_fractional'] : $unit->is_fractional,
            ]);

            return $unit;
        });
    }
}
