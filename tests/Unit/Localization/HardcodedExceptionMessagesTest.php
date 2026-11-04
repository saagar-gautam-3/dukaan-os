<?php

declare(strict_types=1);

namespace Tests\Unit\Localization;

use Tests\TestCase;

final class HardcodedExceptionMessagesTest extends TestCase
{
    public function test_exception_and_abort_messages_are_translated_or_documented_in_the_allowlist(): void
    {
        $appPath = app_path();
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($appPath, \FilesystemIterator::SKIP_DOTS)
        );
        $allowlist = require base_path('tests/Support/hardcoded-exception-message-allowlist.php');
        $violations = [];
        $scannedFiles = 0;

        foreach ($iterator as $file) {
            if (! $file->isFile() || ! str_ends_with($file->getFilename(), '.php')) {
                continue;
            }

            $scannedFiles++;
            $path = $file->getRealPath();
            $relativePath = str_replace('\\', '/', substr($path, strlen(base_path()) + 1));
            $source = (string) file_get_contents($path);
            preg_match_all(
                '/(?:throw\s+new\s+[\\\\A-Za-z_][\\\\A-Za-z0-9_]*|new\s+self)\s*\(\s*([\'\"])(.*?)\1|\babort\s*\(\s*\d+\s*,\s*([\'\"])(.*?)\3/s',
                $source,
                $matches,
                PREG_SET_ORDER
            );

            foreach ($matches as $match) {
                $message = $match[2] !== '' ? $match[2] : ($match[4] ?? '');
                if (! preg_match('/[A-Za-z]/', $message) || isset($allowlist[$relativePath][$message])) {
                    continue;
                }

                $violations[] = "{$relativePath}: '{$message}'";
            }
        }

        $this->assertGreaterThan(0, $scannedFiles, 'The exception scan must inspect app PHP files.');
        $this->assertEmpty(
            $violations,
            "Hard-coded English exception or abort message found; use a translation key:\n".implode("\n", array_unique($violations))
        );
    }

    public function test_every_exception_message_allowlist_entry_has_a_reason(): void
    {
        $allowlist = require base_path('tests/Support/hardcoded-exception-message-allowlist.php');
        $entryCount = array_sum(array_map('count', $allowlist));

        $this->assertGreaterThan(0, $entryCount);

        foreach ($allowlist as $path => $messages) {
            foreach ($messages as $message => $reason) {
                $this->assertNotSame('', trim($reason), "Allowlist message '{$message}' in {$path} needs a reason.");
            }
        }
    }
}
