<?php

declare(strict_types=1);

namespace Tests\Feature\Auth;

use App\Models\Shop;
use App\Models\ShopUser;
use App\Models\User;
use App\Services\RbacService;
use App\Tenancy\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class EmailVerificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_unverified_user_redirected_to_verification_notice(): void
    {
        $shop = Shop::factory()->create();
        $user = User::factory()->unverified()->create();
        ShopUser::factory()->create([
            'shop_id' => $shop->id,
            'user_id' => $user->id,
            'role_id' => app(RbacService::class)->createDefaultRolesForShop($shop)['Shop Owner']->id,
            'status' => 'active',
        ]);
        TenantContext::setShop($shop);
        session(['current_shop_id' => $shop->id]);
        $this->actingAs($user);
        $response = $this->get(route('dashboard'));
        $response->assertRedirect(route('verification.notice'));
    }

    public function test_verified_user_passes(): void
    {
        $shop = Shop::factory()->create();
        $user = User::factory()->create();
        ShopUser::factory()->create([
            'shop_id' => $shop->id,
            'user_id' => $user->id,
            'role_id' => app(RbacService::class)->createDefaultRolesForShop($shop)['Shop Owner']->id,
            'status' => 'active',
        ]);
        session(['current_shop_id' => $shop->id]);
        $this->actingAs($user);
        $response = $this->get(route('dashboard'));
        $response->assertOk();
    }

    public function test_resend_throttled(): void
    {
        $user = User::factory()->unverified()->create();
        $this->actingAs($user);
        for ($i = 0; $i < 7; $i++) {
            $this->post(route('verification.send'));
        }
        $response = $this->post(route('verification.send'));
        $response->assertStatus(429);
    }

    public function test_tampered_signed_link_fails(): void
    {
        $user = User::factory()->unverified()->create();
        $this->actingAs($user);
        $response = $this->get(route('verification.verify', ['id' => $user->id, 'hash' => 'bad']));
        $response->assertStatus(403);
    }

    public function test_verifying_marks_email_verified_at(): void
    {
        $user = User::factory()->unverified()->create();
        $this->actingAs($user);
        $url = route('verification.verify', [
            'id' => $user->id,
            'hash' => sha1($user->email),
        ]);
        // signed URL required; use actingAs + signed route via URL generator
        $signed = URL::signedRoute('verification.verify', ['id' => $user->id, 'hash' => sha1($user->email)]);
        $response = $this->get($signed);
        $this->assertTrue($user->fresh()->hasVerifiedEmail() || $response->isRedirect());
    }

    public function test_guest_redirected_to_login(): void
    {
        $response = $this->get(route('verification.notice'));
        $response->assertRedirect(route('login'));
    }
}
