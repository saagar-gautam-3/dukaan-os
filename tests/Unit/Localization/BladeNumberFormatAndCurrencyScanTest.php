<?php

declare(strict_types=1);

namespace Tests\Unit\Localization;

use Tests\TestCase;

class BladeNumberFormatAndCurrencyScanTest extends TestCase
{
    public function test_application_and_views_have_no_float_money_formatting(): void
    {
        $allowlist = require base_path('tests/Support/float-money-scan-allowlist.php');
        $violations = [];
        $patterns = [
            'float cast' => '/\(\s*float\s*\)/i',
            'floatval' => '/\bfloatval\s*\(/i',
            'number_format' => '/\bnumber_format\s*\(/i',
        ];

        foreach ([app_path(), resource_path('views')] as $root) {
            $iterator = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS)
            );

            foreach ($iterator as $file) {
                if (! $file->isFile() || ! str_ends_with($file->getFilename(), '.php')) {
                    continue;
                }

                $relativePath = str_replace(base_path().DIRECTORY_SEPARATOR, '', $file->getRealPath());
                $lines = file($file->getRealPath(), FILE_IGNORE_NEW_LINES) ?: [];

                foreach ($lines as $index => $line) {
                    foreach ($patterns as $name => $pattern) {
                        if (! preg_match($pattern, $line)) {
                            continue;
                        }

                        $entry = $allowlist[$relativePath] ?? null;
                        if ($entry !== null && isset($entry['reason']) && in_array($name, $entry['patterns'] ?? [], true)) {
                            continue;
                        }

                        $violations[] = "{$relativePath}:".($index + 1)." [{$name}] ".trim($line);
                    }
                }
            }
        }

        $this->assertSame([], $violations, "Float money formatting is forbidden in app/ and resources/views/; use decimal strings and Money.\n".implode("\n", $violations));
    }

    public function test_blade_views_do_not_prefix_currency_interpolations_with_rs(): void
    {
        $viewsPath = resource_path('views');
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($viewsPath, \FilesystemIterator::SKIP_DOTS)
        );
        $violations = [];

        foreach ($iterator as $file) {
            if (! $file->isFile() || ! str_ends_with($file->getFilename(), '.blade.php')) {
                continue;
            }

            $relativePath = str_replace(base_path().DIRECTORY_SEPARATOR, '', $file->getRealPath());
            foreach (file($file->getRealPath(), FILE_IGNORE_NEW_LINES) ?: [] as $index => $line) {
                if (preg_match('/Rs\.?\s*\{\{/i', $line)) {
                    $violations[] = "{$relativePath}:".($index + 1).' '.trim($line);
                }
            }
        }

        $this->assertSame([], $violations, "Use @npr for formatted currency.\n".implode("\n", $violations));
    }
}
