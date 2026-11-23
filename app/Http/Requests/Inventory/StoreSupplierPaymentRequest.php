<?php

declare(strict_types=1);

namespace App\Http\Requests\Inventory;

use App\Tenancy\TenantContext;
use Carbon\Carbon;
use Illuminate\Foundation\Http\FormRequest;

class StoreSupplierPaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('pay', $this->route('supplier')) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'amount' => [
                'required',
                'string',
                'regex:/^\d+(?:\.\d{1,2})?$/',
                function (string $attribute, mixed $value, callable $fail): void {
                    $amount = (string) $value;
                    if (! preg_match('/^\d+(?:\.\d{1,2})?$/', $amount)) {
                        $fail(__('errors.supplier_payment_amount_decimals'));

                        return;
                    }

                    if (bccomp($amount, '0.01', 2) < 0) {
                        $fail(__('errors.supplier_payment_amount_positive'));
                    }
                },
            ],
            'payment_method' => ['required', 'in:cash,bank,qr'],
            'reference_number' => ['nullable', 'string', 'max:100'],
            'notes' => ['nullable', 'string', 'max:255'],
            'payment_date' => [
                'nullable',
                'date_format:Y-m-d',
                function (string $attribute, mixed $value, callable $fail): void {
                    $timezone = (string) (TenantContext::shop()->timezone ?? config('app.timezone'));
                    $paidOn = Carbon::parse((string) $value, $timezone)->startOfDay();

                    if ($paidOn->greaterThan(Carbon::today($timezone))) {
                        $fail(__('errors.supplier_payment_date_future'));
                    }
                },
            ],
            'idempotency_key' => ['nullable', 'string', 'max:128'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'amount.regex' => __('errors.supplier_payment_amount_decimals'),
            'payment_date.date_format' => __('errors.supplier_payment_date_format'),
        ];
    }
}
