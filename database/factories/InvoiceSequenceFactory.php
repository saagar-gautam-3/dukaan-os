<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\InvoiceSequence;
use App\Models\Shop;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<InvoiceSequence>
 */
class InvoiceSequenceFactory extends Factory
{
    protected $model = InvoiceSequence::class;

    public function definition(): array
    {
        return [
            'shop_id' => Shop::factory(),
            'fiscal_year' => '2081-82',
            'prefix' => 'INV',
            'last_number' => 0,
        ];
    }
}
