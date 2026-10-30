<?php

declare(strict_types=1);

namespace Tests\Feature\Sales;

use App\Models\InvoiceSequence;
use App\Models\Payment;
use App\Models\Shop;
use App\Services\DocumentSequenceService;
use App\Support\FiscalYear;
use App\Tenancy\TenantContext;
use Symfony\Component\Process\Process;
use Tests\TestCase;

class DocumentSequenceConcurrencyTest extends TestCase
{
    /** @var array<int, Shop> */
    protected array $testShops = [];

    protected function tearDown(): void
    {
        TenantContext::reset();

        foreach ($this->testShops as $shop) {
            // Delete the shop — the DB-level FK cascade removes all child
            // tenant rows (payments, invoice_sequences, etc.) at the database
            // layer, bypassing Eloquent model events and immutability triggers.
            $shop->delete();
        }

        parent::tearDown();
    }

    public function test_concurrent_first_sale_of_fiscal_year_generates_gapless_unique_numbers(): void
    {
        $shop = Shop::factory()->create();
        $this->testShops[] = $shop;
        $fiscalYear = '2099-00'; // distinct fiscal year with no prior records

        // Assert no sequence exists prior to test
        $this->assertFalse(
            InvoiceSequence::where('shop_id', $shop->id)
                ->where('fiscal_year', $fiscalYear)
                ->where('prefix', 'INV')
                ->exists()
        );

        $processes = $this->spawnSequenceWorkers($shop->id, 'INV', $fiscalYear, 5, 3);
        $numbers = $this->collectNumbersFromWorkers($processes);

        // 3 workers * 5 numbers = 15 total numbers
        $this->assertCount(15, $numbers);
        $this->assertCount(15, array_unique($numbers), 'Every generated invoice number must be unique.');

        // Sort and check gapless sequence
        sort($numbers);
        for ($i = 1; $i <= 15; $i++) {
            $expected = sprintf('INV-%s-%06d', $fiscalYear, $i);
            $this->assertSame($expected, $numbers[$i - 1]);
        }
    }

    public function test_concurrent_payment_number_generation_produces_unique_numbers(): void
    {
        $shop = Shop::factory()->create();
        $this->testShops[] = $shop;
        $fiscalYear = FiscalYear::current();

        $processes = $this->spawnSequenceWorkers($shop->id, 'PAY', $fiscalYear, 5, 3);
        $numbers = $this->collectNumbersFromWorkers($processes);

        $this->assertCount(15, $numbers);
        $this->assertCount(15, array_unique($numbers), 'Every generated payment number must be unique.');

        sort($numbers);
        for ($i = 1; $i <= 15; $i++) {
            $expected = sprintf('PAY-%s-%06d', $fiscalYear, $i);
            $this->assertSame($expected, $numbers[$i - 1]);
        }
    }

    public function test_concurrent_return_number_generation_produces_unique_numbers(): void
    {
        $shop = Shop::factory()->create();
        $this->testShops[] = $shop;
        $fiscalYear = FiscalYear::current();

        $processes = $this->spawnSequenceWorkers($shop->id, 'RET', $fiscalYear, 5, 3);
        $numbers = $this->collectNumbersFromWorkers($processes);

        $this->assertCount(15, $numbers);
        $this->assertCount(15, array_unique($numbers), 'Every generated return number must be unique.');

        sort($numbers);
        for ($i = 1; $i <= 15; $i++) {
            $expected = sprintf('RET-%s-%06d', $fiscalYear, $i);
            $this->assertSame($expected, $numbers[$i - 1]);
        }
    }

    public function test_payment_no_is_always_populated_and_not_null(): void
    {
        $shop = Shop::factory()->create();
        $this->testShops[] = $shop;
        $service = app(DocumentSequenceService::class);

        $paymentNo = $service->nextNumber($shop->id, 'PAY');
        $this->assertNotNull($paymentNo);
        $this->assertStringStartsWith('PAY-', $paymentNo);

        $payment = Payment::factory()->create([
            'shop_id' => $shop->id,
            'payment_no' => $paymentNo,
        ]);

        $this->assertNotNull($payment->payment_no);
        $this->assertSame($paymentNo, $payment->payment_no);
    }

    /**
     * @return array<int, Process>
     */
    private function spawnSequenceWorkers(int $shopId, string $prefix, string $fiscalYear, int $countPerWorker, int $workers): array
    {
        $phpBinary = PHP_BINARY;
        $artisan = base_path('artisan');
        $env = [
            'APP_ENV' => 'testing',
            'DB_DATABASE' => config('database.connections.mysql.database'),
        ];

        $processes = [];
        for ($w = 0; $w < $workers; $w++) {
            $cmd = [
                $phpBinary,
                $artisan,
                'test:sequence-worker',
                (string) $shopId,
                $prefix,
                $fiscalYear,
                "--count={$countPerWorker}",
            ];

            $process = new Process($cmd, null, $env);
            $process->start();
            $processes[] = $process;
        }

        return $processes;
    }

    /**
     * @param  array<int, Process>  $processes
     * @return array<int, string>
     */
    private function collectNumbersFromWorkers(array $processes): array
    {
        $numbers = [];

        foreach ($processes as $process) {
            $process->wait();
            $this->assertSame(0, $process->getExitCode(), 'Worker failed: '.$process->getOutput().' | '.$process->getErrorOutput());

            $output = $process->getOutput();
            foreach (explode("\n", $output) as $line) {
                $line = trim($line);
                if (str_starts_with($line, 'NUMBER:')) {
                    $numbers[] = substr($line, 7);
                }
            }
        }

        return $numbers;
    }
}
