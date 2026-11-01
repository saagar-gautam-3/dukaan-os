<?php

declare(strict_types=1);

namespace App\Http\Requests\Shop;

use App\Rules\NepalMobilePhone;
use App\Tenancy\TenantContext;
use App\Traits\NormalizesNepaliInput;
use Illuminate\Foundation\Http\FormRequest;

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
        ];
    }
}
