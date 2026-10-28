<?php

declare(strict_types=1);

namespace App\Http\Requests\Reports;

use Illuminate\Foundation\Http\FormRequest;

class ProfitReportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'range' => ['nullable', 'in:today,this_week,this_month,custom'],
            'custom_from' => ['nullable', 'date', 'required_if:range,custom'],
            'custom_to' => ['nullable', 'date', 'required_if:range,custom', 'after_or_equal:custom_from'],
        ];
    }
}
