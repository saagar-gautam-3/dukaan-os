<?php

declare(strict_types=1);

namespace App\Actions\Shop;

use App\Models\Shop;
use Illuminate\Support\Facades\DB;

class UpdateShopSettings
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function execute(Shop $shop, array $data): Shop
    {
        return DB::transaction(function () use ($shop, $data) {
            $shop->update([
                'name' => $data['name'],
                'business_type' => $data['business_type'],
                'timezone' => $data['timezone'],
            ]);

            return $shop;
        });
    }
}
