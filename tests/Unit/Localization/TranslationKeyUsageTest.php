<?php

declare(strict_types=1);

namespace Tests\Unit\Localization;

use Tests\TestCase;

final class TranslationKeyUsageTest extends TestCase
{
    /** @var array<string, string> */
    private const ARRAY_MARKER = ['__array__' => '__array__'];

    public function test_every_static_translation_key_exists_in_both_locales(): void
    {
        $en = $this->localeKeys('en');
        $ne = $this->localeKeys('ne');
        $usages = $this->staticUsages();

        $this->assertNotEmpty($usages, 'The translation-key scan must find at least one key.');

        $violations = [];
        foreach ($usages as $key => $files) {
            if (str_ends_with($key, '.')) {
                continue;
            }

            if (! array_key_exists($key, $en)) {
                $violations[] = "Missing in lang/en: {$key} (".implode(', ', $files).')';
            } elseif ($en[$key] === self::ARRAY_MARKER) {
                $violations[] = "Resolves to an array in lang/en: {$key} (".implode(', ', $files).')';
            }

            if (! array_key_exists($key, $ne)) {
                $violations[] = "Missing in lang/ne: {$key} (".implode(', ', $files).')';
            } elseif ($ne[$key] === self::ARRAY_MARKER) {
                $violations[] = "Resolves to an array in lang/ne: {$key} (".implode(', ', $files).')';
            }
        }

        $this->assertSame([], $violations, "Translation keys used in code must exist in both locales:\n".implode("\n", $violations));
    }

    public function test_every_dynamic_translation_prefix_has_values_in_both_locales(): void
    {
        $en = $this->localeKeys('en');
        $ne = $this->localeKeys('ne');
        $prefixes = $this->dynamicPrefixes();

        $this->assertNotEmpty($prefixes, 'The dynamic translation scan must find at least one prefix.');

        $violations = [];
        foreach ($prefixes as $prefix => $files) {
            foreach (['en' => $en, 'ne' => $ne] as $locale => $keys) {
                $hasValue = false;
                foreach (array_keys($keys) as $key) {
                    if (str_starts_with($key, $prefix) && $keys[$key] !== self::ARRAY_MARKER) {
                        $hasValue = true;
                        break;
                    }
                }

                if (! $hasValue) {
                    $violations[] = "Dynamic prefix '{$prefix}' has no values in lang/{$locale} (".implode(', ', $files).')';
                }
            }
        }

        $this->assertSame([], $violations, "Dynamic translation prefixes must resolve in both locales:\n".implode("\n", $violations));
    }

    /**
     * @return array<string, string>
     */
    private function localeKeys(string $locale): array
    {
        $path = base_path('lang/'.$locale);
        $keys = [];

        foreach (glob($path.'/*.php') ?: [] as $file) {
            $group = basename($file, '.php');
            $contents = require $file;
            $this->assertIsArray($contents, "lang/{$locale}/{$group}.php must return an array");

            foreach ($this->flatten($contents, $group) as $key => $value) {
                $keys[$key] = $value;
            }
        }

        foreach (glob($path.'/*.json') ?: [] as $file) {
            $contents = json_decode((string) file_get_contents($file), true);
            $this->assertIsArray($contents, "lang/{$locale}/".basename($file).' must be a JSON object');

            foreach ($contents as $key => $value) {
                $keys[(string) $key] = is_array($value) ? self::ARRAY_MARKER : (string) $value;
            }
        }

        return $keys;
    }

    /**
     * @param  array<string, mixed>  $array
     * @return array<string, string>
     */
    private function flatten(array $array, string $prefix): array
    {
        $keys = [];
        foreach ($array as $key => $value) {
            $fullKey = $prefix.'.'.$key;
            if (is_array($value)) {
                $keys[$fullKey] = '__array__';
                foreach ($this->flatten($value, $fullKey) as $nestedKey => $nestedValue) {
                    $keys[$nestedKey] = $nestedValue;
                }
            } else {
                $keys[$fullKey] = (string) $value;
            }
        }

        return $keys;
    }

    /**
     * @return array<string, list<string>>
     */
    private function staticUsages(): array
    {
        $patterns = [
            '/(?:__|trans|trans_choice|Lang::get|Lang::choice)\(\s*([\'"])([A-Za-z0-9_.\-]+)\1/',
            '/@lang\(\s*([\'"])([A-Za-z0-9_.\-]+)\1/',
            '/@choice\(\s*([\'"])([A-Za-z0-9_.\-]+)\1/',
        ];

        $usages = [];
        foreach ($this->sourceFiles() as $file) {
            $contents = (string) file_get_contents($file);
            $relative = str_replace('\\', '/', substr($file, strlen(base_path()) + 1));

            foreach ($patterns as $pattern) {
                preg_match_all($pattern, $contents, $matches);
                foreach ($matches[2] ?? [] as $key) {
                    if (str_ends_with($key, '.')) {
                        continue;
                    }
                    $usages[$key][] = $relative;
                }
            }
        }

        return array_map(static fn (array $files): array => array_values(array_unique($files)), $usages);
    }

    /**
     * @return array<string, list<string>>
     */
    private function dynamicPrefixes(): array
    {
        $patterns = [
            '/(?:__|trans)\(\s*([\'"])([A-Za-z0-9_.\-]+\.)\1\s*\./',
        ];

        $prefixes = [];
        foreach ($this->sourceFiles() as $file) {
            $contents = (string) file_get_contents($file);
            $relative = str_replace('\\', '/', substr($file, strlen(base_path()) + 1));

            foreach ($patterns as $pattern) {
                preg_match_all($pattern, $contents, $matches);
                foreach ($matches[2] ?? [] as $prefix) {
                    $prefixes[$prefix][] = $relative;
                }
            }
        }

        return array_map(static fn (array $files): array => array_values(array_unique($files)), $prefixes);
    }

    /**
     * @return list<string>
     */
    private function sourceFiles(): array
    {
        $files = [];
        foreach ([app_path(), resource_path('views')] as $root) {
            $iterator = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS)
            );

            foreach ($iterator as $file) {
                if ($file->isFile() && (str_ends_with($file->getFilename(), '.php') || str_ends_with($file->getFilename(), '.blade.php'))) {
                    $files[] = $file->getPathname();
                }
            }
        }

        return $files;
    }
}
