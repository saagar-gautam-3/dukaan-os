<?php

declare(strict_types=1);

namespace Tests\Feature\Auth;

use App\Models\Customer;
use App\Models\Payment;
use App\Models\Shop;
use App\Models\ShopUser;
use App\Models\User;
use App\Services\RbacService;
use App\Tenancy\TenantContext;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

class NamedRateLimitTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        TenantContext::reset();
        parent::tearDown();
    }

    public function test_named_security_limiters_return_429_after_the_configured_limit(): void
    {
        $shop = Shop::factory()->create();
        TenantContext::setShop($shop);
        $roles = app(RbacService::class)->createDefaultRolesForShop($shop);
        $user = User::factory()->create();
        ShopUser::factory()->create([
            'shop_id' => $shop->id,
            'user_id' => $user->id,
            'role_id' => $roles['Shop Owner']->id,
            'status' => 'active',
        ]);
        $customer = Customer::factory()->create(['shop_id' => $shop->id]);
        $payment = Payment::factory()->qrPending()->create(['shop_id' => $shop->id]);

        $cases = [
            ['register', 'POST', '/register', false],
            ['login', 'POST', '/login', false],
            ['password-reset', 'POST', '/forgot-password', false],
            ['password-reset', 'POST', '/reset-password', false],
            ['post-sale', 'POST', route('sales.store'), true],
            ['payment', 'POST', route('payments.verify', $payment), true],
            ['collect', 'POST', route('udhar.collect.store', $customer), true],
            ['remind', 'POST', route('udhar.remind', $customer), true],
        ];

        foreach ($cases as [$name, $method, $url, $authenticated]) {
            RateLimiter::for($name, fn (Request $request) => Limit::perMinute(1)->by('f2-'.$name.'-'.$url));

            $firstRequest = $this->withServerVariables(['REMOTE_ADDR' => '198.51.100.77']);
            if ($authenticated) {
                $firstRequest->actingAs($user)->withSession(['current_shop_id' => $shop->id]);
            }
            $firstRequest->call($method, $url);

            $secondRequest = $this->withServerVariables(['REMOTE_ADDR' => '198.51.100.77']);
            if ($authenticated) {
                $secondRequest->actingAs($user)->withSession(['current_shop_id' => $shop->id]);
            }
            $secondRequest->call($method, $url)->assertStatus(429, "Limiter {$name} did not reject the second request.");
        }
    }
}
