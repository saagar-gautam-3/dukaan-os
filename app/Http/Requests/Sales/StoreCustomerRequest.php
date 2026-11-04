<?php

declare(strict_types=1);

namespace App\Http\Requests\Sales;

use App\Models\Customer;
use App\Rules\NepalMobilePhone;
use App\Traits\NormalizesNepaliInput;
use Illuminate\Foundation\Http\FormRequest;

class StoreCustomerRequest extends FormRequest
{
    use NormalizesNepaliInput;

    public function authorize(): bool
    {
        return $this->user()?->can('create', Customer::class) ?? false;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:100'],
            'phone' => ['nullable', 'string', 'max:20', new NepalMobilePhone],
            'email' => ['nullable', 'email', 'max:100'],
            'address' => ['nullable', 'string', 'max:250'],
            'pan_no' => ['nullable', 'string', 'max:20'],
            'credit_limit' => ['nullable', 'numeric', 'min:0'],
        ];
    }

    public function messages(): array
    {
        return [
        ];
    }
}
