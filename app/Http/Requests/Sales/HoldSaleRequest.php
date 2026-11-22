<?php

declare(strict_types=1);

namespace App\Http\Requests\Sales;

use App\Models\Sale;
use App\Tenancy\TenantContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class HoldSaleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', Sale::class) ?? false;
    }

    public function rules(): array
    {
        $shopId = TenantContext::shopId();

        return [
            'label' => ['nullable', 'string', 'max:60'],
            'customer_id' => ['nullable', 'integer', Rule::exists('customers', 'id')->where('shop_id', $shopId)],
            'items' => ['required', 'array', 'min:1', 'max:200'],
            'items.*.product_id' => ['required', 'integer', Rule::exists('products', 'id')->where('shop_id', $shopId)],
            'items.*.quantity' => ['required', 'numeric', 'min:0.001'],
        ];
    }
}
