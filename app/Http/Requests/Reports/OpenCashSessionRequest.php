<?php

declare(strict_types=1);

namespace App\Http\Requests\Reports;

use App\Traits\NormalizesNepaliInput;
use Illuminate\Foundation\Http\FormRequest;

class OpenCashSessionRequest extends FormRequest
{
    use NormalizesNepaliInput;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'opening_cash' => ['required', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string', 'max:500'],
        ];
    }
}
