<?php

declare(strict_types=1);

namespace Tests\Unit\Localization;

use PHPUnit\Framework\AssertionFailedError;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class NoMojibakeTest extends TestCase
{
    private const MOJIBAKE_PATTERN = '/Ã.|Â.|â€|à¤|à¥|Ø|Ù/u';

    // Non-ASCII symbols that are legitimately used in Nepali copy without
    // Devanagari (e.g. middle dot in abbreviations, multiplication sign).
    private const ALLOWED_NON_DEVANAGARI = ["\u{00B7}", "\u{00D7}"];

    public function test_lang_and_views_have_no_mojibake_or_invalid_utf8(): void
    {
        $directories = [
            base_path('lang'),
            base_path('resources/views'),
        ];

        $failures = [];

        foreach ($directories as $directory) {
            foreach ($this->allFiles($directory) as $file) {
                $contents = file_get_contents($file);
                if ($contents === false) {
                    continue;
                }

                if (! mb_check_encoding($contents, 'UTF-8')) {
                    $failures[] = "Invalid UTF-8: {$file}";

                    continue;
                }

                if (preg_match(self::MOJIBAKE_PATTERN, $contents) === 1) {
                    $failures[] = "Mojibake marker found: {$file}";
                }
            }
        }

        $this->assertSame([], $failures, implode("\n", $failures));
    }

    public function test_lang_ne_strings_contain_devanagari(): void
    {
        $failures = [];

        foreach ($this->allFiles(base_path('lang/ne')) as $file) {
            if (! str_ends_with($file, '.php')) {
                continue;
            }

            $array = include $file;
            if (! is_array($array)) {
                continue;
            }

            $this->collectStringFailures($array, $file, '', $failures);
        }

        $this->assertSame([], $failures, implode("\n", $failures));
    }

    public function test_detector_fails_on_corrupted_sample(): void
    {
        $this->expectException(AssertionFailedError::class);

        $corrupted = 'à¤ªà¤¸à¤²à¤•à¥‹ à¤¸à¤¨à¥à¤¦à¤°à¥à¤­ à¤›à¥ˆà¤¨à¥¤';

        $this->assertNoMojibakeMarkers($corrupted, 'corrupted sample');
    }

    public function test_renders_shop_deactivated_403_in_nepali(): void
    {
        app()->setLocale('ne');

        $exception = new HttpException(
            403,
            __('errors.shop_deactivated')
        );

        $view = $this->view('errors.403', ['exception' => $exception]);
        $html = (string) $view;

        $this->assertStringContainsString('यो पसल खाता निलम्बित छ। सहायताका लागि सम्पर्क गर्नुहोस्।', $html);
        $this->assertNoMojibakeMarkers($html, 'rendered 403 page');
    }

    /**
     * @param  array<string, mixed>  $array
     * @param  array<int, string>  $failures
     */
    private function collectStringFailures(array $array, string $file, string $prefix, array &$failures): void
    {
        foreach ($array as $key => $value) {
            $fullKey = $prefix === '' ? (string) $key : $prefix.'.'.$key;

            if (is_array($value)) {
                $this->collectStringFailures($value, $file, $fullKey, $failures);

                continue;
            }

            if (! is_string($value)) {
                continue;
            }

            if (preg_match('/[^\x00-\x7F]/u', $value) !== 1) {
                continue;
            }

            $nonAscii = [];
            preg_match_all('/[^\x00-\x7F]/u', $value, $nonAscii);

            $allAllowed = $nonAscii[0] !== [];
            foreach ($nonAscii[0] as $char) {
                if (! in_array($char, self::ALLOWED_NON_DEVANAGARI, true)) {
                    $allAllowed = false;
                    break;
                }
            }

            if ($allAllowed) {
                continue;
            }

            if (preg_match('/[\x{0900}-\x{097F}]/u', $value) !== 1) {
                $failures[] = "Non-Devanagari Nepali string in {$file} [{$fullKey}]: {$value}";
            }
        }
    }

    private function assertNoMojibakeMarkers(string $contents, string $label): void
    {
        $this->assertTrue(mb_check_encoding($contents, 'UTF-8'), "Invalid UTF-8 in {$label}");
        $this->assertDoesNotMatchRegularExpression(self::MOJIBAKE_PATTERN, $contents, "Mojibake marker found in {$label}");
    }

    /**
     * @return array<int, string>
     */
    private function allFiles(string $directory): array
    {
        if (! is_dir($directory)) {
            return [];
        }

        $files = [];
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($directory, \FilesystemIterator::SKIP_DOTS)
        );

        foreach ($iterator as $file) {
            if ($file->isFile()) {
                $files[] = $file->getPathname();
            }
        }

        return $files;
    }
}
