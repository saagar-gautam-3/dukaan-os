<?php

declare(strict_types=1);

namespace Tests\Unit\Localization;

use Illuminate\Support\Facades\Blade;
use Tests\TestCase;

final class BladeHardcodedEnglishStringsTest extends TestCase
{
    public function test_blade_visible_text_is_localized_or_documented_in_the_allowlist(): void
    {
        $viewsPath = resource_path('views');
        $this->assertDirectoryExists($viewsPath);

        $allowlist = require base_path('tests/Support/blade-visible-text-allowlist.php');
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($viewsPath, \FilesystemIterator::SKIP_DOTS)
        );
        $violations = [];
        $scannedFiles = 0;

        foreach ($iterator as $file) {
            if (! $file->isFile() || ! str_ends_with($file->getFilename(), '.blade.php')) {
                continue;
            }

            $scannedFiles++;
            $path = $file->getRealPath();
            $relativePath = str_replace('\\', '/', substr($path, strlen(base_path()) + 1));

            foreach ($this->visibleTextFragments((string) file_get_contents($path)) as $fragment) {
                if (! preg_match('/[A-Za-z]/', $fragment) || isset($allowlist[$relativePath][$fragment])) {
                    continue;
                }

                $violations[] = "{$relativePath}: '{$fragment}'";
            }
        }

        $this->assertGreaterThan(0, $scannedFiles, 'The Blade scan must inspect at least one view.');
        $this->assertEmpty(
            $violations,
            "Unlocalized English visible text found; move it to lang/ne and lang/en:\n".implode("\n", array_unique($violations))
        );
    }

    public function test_every_blade_visible_text_allowlist_entry_has_a_reason(): void
    {
        $allowlist = require base_path('tests/Support/blade-visible-text-allowlist.php');
        $entryCount = array_sum(array_map('count', $allowlist));

        $this->assertGreaterThan(0, $entryCount, 'The allowlist reason test must inspect at least one justified token.');

        foreach ($allowlist as $path => $fragments) {
            $this->assertIsArray($fragments, "Allowlist entries for {$path} must map exact text to reasons.");

            foreach ($fragments as $fragment => $reason) {
                $this->assertNotSame('', trim($reason), "Allowlist fragment '{$fragment}' needs a reason.");
            }
        }
    }

    /** @return list<string> */
    private function visibleTextFragments(string $blade): array
    {
        $blade = preg_replace('/\{\{--.*?--\}\}/s', ' ', $blade) ?? $blade;
        $blade = preg_replace('/<script\b[^>]*>.*?<\/script\s*>/is', ' ', $blade) ?? $blade;
        $blade = preg_replace('/<style\b[^>]*>.*?<\/style\s*>/is', ' ', $blade) ?? $blade;

        $fragments = [];
        preg_match_all('/\b(?:placeholder|title|aria-label|alt)\s*=\s*(["\'])(.*?)\1/is', $blade, $attributeMatches);
        foreach ($attributeMatches[2] ?? [] as $attribute) {
            if (! preg_match('/\{\{|\{!!|<\?php|\$[A-Za-z_]/', $attribute)) {
                $fragments[] = trim(html_entity_decode($attribute, ENT_QUOTES | ENT_HTML5, 'UTF-8'));
            }
        }

        preg_match_all('/<input\b([^>]*)>/is', $blade, $inputMatches);
        foreach ($inputMatches[1] ?? [] as $attributes) {
            if (preg_match('/\btype\s*=\s*(["\'])hidden\1/i', $attributes)) {
                continue;
            }

            if (preg_match('/\bvalue\s*=\s*(["\'])(.*?)\1/is', $attributes, $valueMatch)
                && ! preg_match('/\{\{|\{!!|<\?php|\$[A-Za-z_]/', $valueMatch[2])) {
                $fragments[] = trim(html_entity_decode($valueMatch[2], ENT_QUOTES | ENT_HTML5, 'UTF-8'));
            }
        }

        $compiled = Blade::compileString($blade);
        $compiled = preg_replace('/<\?php.*?\?>/s', ' ', $compiled) ?? $compiled;
        preg_match_all('/>([^<>]+)</s', $compiled, $matches);

        return array_values(array_filter(array_merge($fragments, array_map(
            static fn (string $fragment): string => trim(html_entity_decode($fragment, ENT_QUOTES | ENT_HTML5, 'UTF-8')),
            $matches[1] ?? [],
        )), static fn (string $fragment): bool => $fragment !== ''));
    }
}
