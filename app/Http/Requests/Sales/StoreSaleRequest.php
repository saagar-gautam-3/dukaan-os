<?php

declare(strict_types=1);

namespace App\Http\Requests\Sales;

use App\Models\Sale;
use App\Tenancy\TenantContext;
use App\Traits\NormalizesNepaliInput;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreSaleRequest extends FormRequest
{
    use NormalizesNepaliInput;

    public function authorize(): bool
    {
        return $this->user()?->can('create', Sale::class) ?? false;
    }

    public function rules(): array
    {
        $shopId = TenantContext::shopId();

        return [
            'warehouse_id' => ['nullable', 'integer', Rule::exists('warehouses', 'id')->where('shop_id', $shopId)],
            'customer_id' => ['nullable', 'integer', Rule::exists('customers', 'id')->where('shop_id', $shopId)],
            'notes' => ['nullable', 'string', 'max:500'],
            'post_immediately' => ['boolean'],
            'payment_method' => ['nullable', 'in:cash,bank,qr'],
            'payment_amount' => ['nullable', 'numeric', 'min:0'],
            'payment_reference' => ['nullable', 'string', 'max:100'],
            'idempotency_key' => ['nullable', 'string', 'max:128'],

            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', 'integer', Rule::exists('products', 'id')->where('shop_id', $shopId)],
            'items.*.product_variant_id' => ['nullable', 'integer'],
            'items.*.quantity' => ['required', 'numeric', 'min:0.001'],
            'items.*.unit_price' => ['required', 'numeric', 'min:0'],
            'items.*.discount' => ['nullable', 'numeric', 'min:0'],
            'items.*.tax' => ['nullable', 'numeric', 'min:0'],
        ];
    }
}
