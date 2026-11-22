<?php

declare(strict_types=1);

namespace App\Http\Requests\Shop;

use App\Rules\NepalMobilePhone;
use App\Tenancy\TenantContext;
use App\Traits\NormalizesNepaliInput;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\UploadedFile;

class UpdateShopSettingsRequest extends FormRequest
{
    use NormalizesNepaliInput;

    public function authorize(): bool
    {
        $shop = TenantContext::currentShop();

        return $shop !== null && ($this->user()?->can('manage', $shop) ?? false);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'business_type' => ['required', 'string', 'max:50'],
            'timezone' => ['required', 'string', 'timezone'],
            'show_bs_dates' => ['nullable', 'boolean'],
            'devanagari_digits' => ['nullable', 'boolean'],
            'address' => ['nullable', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:20', new NepalMobilePhone],
            'pan_number' => ['nullable', 'string', 'max:50'],
            'sku_auto_generate' => ['nullable', 'boolean'],
            'thermal_paper_width' => ['nullable', 'in:58,80'],
            'invoice_footer' => ['nullable', 'string', 'max:200'],
            'logo' => ['nullable', 'file', 'max:1024', 'mimes:png,jpg,jpeg,webp', function ($attribute, $value, $fail) {
                if (! $value instanceof UploadedFile) {
                    return;
                }
                $path = $value->getRealPath();
                if (! is_string($path) || $path === '' || ! is_file($path)) {
                    $fail(__('shops.logo_invalid'));

                    return;
                }
                $info = @getimagesize($path);
                if ($info === false || ! isset($info['mime']) || ! in_array($info['mime'], ['image/png', 'image/jpeg', 'image/webp'], true)) {
                    $fail(__('shops.logo_invalid'));
                }
            }],
            'remove_logo' => ['nullable', 'boolean'],
        ];
    }
}
