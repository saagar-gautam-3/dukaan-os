<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\LedgerEntryType;
use App\Models\Customer;
use App\Models\CustomerLedgerEntry;
use App\Models\Shop;
use App\Tenancy\TenantContext;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CustomerLedgerEntry>
 */
final class CustomerLedgerEntryFactory extends Factory
{
    protected $model = CustomerLedgerEntry::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $shop = Shop::factory()->create();
        $customer = TenantContext::runAs($shop, fn () => Customer::factory()->create(['shop_id' => $shop->id]));

        return [
            'shop_id' => $shop->id,
            'customer_id' => $customer->id,
            'entry_type' => LedgerEntryType::ManualDebit->value,
            'reference_type' => null,
            'reference_id' => null,
            'debit' => '100.00',
            'credit' => '0.00',
            'notes' => 'Test debit entry',
        ];
    }

    public function debit(string $amount = '100.00'): self
    {
        return $this->state(fn () => [
            'entry_type' => LedgerEntryType::ManualDebit->value,
            'debit' => $amount,
            'credit' => '0.00',
        ]);
    }

    public function credit(string $amount = '50.00'): self
    {
        return $this->state(fn () => [
            'entry_type' => LedgerEntryType::ManualCredit->value,
            'debit' => '0.00',
            'credit' => $amount,
        ]);
    }
}
