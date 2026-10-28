<?php

declare(strict_types=1);

namespace App\Http\Requests\Shop;

use App\Tenancy\TenantContext;
use Illuminate\Foundation\Http\FormRequest;

class UpdateShopSettingsRequest extends FormRequest
{
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
        ];
    }
}
