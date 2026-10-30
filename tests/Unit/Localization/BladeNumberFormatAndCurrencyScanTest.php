<?php

declare(strict_types=1);

namespace Tests\Unit\Localization;

use Tests\TestCase;

class BladeNumberFormatAndCurrencyScanTest extends TestCase
{
    /**
     * Test that no Blade view contains number_format( or literal 'Rs' before {{.
     */
    public function test_no_blade_view_contains_number_format_or_rs_before_curly_braces(): void
    {
        $viewsPath = resource_path('views');
        $this->assertDirectoryExists($viewsPath);

        $files = $this->getBladeFiles($viewsPath);
        $this->assertNotEmpty($files, 'Blade views directory must not be empty.');

        $numberFormatViolations = [];
        $rsCurlyViolations = [];

        foreach ($files as $file) {
            $content = file_get_contents($file);
            $lines = explode("\n", $content);

            foreach ($lines as $lineIndex => $line) {
                $lineNumber = $lineIndex + 1;
                $relPath = str_replace(base_path().DIRECTORY_SEPARATOR, '', $file);

                if (str_contains($line, 'number_format(')) {
                    $numberFormatViolations[] = "{$relPath}:{$lineNumber} => ".trim($line);
                }

                if (preg_match('/Rs\.?\s*\{\{/i', $line)) {
                    $rsCurlyViolations[] = "{$relPath}:{$lineNumber} => ".trim($line);
                }
            }
        }

        $this->assertEmpty(
            $numberFormatViolations,
            "Direct number_format() call found in Blade views (must use @npr or @qty):\n".implode("\n", $numberFormatViolations)
        );

        $this->assertEmpty(
            $rsCurlyViolations,
            "Literal 'Rs' before {{ found in Blade views (must use @npr):\n".implode("\n", $rsCurlyViolations)
        );
    }

    /**
     * @return array<int, string>
     */
    private function getBladeFiles(string $dir): array
    {
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS)
        );

        $files = [];
        /** @var \SplFileInfo $file */
        foreach ($iterator as $file) {
            if ($file->isFile() && str_ends_with($file->getFilename(), '.blade.php')) {
                $files[] = $file->getRealPath();
            }
        }

        return $files;
    }
}

