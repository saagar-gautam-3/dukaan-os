<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Customer;
use App\Models\Shop;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Customer>
 */
class CustomerFactory extends Factory
{
    protected $model = Customer::class;

    public function definition(): array
    {
        return [
            'shop_id' => Shop::factory(),
            'name' => $this->faker->name(),
            'phone' => '98'.$this->faker->numerify('########'),
            'email' => $this->faker->optional()->safeEmail(),
            'address' => $this->faker->optional()->address(),
            'pan_no' => null,
        ];
    }
}
