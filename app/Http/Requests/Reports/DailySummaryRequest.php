<?php

declare(strict_types=1);

namespace App\Http\Requests\Reports;

use App\Tenancy\TenantContext;
use Carbon\Carbon;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Validates the daily summary page's optional ?date=YYYY-MM-DD parameter.
 */
class DailySummaryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, string> */
    public function rules(): array
    {
        $shop = TenantContext::has() ? TenantContext::currentShop() : null;
        $timezone = $shop?->timezone ?? 'Asia/Kathmandu';

        return [
            'date' => [
                'nullable',
                'string',
                'date_format:Y-m-d',
                'before_or_equal:'.Carbon::now($timezone)->toDateString(),
            ],
        ];
    }
}
