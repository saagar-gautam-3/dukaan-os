<?php

declare(strict_types=1);

namespace App\Http\Requests\Reports;

use App\Models\ExpenseCategory;
use Illuminate\Foundation\Http\FormRequest;

class StoreExpenseCategoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', ExpenseCategory::class) ?? false;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:100'],
        ];
    }
}
