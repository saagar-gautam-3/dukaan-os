<?php

declare(strict_types=1);

namespace Tests\Feature\Tenancy;

use App\Actions\Udhar\SendReminder;
use App\Jobs\SendReminderJob;
use App\Models\Customer;
use App\Models\Shop;
use App\Tenancy\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Queue\Events\JobProcessed;
use Illuminate\Queue\Events\JobProcessing;
use Illuminate\Queue\Jobs\SyncJob;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class QueueTenantIsolationTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        TenantContext::reset();

        parent::tearDown();
    }

    public function test_job_leaves_tenant_context_cleared(): void
    {
        $shop = Shop::factory()->create();
        $customer = Customer::factory()->create(['shop_id' => $shop->id]);

        TenantContext::reset();
        $this->assertFalse(TenantContext::has());

        $job = new SendReminderJob($shop->id, $customer->id);
        $job->handle(app(SendReminder::class));

        $this->assertFalse(TenantContext::has(), 'Job execution must leave TenantContext cleared.');
    }

    public function test_shop_b_job_after_shop_a_job_cannot_see_shop_a_data(): void
    {
        $shopA = Shop::factory()->create(['name' => 'Shop A']);
        $shopB = Shop::factory()->create(['name' => 'Shop B']);

        $customerA = Customer::factory()->create(['shop_id' => $shopA->id, 'name' => 'Customer of Shop A']);
        $customerB = Customer::factory()->create(['shop_id' => $shopB->id, 'name' => 'Customer of Shop B']);

        TenantContext::reset();

        // Run Job for Shop A
        $jobA = new SendReminderJob($shopA->id, $customerA->id);
        $jobA->handle(app(SendReminder::class));

        $this->assertFalse(TenantContext::has(), 'Context must be cleared after Job A.');

        // Now run a job or callback simulating Job B
        TenantContext::runAs($shopB, function () use ($customerA, $customerB, $shopB) {
            $this->assertSame($shopB->id, TenantContext::shopId());

            // Shop B cannot see Customer A due to BelongsToShop global scope
            $foundCustomerA = Customer::find($customerA->id);
            $this->assertNull($foundCustomerA, 'Shop B must not see Shop A customer.');

            $foundCustomerB = Customer::find($customerB->id);
            $this->assertNotNull($foundCustomerB, 'Shop B must see its own customer.');
            $this->assertSame($customerB->id, $foundCustomerB->id);
        });

        $this->assertFalse(TenantContext::has(), 'Context must be cleared after Job B.');
    }

    public function test_queue_listeners_reset_context_before_and_after_every_job(): void
    {
        $shopA = Shop::factory()->create(['name' => 'Shop A']);
        $shopB = Shop::factory()->create(['name' => 'Shop B']);

        // Suppose ambient context had Shop A set before worker runs next job
        TenantContext::setShop($shopA);
        $this->assertTrue(TenantContext::has());

        // Fire queue JobProcessing event
        Event::dispatch(new JobProcessing('database', new SyncJob(app(), 'payload', 'connection', 'default')));
        $this->assertFalse(TenantContext::has(), 'Queue::before must reset TenantContext before job starts.');

        // Simulate job setting context
        TenantContext::setShop($shopB);
        $this->assertTrue(TenantContext::has());

        // Fire queue JobProcessed event
        Event::dispatch(new JobProcessed('database', new SyncJob(app(), 'payload', 'connection', 'default')));
        $this->assertFalse(TenantContext::has(), 'Queue::after must reset TenantContext after job finishes.');
    }
}
