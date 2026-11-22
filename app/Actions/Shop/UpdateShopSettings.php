<?php

declare(strict_types=1);

namespace App\Actions\Shop;

use App\Models\Shop;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

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
            if (array_key_exists('sku_auto_generate', $data)) {
                $settings['sku_auto_generate'] = (bool) $data['sku_auto_generate'];
            }
            if (array_key_exists('thermal_paper_width', $data) && $data['thermal_paper_width'] !== null) {
                $settings['thermal_paper_width'] = (string) $data['thermal_paper_width'];
            }
            if (array_key_exists('invoice_footer', $data)) {
                $settings['invoice_footer'] = $data['invoice_footer'];
            }

            $disk = Storage::disk('public');
            $previousLogo = $settings['logo_path'] ?? null;

            if (! empty($data['remove_logo'])) {
                if (is_string($previousLogo) && $previousLogo !== '' && $disk->exists($previousLogo)) {
                    $disk->delete($previousLogo);
                }
                unset($settings['logo_path']);
            } elseif (isset($data['logo']) && $data['logo'] instanceof UploadedFile) {
                $file = $data['logo'];
                $ext = strtolower($file->getClientOriginalExtension());
                if (! in_array($ext, ['png', 'jpg', 'jpeg', 'webp'], true)) {
                    $ext = 'png';
                }
                $path = 'shops/'.$shop->id.'/logo/'.(string) Str::uuid().'.'.$ext;
                $disk->putFileAs(dirname($path), $file, basename($path), 'public');
                if (is_string($previousLogo) && $previousLogo !== '' && $previousLogo !== $path && $disk->exists($previousLogo)) {
                    $disk->delete($previousLogo);
                }
                $settings['logo_path'] = $path;
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
