<?php

declare(strict_types=1);

namespace Tests\Feature\Auth;

use App\Actions\Tenancy\RegisterShopAction;
use App\Models\Role;
use App\Models\Shop;
use App\Models\User;
use App\Services\RbacService;
use Exception;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RegistrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_registration_screen_can_be_rendered(): void
    {
        $response = $this->get('/register');

        $response->assertStatus(200);
        $response->assertSee('Dukaan OS');
    }

    public function test_registration_creates_user_shop_owner_membership_and_default_roles_in_one_transaction(): void
    {
        $response = $this->post('/register', [
            'name' => 'Ram Shrestha',
            'email' => 'ram@example.com',
            'phone' => '9841234567',
            'shop_name' => 'Annapurna General Store',
            'business_type' => 'kirana',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $this->assertAuthenticated();

        /** @var User $user */
        $user = User::where('email', 'ram@example.com')->first();
        $this->assertNotNull($user);
        $this->assertSame('9841234567', $user->phone);

        /** @var Shop $shop */
        $shop = Shop::where('name', 'Annapurna General Store')->first();
        $this->assertNotNull($shop);
        $this->assertSame('kirana', $shop->business_type);

        // Check session
        $this->assertEquals($shop->id, session('current_shop_id'));

        // Check default roles created for this shop
        $expectedRoles = ['Shop Owner', 'Manager', 'Cashier', 'Inventory Staff', 'Accountant'];
        foreach ($expectedRoles as $roleName) {
            $this->assertDatabaseHas('roles', [
                'shop_id' => $shop->id,
                'name' => $roleName,
            ]);
        }

        // Check owner membership
        $ownerRole = Role::withoutGlobalScopes()
            ->where('shop_id', $shop->id)
            ->where('name', 'Shop Owner')
            ->first();

        $this->assertDatabaseHas('shop_user', [
            'shop_id' => $shop->id,
            'user_id' => $user->id,
            'role_id' => $ownerRole->id,
            'status' => 'active',
        ]);

        $response->assertRedirect(route('dashboard'));
    }

    public function test_registration_rolls_back_completely_if_error_occurs(): void
    {
        // Mock RbacService to throw an exception during default roles creation
        $this->mock(RbacService::class, function ($mock) {
            $mock->shouldReceive('createDefaultRolesForShop')
                ->andThrow(new Exception('Simulated role creation failure'));
        });

        $action = app(RegisterShopAction::class);

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Simulated role creation failure');

        try {
            $action->execute([
                'name' => 'Failed User',
                'email' => 'failed@example.com',
                'phone' => '9800000000',
                'password' => 'password123',
                'shop_name' => 'Failed Shop',
                'business_type' => 'kirana',
            ]);
        } finally {
            // Invariant: Entire transaction must be rolled back
            $this->assertDatabaseMissing('users', ['email' => 'failed@example.com']);
            $this->assertDatabaseMissing('shops', ['name' => 'Failed Shop']);
            $this->assertDatabaseMissing('shop_user', ['status' => 'active']);
        }
    }
}
