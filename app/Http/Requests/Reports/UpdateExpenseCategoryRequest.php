<?php

declare(strict_types=1);

namespace App\Http\Requests\Reports;

use Illuminate\Foundation\Http\FormRequest;

final class UpdateExpenseCategoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('update', $this->route('expenseCategory')) ?? false;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:100'],
        ];
    }
}
