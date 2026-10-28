<?php

declare(strict_types=1);

namespace App\Http\Requests\Inventory;

use App\Models\StockBalance;
use Illuminate\Foundation\Http\FormRequest;

class AdjustStockRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('adjust', StockBalance::class) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'warehouse_id' => ['required', 'exists:warehouses,id'],
            'product_id' => ['required', 'exists:products,id'],
            'type' => ['required', 'in:in,out'],
            'quantity' => ['required', 'numeric', 'gt:0'],
            'reason' => ['required', 'string', 'max:255'],
            'unit_cost' => ['nullable', 'numeric', 'min:0'],
        ];
    }
}
