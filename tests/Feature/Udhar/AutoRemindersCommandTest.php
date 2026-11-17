<?php

declare(strict_types=1);

namespace Tests\Feature\Udhar;

use App\Jobs\SendReminderJob;
use App\Models\Customer;
use App\Models\Sale;
use App\Models\Shop;
use App\Models\ShopUser;
use App\Models\User;
use App\Services\RbacService;
use App\Tenancy\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

final class AutoRemindersCommandTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        TenantContext::reset();
        parent::tearDown();
    }

    private function createShopWithOwner(bool $autoReminders): array
    {
        $shop = Shop::factory()->create([
            'timezone' => 'Asia/Kathmandu',
            'settings' => ['auto_reminders_enabled' => $autoReminders],
        ]);
        TenantContext::setShop($shop);
        $rbac = app(RbacService::class);
        $roles = $rbac->createDefaultRolesForShop($shop);
        $user = User::factory()->create();
        ShopUser::create([
            'shop_id' => $shop->id,
            'user_id' => $user->id,
            'role_id' => $roles['Shop Owner']->id,
            'status' => 'active',
        ]);

        return [$shop, $user];
    }

    public function test_shop_without_setting_is_skipped(): void
    {
        [$shop] = $this->createShopWithOwner(false);

        $exit = $this->artisan('udhar:send-due-reminders', ['--shop' => $shop->id])->run();

        $this->assertSame(0, $exit);
    }

    public function test_shop_with_setting_true_dispatches_once_per_customer(): void
    {
        Queue::fake();

        [$shop] = $this->createShopWithOwner(true);

        $customer = Customer::factory()->create(['shop_id' => $shop->id]);
        Sale::factory()->create([
            'shop_id' => $shop->id,
            'customer_id' => $customer->id,
            'status' => 'posted',
            'grand_total' => '100.00',
            'paid_total' => '0.00',
            'due_date' => now()->subDay()->toDateString(),
        ]);

        $this->artisan('udhar:send-due-reminders', ['--shop' => $shop->id])->assertExitCode(0);

        Queue::assertPushed(SendReminderJob::class, 1);
    }

    public function test_dry_run_changes_nothing(): void
    {
        Queue::fake();

        [$shop] = $this->createShopWithOwner(true);

        $customer = Customer::factory()->create(['shop_id' => $shop->id]);
        Sale::factory()->create([
            'shop_id' => $shop->id,
            'customer_id' => $customer->id,
            'status' => 'posted',
            'grand_total' => '100.00',
            'paid_total' => '0.00',
            'due_date' => now()->subDay()->toDateString(),
        ]);

        $this->artisan('udhar:send-due-reminders', ['--shop' => $shop->id, '--dry-run' => true])->assertExitCode(0);

        Queue::assertNothingPushed();
    }

    public function test_schedule_does_not_list_command(): void
    {
        $this->artisan('schedule:list')
            ->doesntExpectOutputToContain('udhar:send-due-reminders')
            ->assertExitCode(0);
    }
}
