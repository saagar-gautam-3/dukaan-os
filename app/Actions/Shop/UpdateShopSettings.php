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
            $settings = $shop->settings ?? [];

            if (array_key_exists('show_bs_dates', $data)) {
                $settings['show_bs_dates'] = (bool) $data['show_bs_dates'];
            }
            if (array_key_exists('devanagari_digits', $data)) {
                $settings['devanagari_digits'] = (bool) $data['devanagari_digits'];
            }
            if (array_key_exists('address', $data)) {
                $settings['address'] = $data['address'];
            }
            if (array_key_exists('phone', $data)) {
                $settings['phone'] = $data['phone'];
            }
            if (array_key_exists('pan_number', $data)) {
                $settings['pan_number'] = $data['pan_number'];
            }

            $shop->update([
                'name' => $data['name'],
                'business_type' => $data['business_type'],
                'timezone' => $data['timezone'],
                'settings' => $settings,
            ]);

            return $shop;
        });
    }
}
