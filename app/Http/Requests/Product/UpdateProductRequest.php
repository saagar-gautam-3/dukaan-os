<?php

declare(strict_types=1);

namespace App\Http\Requests\Product;

use App\Models\Product;
use App\Tenancy\TenantContext;
use App\Traits\NormalizesNepaliInput;
use Illuminate\Foundation\Http\FormRequest;

class UpdateProductRequest extends FormRequest
{
    use NormalizesNepaliInput;

    public function authorize(): bool
    {
        $product = $this->route('product');

        return $product instanceof Product && ($this->user()?->can('update', $product) ?? false);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $product = $this->route('product');
        $shop = TenantContext::currentShop();
        $auto = (bool) ($shop?->settings['sku_auto_generate'] ?? true);

        if ($auto) {
            $skuRules = ['nullable', 'string', 'max:100'];
        } else {
            $ignore = $product instanceof Product ? $product->id : 'NULL';
            $skuRules = ['required', 'string', 'max:100', 'unique:products,sku,'.$ignore.',id,shop_id,'.$shop?->id];
        }

        return [
            'name' => ['required', 'string', 'max:255'],
            'name_ne' => ['nullable', 'string', 'max:255'],
            'sku' => $skuRules,
            'barcode' => ['nullable', 'string', 'max:100'],
            'category_id' => ['nullable', 'exists:categories,id'],
            'unit_id' => ['required', 'exists:units,id'],
            'cost_price' => ['required', 'numeric', 'min:0'],
            'selling_price' => ['required', 'numeric', 'min:0'],
            'min_stock_alert' => ['nullable', 'numeric', 'min:0'],
            'low_stock_threshold' => ['nullable', 'numeric', 'min:0'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }
}
