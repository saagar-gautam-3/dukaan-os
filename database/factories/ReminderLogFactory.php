<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Customer;
use App\Models\ReminderLog;
use App\Models\Shop;
use App\Tenancy\TenantContext;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ReminderLog>
 */
final class ReminderLogFactory extends Factory
{
    protected $model = ReminderLog::class;

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
            'channel' => 'stub',
            'phone_masked' => '98XXXXXX10',
            'message_preview' => 'Test reminder preview',
            'status' => 'sent',
            'sent_by' => null,
        ];
    }
}
