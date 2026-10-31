<?php

declare(strict_types=1);

namespace Tests\Feature\Auth;

use App\Models\Shop;
use App\Models\ShopUser;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_screen_can_be_rendered(): void
    {
        $response = $this->get('/login');

        $response->assertStatus(200);
        $response->assertSee('Dukaan OS');
    }

    public function test_users_can_authenticate_and_single_shop_is_selected_in_session(): void
    {
        $user = User::factory()->create([
            'email' => 'loginuser@example.com',
            'password' => bcrypt('password123'),
        ]);

        $shop = Shop::factory()->create();

        ShopUser::factory()->create([
            'shop_id' => $shop->id,
            'user_id' => $user->id,
            'status' => 'active',
        ]);

        $response = $this->post('/login', [
            'email' => 'loginuser@example.com',
            'password' => 'password123',
        ]);

        $this->assertAuthenticated();
        $this->assertEquals($shop->id, session('current_shop_id'));
        $response->assertRedirect(route('dashboard'));
    }

    public function test_users_with_multiple_shops_redirect_to_shop_picker(): void
    {
        $user = User::factory()->create([
            'email' => 'multiuser@example.com',
            'password' => bcrypt('password123'),
        ]);

        $shop1 = Shop::factory()->create();
        $shop2 = Shop::factory()->create();

        ShopUser::factory()->create([
            'shop_id' => $shop1->id,
            'user_id' => $user->id,
            'status' => 'active',
        ]);

        ShopUser::factory()->create([
            'shop_id' => $shop2->id,
            'user_id' => $user->id,
            'status' => 'active',
        ]);

        $response = $this->post('/login', [
            'email' => 'multiuser@example.com',
            'password' => 'password123',
        ]);

        $this->assertAuthenticated();
        $response->assertRedirect(route('shops.picker'));
    }

    public function test_users_cannot_authenticate_with_invalid_password(): void
    {
        $user = User::factory()->create([
            'email' => 'testuser@example.com',
            'password' => bcrypt('correct-password'),
        ]);

        $this->post('/login', [
            'email' => 'testuser@example.com',
            'password' => 'wrong-password',
        ]);

        $this->assertGuest();
    }

    public function test_login_throttling_after_five_failed_attempts(): void
    {
        $user = User::factory()->create([
            'email' => 'throttleuser@example.com',
            'password' => bcrypt('correct-password'),
        ]);

        for ($i = 0; $i < 5; $i++) {
            $this->post('/login', [
                'email' => 'throttleuser@example.com',
                'password' => 'wrong-password',
            ]);
        }

        // 6th attempt should be throttled
        $response = $this->post('/login', [
            'email' => 'throttleuser@example.com',
            'password' => 'wrong-password',
        ]);

        $response->assertStatus(429);
        $this->assertGuest();
    }

    public function test_users_can_logout(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post('/logout');

        $this->assertGuest();
        $response->assertRedirect('/');
    }
}
