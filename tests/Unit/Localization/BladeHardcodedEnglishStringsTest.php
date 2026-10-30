<?php

declare(strict_types=1);

namespace Tests\Unit\Localization;

use Tests\TestCase;

class BladeHardcodedEnglishStringsTest extends TestCase
{
    /**
     * Known allowed non-localized tokens, brand names, technical identifiers,
     * acronyms, and boilerplate that legitimately appear in Blade files.
     *
     * @var array<int, string>
     */
    private const ALLOWLIST = [
        'Dukaan OS',
        'Dukaan',
        'OS',
        'NPR',
        'PAN',
        'VAT',
        'SKU',
        'POS',
        'QR',
        'CSV',
        'A4',
        '80mm',
        'Ref',
        'Ph',
        'ID',
        'CSS',
        'JS',
        'HTML',
        'N/A',
        'Max',
        'Rate',
        'Total',
        'Item',
        'Qty',
        'Bill',
        'Date',
        'Phone',
        'Customer',
        'Subtotal',
        'Discount',
        'Tax',
        'Paid',
        'Due',
    ];

    /**
     * Patterns of known user-visible hardcoded phrases that MUST NOT appear.
     *
     * @var array<int, string>
     */
    private const FORBIDDEN_PHRASES = [
        'Opening:',
        'Expected now:',
        'Expected:',
        'Total Value',
        'Paid via',
        'Cost Snapshot',
        'Payment History',
        'Sale Items',
        'Receiving Location',
        'Warehouse Code',
        'Immutable Posted Record',
        'Draft Purchase Order',
        'Delete Draft',
        'Cancel Draft',
        'Confirm & Post',
        'Total payable:',
        'No cash sessions recorded yet',
        'No expenses recorded yet',
        'No tracked stock found',
        'All Stock Levels Healthy',
        'Items below reorder level',
        'POS Billing',
        'Customer Khata',
        'Inventory Catalog',
        'Shop Outflow',
    ];

    public function test_views_do_not_contain_flagged_hardcoded_english_phrases(): void
    {
        $viewsPath = resource_path('views');
        $this->assertDirectoryExists($viewsPath);

        $files = $this->getBladeFiles($viewsPath);
        $violations = [];

        foreach ($files as $file) {
            $content = file_get_contents($file);
            $relPath = str_replace(base_path().DIRECTORY_SEPARATOR, '', $file);

            // Strip blade comments and script/style tags
            $stripped = preg_replace('/\{\{--.*?--\}\}/s', '', $content) ?? $content;
            $stripped = preg_replace('/<script\b[^>]*>(.*?)<\/script>/is', '', $stripped) ?? $stripped;
            $stripped = preg_replace('/<style\b[^>]*>(.*?)<\/style>/is', '', $stripped) ?? $stripped;

            foreach (self::FORBIDDEN_PHRASES as $phrase) {
                if (stripos($stripped, $phrase) !== false) {
                    $violations[] = "{$relPath} contains forbidden hardcoded English phrase: '{$phrase}'";
                }
            }
        }

        $this->assertEmpty(
            $violations,
            "Hardcoded user-visible English phrases found in Blade views (must move to lang files):\n".implode("\n", $violations)
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
