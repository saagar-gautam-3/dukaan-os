<?php

declare(strict_types=1);

namespace App\Http\Requests\Product;

use App\Models\Unit;
use Illuminate\Foundation\Http\FormRequest;

class StoreUnitRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', Unit::class) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'symbol' => ['required', 'string', 'max:20', 'unique:units,symbol'],
            'is_fractional' => ['nullable', 'boolean'],
        ];
    }
}
