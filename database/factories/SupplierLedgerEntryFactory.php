<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\LedgerEntryType;
use App\Models\Shop;
use App\Models\Supplier;
use App\Models\SupplierLedgerEntry;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SupplierLedgerEntry>
 */
final class SupplierLedgerEntryFactory extends Factory
{
    protected $model = SupplierLedgerEntry::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'shop_id' => Shop::factory(),
            'supplier_id' => Supplier::factory(),
            'entry_type' => LedgerEntryType::PurchaseDebit->value,
            'reference_type' => null,
            'reference_id' => null,
            'debit' => '500.00',
            'credit' => '0.00',
            'notes' => 'Test supplier debit entry',
        ];
    }

    public function debit(string $amount = '500.00'): self
    {
        return $this->state(fn () => [
            'entry_type' => LedgerEntryType::PurchaseDebit->value,
            'debit' => $amount,
            'credit' => '0.00',
        ]);
    }

    public function credit(string $amount = '200.00'): self
    {
        return $this->state(fn () => [
            'entry_type' => LedgerEntryType::SupplierPaymentCredit->value,
            'debit' => '0.00',
            'credit' => $amount,
        ]);
    }
}
