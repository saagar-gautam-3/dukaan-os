<?php

declare(strict_types=1);

namespace App\Http\Requests\Reports;

use App\Models\CashSession;
use App\Traits\NormalizesNepaliInput;
use Illuminate\Foundation\Http\FormRequest;

class CloseCashSessionRequest extends FormRequest
{
    use NormalizesNepaliInput;

    public function authorize(): bool
    {
        $cashSession = $this->route('cashSession');

        return $cashSession instanceof CashSession && ($this->user()?->can('close', $cashSession) ?? false);
    }

    public function rules(): array
    {
        return [
            'closing_cash' => ['required', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string', 'max:500'],
        ];
    }
}
