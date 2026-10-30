<?php

declare(strict_types=1);

namespace App\Traits;

use App\Support\NepaliNumber;
use App\Support\NepaliPhone;

trait NormalizesNepaliInput
{
    /**
     * Prepare the data for validation:
     * - Normalizes Devanagari numerals ०-९ to 0-9 in all inputs (nested arrays included)
     * - Normalizes Nepal phone numbers (+977/977/0 prefixes stripped, 10-digit mobile)
     */
    protected function prepareForValidation(): void
    {
        $data = $this->all();
        $this->replace($this->normalizeValues($data));
    }

    /**
     * Recursively normalize inputs.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function normalizeValues(array $data): array
    {
        $phoneFields = ['phone', 'customer_phone', 'mobile', 'contact_number'];

        foreach ($data as $key => $value) {
            if (is_array($value)) {
                $data[$key] = $this->normalizeValues($value);
            } elseif (is_string($value)) {
                if (in_array($key, $phoneFields, true)) {
                    $normalizedPhone = NepaliPhone::normalize($value);
                    if ($normalizedPhone !== null) {
                        $data[$key] = $normalizedPhone;
                    }
                } else {
                    // Convert any Devanagari digits ०-९ to 0-9
                    $data[$key] = NepaliNumber::toAscii($value);
                }
            }
        }

        return $data;
    }
}
