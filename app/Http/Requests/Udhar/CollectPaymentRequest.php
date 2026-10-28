<?php

declare(strict_types=1);

namespace App\Http\Requests\Udhar;

use Illuminate\Foundation\Http\FormRequest;

final class CollectPaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('udhar.collect') ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'amount' => ['required', 'numeric', 'min:0.01'],
            'payment_method' => ['required', 'string', 'in:cash,qr,bank_transfer,cheque,other'],
            'notes' => ['nullable', 'string', 'max:500'],
            'allocations' => ['nullable', 'array'],
            'allocations.*.sale_id' => ['required_with:allocations', 'integer'],
            'allocations.*.amount' => ['required_with:allocations', 'numeric', 'min:0.01'],
        ];
    }
}
