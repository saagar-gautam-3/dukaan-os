<?php

declare(strict_types=1);

namespace Tests\Unit\Localization;

use Tests\TestCase;

class TranslationKeyParityTest extends TestCase
{
    public function test_lang_en_and_lang_ne_have_identical_key_sets(): void
    {
        $langPath = base_path('lang');
        $enPath = $langPath.'/en';
        $nePath = $langPath.'/ne';

        $this->assertDirectoryExists($enPath);
        $this->assertDirectoryExists($nePath);

        $enFiles = glob($enPath.'/*.php') ?: [];
        $neFiles = glob($nePath.'/*.php') ?: [];

        $enBaseNames = array_map('basename', $enFiles);
        $neBaseNames = array_map('basename', $neFiles);

        // Ensure both directories have the same translation files
        $missingFilesNe = array_diff($enBaseNames, $neBaseNames);
        $missingFilesEn = array_diff($neBaseNames, $enBaseNames);

        $this->assertEmpty(
            $missingFilesNe,
            'Missing files in lang/ne: '.implode(', ', $missingFilesNe)
        );
        $this->assertEmpty(
            $missingFilesEn,
            'Missing files in lang/en: '.implode(', ', $missingFilesEn)
        );

        $allMismatches = [];

        foreach ($enFiles as $enFile) {
            $fileName = basename($enFile);
            $neFile = $nePath.'/'.$fileName;

            $enArray = include $enFile;
            $neArray = include $neFile;

            $this->assertIsArray($enArray, "File {$fileName} in en must return an array");
            $this->assertIsArray($neArray, "File {$fileName} in ne must return an array");

            $enKeys = $this->flattenKeys($enArray);
            $neKeys = $this->flattenKeys($neArray);

            $missingInNe = array_diff($enKeys, $neKeys);
            $missingInEn = array_diff($neKeys, $enKeys);

            if (! empty($missingInNe)) {
                $allMismatches[] = "In {$fileName}, keys missing in 'ne': ".implode(', ', $missingInNe);
            }
            if (! empty($missingInEn)) {
                $allMismatches[] = "In {$fileName}, keys missing in 'en': ".implode(', ', $missingInEn);
            }
        }

        $this->assertEmpty(
            $allMismatches,
            "Translation keys do not match between en and ne:\n".implode("\n", $allMismatches)
        );
    }

    /**
     * @param  array<string, mixed>  $array
     * @return array<int, string>
     */
    private function flattenKeys(array $array, string $prefix = ''): array
    {
        $keys = [];
        foreach ($array as $key => $value) {
            $fullKey = $prefix === '' ? (string) $key : "{$prefix}.{$key}";
            if (is_array($value)) {
                $keys = array_merge($keys, $this->flattenKeys($value, $fullKey));
            } else {
                $keys[] = $fullKey;
            }
        }

        return $keys;
    }
}
