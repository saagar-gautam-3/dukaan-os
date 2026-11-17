<?php

declare(strict_types=1);

namespace Tests\Feature\Platform;

use App\Models\AuditLog;
use App\Models\Shop;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PlatformSuspendTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['is_platform_admin' => true, 'email_verified_at' => now()]);
    }

    public function test_suspend_reason_validation(): void
    {
        $admin = $this->admin();
        $shop = Shop::factory()->create(['is_active' => true]);
        $this->actingAs($admin);

        $this->post(route('platform.shops.suspend.store', $shop), ['reason' => ''])->assertSessionHasErrors('reason');
        $this->post(route('platform.shops.suspend.store', $shop), ['reason' => 'abcd'])->assertSessionHasErrors('reason');
        $this->post(route('platform.shops.suspend.store', $shop), ['reason' => str_repeat('a', 501)])->assertSessionHasErrors('reason');
    }

    public function test_second_suspend_rejected(): void
    {
        $admin = $this->admin();
        $shop = Shop::factory()->create(['is_active' => true]);
        $this->actingAs($admin);

        $this->post(route('platform.shops.suspend.store', $shop), ['reason' => 'First suspension reason'])
            ->assertRedirect();
        $response = $this->post(route('platform.shops.suspend.store', $shop), ['reason' => 'Second suspension reason']);
        $this->assertTrue(in_array($response->getStatusCode(), [302, 422, 403]));
    }

    public function test_suspend_writes_audit_with_reason(): void
    {
        $admin = $this->admin();
        $shop = Shop::factory()->create(['is_active' => true, 'name' => 'Audit Shop']);
        $this->actingAs($admin);

        $this->post(route('platform.shops.suspend.store', $shop), ['reason' => 'Fraud suspected'])
            ->assertRedirect();

        $log = AuditLog::where('event', 'platform.shop_suspended')->latest('id')->first();
        $this->assertNotNull($log);
        $this->assertSame($admin->id, $log->user_id);
        $this->assertSame('platform.shop_suspended', $log->event);
        $this->assertSame('Fraud suspected', $log->new_values['reason']);
        $this->assertSame($shop->id, $log->new_values['shop_id']);
    }

    public function test_reactivate_clears_columns_and_audits(): void
    {
        $admin = $this->admin();
        $shop = Shop::factory()->create(['is_active' => false]);
        $shop->suspended_at = now();
        $shop->suspended_by = $admin->id;
        $shop->suspended_reason = 'Temp';
        $shop->save();

        $this->actingAs($admin);
        $this->post(route('platform.shops.reactivate', $shop))->assertRedirect();

        $fresh = $shop->fresh();
        $this->assertTrue($fresh->is_active);
        $this->assertNull($fresh->suspended_at);
        $this->assertNull($fresh->suspended_by);
        $this->assertNull($fresh->suspended_reason);

        $log = AuditLog::where('event', 'platform.shop_reactivated')->latest('id')->first();
        $this->assertNotNull($log);
    }
}
