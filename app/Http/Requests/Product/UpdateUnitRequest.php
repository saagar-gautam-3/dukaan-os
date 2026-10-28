<?php

declare(strict_types=1);

namespace App\Http\Requests\Product;

use App\Models\Unit;
use Illuminate\Foundation\Http\FormRequest;

class UpdateUnitRequest extends FormRequest
{
    public function authorize(): bool
    {
        $unit = $this->route('unit');

        return $unit instanceof Unit && ($this->user()?->can('update', $unit) ?? false);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'is_fractional' => ['nullable', 'boolean'],
        ];
    }
}
