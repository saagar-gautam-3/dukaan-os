<?php

declare(strict_types=1);

namespace Tests\Feature\Staff;

use App\Models\AuditLog;
use App\Models\Role;
use App\Models\Shop;
use App\Models\ShopUser;
use App\Models\User;
use App\Services\RbacService;
use App\Tenancy\TenantContext;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

final class StaffManagementTest extends TestCase
{
    use RefreshDatabase;

    private Shop $shop;

    private User $owner;

    private ShopUser $ownerMembership;

    /** @var array<string, Role> */
    private array $roles;

    protected function setUp(): void
    {
        parent::setUp();

        $this->shop = Shop::factory()->create();
        $this->roles = TenantContext::runAs(
            $this->shop,
            fn (): array => app(RbacService::class)->createDefaultRolesForShop($this->shop),
        );
        $this->owner = User::factory()->create(['name' => 'Shop Owner']);
        $this->ownerMembership = ShopUser::create([
            'shop_id' => $this->shop->id,
            'user_id' => $this->owner->id,
            'role_id' => $this->roles['Shop Owner']->id,
            'status' => 'active',
        ]);

        TenantContext::setShop($this->shop, $this->ownerMembership);
    }

    protected function tearDown(): void
    {
        TenantContext::reset();
        parent::tearDown();
    }

    public function test_owner_can_add_staff_with_each_non_owner_role_and_sends_verification(): void
    {
        Notification::fake();

        foreach (['Manager', 'Cashier', 'Inventory Staff', 'Accountant'] as $index => $roleName) {
            $email = strtolower(str_replace(' ', '-', $roleName)).'@example.test';
            $response = $this->actingAs($this->owner)
                ->withSession(['current_shop_id' => $this->shop->id])
                ->post(route('staff.store'), [
                    'name' => "Staff Member {$index}",
                    'email' => $email,
                    'phone' => '९८४-१२३-४५६७',
                    'role_id' => $this->roles[$roleName]->id,
                    'password' => 'Temporary123',
                ]);

            $response->assertRedirect(route('staff.index'));
            $user = User::query()->where('email', $email)->firstOrFail();
            $membership = ShopUser::query()->where('shop_id', $this->shop->id)->where('user_id', $user->id)->firstOrFail();

            $this->assertNull($user->email_verified_at);
            $this->assertSame('9841234567', $user->phone);
            $this->assertFalse($user->is_platform_admin);
            $this->assertSame((int) $this->roles[$roleName]->id, (int) $membership->role_id);
            $this->assertSame((int) $this->shop->id, (int) $membership->shop_id);
            Notification::assertSentTo($user, VerifyEmail::class);

            $audit = AuditLog::query()
                ->where('shop_id', $this->shop->id)
                ->where('user_id', $this->owner->id)
                ->where('auditable_id', $membership->id)
                ->where('event', 'staff_added')
                ->first();

            $this->assertNotNull($audit);
            $this->assertSame(['user_id', 'role_id'], array_keys($audit->new_values));
            $this->assertArrayNotHasKey('password', $audit->new_values);
        }

        $this->assertFalse($this->roles['Manager']->permissions()->where('name', 'staff.manage')->exists());
        $this->assertFalse($this->roles['Cashier']->permissions()->where('name', 'staff.manage')->exists());
        $this->assertTrue($this->roles['Shop Owner']->permissions()->where('name', 'staff.manage')->exists());
    }

    public function test_added_staff_password_is_hashed_and_can_authenticate(): void
    {
        Notification::fake();
        $password = 'Temporary123';
        $email = 'new-staff@example.test';

        $this->actingAs($this->owner)
            ->withSession(['current_shop_id' => $this->shop->id])
            ->post(route('staff.store'), [
                'name' => 'New Staff',
                'email' => $email,
                'phone' => '9841234567',
                'role_id' => $this->roles['Cashier']->id,
                'password' => $password,
            ])
            ->assertRedirect(route('staff.index'));

        $staff = User::query()->where('email', $email)->firstOrFail();
        $membership = ShopUser::query()->where('user_id', $staff->id)->firstOrFail();
        $storedPassword = DB::table('users')->where('id', $staff->id)->value('password');

        $this->assertNotSame($password, $storedPassword);
        $this->assertTrue(Hash::check($password, $storedPassword));
        $this->assertNull($staff->email_verified_at);
        $auditEntries = AuditLog::query()
            ->where('auditable_id', $membership->id)
            ->where('event', 'staff_added')
            ->get();
        $this->assertNotEmpty($auditEntries);
        foreach ($auditEntries as $auditEntry) {
            $this->assertStringNotContainsString($password, json_encode($auditEntry->toArray(), JSON_THROW_ON_ERROR));
        }

        auth()->logout();
        $this->post(route('login'), ['email' => $email, 'password' => $password])
            ->assertRedirect(route('dashboard'));
        $this->assertAuthenticatedAs($staff);
    }

    public function test_verification_notification_failure_does_not_fail_staff_creation(): void
    {
        Notification::shouldReceive('send')->once()->andThrow(new \RuntimeException('Mail transport unavailable'));
        $email = 'mail-failure-staff@example.test';

        $response = $this->actingAs($this->owner)
            ->withSession(['current_shop_id' => $this->shop->id])
            ->post(route('staff.store'), [
                'name' => 'Mail Failure Staff',
                'email' => $email,
                'phone' => '9841234567',
                'role_id' => $this->roles['Cashier']->id,
                'password' => 'Temporary123',
            ]);

        $response->assertRedirect(route('staff.index'))
            ->assertSessionHas('status', __('staff.created_success'))
            ->assertSessionMissing('error');
        $this->assertDatabaseHas('users', ['email' => $email]);
        $this->assertDatabaseHas('shop_user', [
            'shop_id' => $this->shop->id,
            'user_id' => User::query()->where('email', $email)->value('id'),
        ]);
    }

    public function test_owner_can_list_staff_and_sidebar_link_is_permission_gated(): void
    {
        [$cashier, $cashierMembership] = $this->createMember($this->roles['Cashier'], 'cashier@example.test');

        $this->actingAs($this->owner)
            ->withSession(['current_shop_id' => $this->shop->id])
            ->get(route('staff.index'))
            ->assertOk()
            ->assertSee($cashier->name)
            ->assertSee($cashier->email)
            ->assertSee($cashier->phone)
            ->assertSee($this->roles['Cashier']->name)
            ->assertSee(route('staff.index'))
            ->assertSee(__('common.nav.staff'));

        $manager = $this->createMember($this->roles['Manager'], 'manager@example.test')[0];
        $response = $this->actingAs($manager)
            ->withSession(['current_shop_id' => $this->shop->id])
            ->get(route('dashboard'));

        $response->assertOk()->assertDontSee(route('staff.index'));
        $this->assertSame((int) $this->shop->id, (int) $cashierMembership->shop_id);
    }

    public function test_cashier_and_manager_receive_forbidden_on_every_staff_route(): void
    {
        $target = $this->ownerMembership;
        $paths = [
            ['GET', route('staff.index'), []],
            ['GET', route('staff.create'), []],
            ['POST', route('staff.store'), []],
            ['GET', route('staff.edit', $target), []],
            ['PUT', route('staff.update', $target), ['role_id' => $this->roles['Cashier']->id]],
            ['DELETE', route('staff.destroy', $target), []],
        ];

        foreach (['Cashier', 'Manager'] as $roleName) {
            $user = $this->createMember($this->roles[$roleName], strtolower(str_replace(' ', '-', $roleName)).'-denied@example.test')[0];

            foreach ($paths as [$method, $url, $data]) {
                $response = $this->actingAs($user)
                    ->withSession([
                        'current_shop_id' => $this->shop->id,
                        'permissions_version' => (int) $user->permissions_version,
                    ])
                    ->call($method, $url, $data);

                $response->assertForbidden();
            }
        }
    }

    public function test_guest_is_redirected_from_every_staff_route(): void
    {
        $routes = [
            ['GET', route('staff.index')],
            ['GET', route('staff.create')],
            ['POST', route('staff.store')],
            ['GET', route('staff.edit', $this->ownerMembership)],
            ['PUT', route('staff.update', $this->ownerMembership)],
            ['DELETE', route('staff.destroy', $this->ownerMembership)],
        ];

        foreach ($routes as [$method, $url]) {
            $this->call($method, $url)->assertRedirect(route('login'));
        }
    }

    public function test_other_shop_owner_cannot_edit_update_or_remove_this_shops_member(): void
    {
        [, $cashierMembership] = $this->createMember($this->roles['Cashier'], 'target@example.test');
        $otherShop = Shop::factory()->create();
        [$otherOwner, $otherOwnerMembership] = TenantContext::runAs($otherShop, function () use ($otherShop): array {
            $otherRoles = app(RbacService::class)->createDefaultRolesForShop($otherShop);
            $owner = User::factory()->create(['email_verified_at' => now()]);
            $membership = ShopUser::create([
                'shop_id' => $otherShop->id,
                'user_id' => $owner->id,
                'role_id' => $otherRoles['Shop Owner']->id,
                'status' => 'active',
            ]);

            return [$owner, $membership];
        });

        $requests = [
            ['GET', route('staff.edit', $cashierMembership), []],
            ['PUT', route('staff.update', $cashierMembership), ['role_id' => $this->roles['Accountant']->id]],
            ['DELETE', route('staff.destroy', $cashierMembership), []],
        ];

        foreach ($requests as [$method, $url, $data]) {
            $this->actingAs($otherOwner)
                ->withSession([
                    'current_shop_id' => $otherShop->id,
                    'permissions_version' => (int) $otherOwner->permissions_version,
                ])
                ->call($method, $url, $data)
                ->assertNotFound();
        }

        $this->assertSame((int) $otherShop->id, (int) $otherOwnerMembership->shop_id);
        $this->assertDatabaseHas('shop_user', ['id' => $cashierMembership->id, 'shop_id' => $this->shop->id]);
    }

    public function test_owner_cannot_change_or_remove_self_or_a_shop_owner_member(): void
    {
        $secondOwner = User::factory()->create();
        $secondOwnerMembership = ShopUser::create([
            'shop_id' => $this->shop->id,
            'user_id' => $secondOwner->id,
            'role_id' => $this->roles['Shop Owner']->id,
            'status' => 'active',
        ]);

        foreach ([$this->ownerMembership, $secondOwnerMembership] as $protectedMember) {
            $this->actingAs($this->owner)
                ->withSession(['current_shop_id' => $this->shop->id])
                ->put(route('staff.update', $protectedMember), ['role_id' => $this->roles['Cashier']->id])
                ->assertForbidden();

            $this->actingAs($this->owner)
                ->withSession(['current_shop_id' => $this->shop->id])
                ->delete(route('staff.destroy', $protectedMember))
                ->assertForbidden();
        }

        $this->assertDatabaseHas('shop_user', ['id' => $this->ownerMembership->id]);
        $this->assertDatabaseHas('shop_user', ['id' => $secondOwnerMembership->id]);
    }

    public function test_role_change_invalidates_session_then_new_role_access_applies(): void
    {
        [$cashier, $membership] = $this->createMember($this->roles['Cashier'], 'promoted@example.test');

        $this->actingAs($this->owner)
            ->withSession(['current_shop_id' => $this->shop->id])
            ->put(route('staff.update', $membership), ['role_id' => $this->roles['Accountant']->id])
            ->assertRedirect(route('staff.index'));

        $this->assertSame(1, (int) $cashier->fresh()->permissions_version);
        $this->actingAs($cashier->fresh())
            ->withSession(['current_shop_id' => $this->shop->id, 'permissions_version' => 0])
            ->get(route('dashboard'))
            ->assertRedirect(route('login'));

        $this->post(route('login'), ['email' => $cashier->email, 'password' => 'password'])
            ->assertRedirect(route('dashboard'));
        $this->get(route('reports.profit'))->assertOk();
    }

    public function test_role_demotion_invalidates_session_and_removes_previous_access(): void
    {
        [$accountant, $membership] = $this->createMember($this->roles['Accountant'], 'demoted@example.test');

        $this->actingAs($this->owner)
            ->withSession(['current_shop_id' => $this->shop->id])
            ->put(route('staff.update', $membership), ['role_id' => $this->roles['Cashier']->id])
            ->assertRedirect(route('staff.index'));

        $this->actingAs($accountant->fresh())
            ->withSession(['current_shop_id' => $this->shop->id, 'permissions_version' => 0])
            ->get(route('reports.profit'))
            ->assertRedirect(route('login'));

        $this->post(route('login'), ['email' => $accountant->email, 'password' => 'password'])
            ->assertRedirect(route('dashboard'));
        $this->get(route('reports.profit'))->assertForbidden();
    }

    public function test_removal_invalidates_session_and_relogin_has_no_shop_access(): void
    {
        [$cashier, $membership] = $this->createMember($this->roles['Cashier'], 'removed@example.test');

        $this->actingAs($this->owner)
            ->withSession(['current_shop_id' => $this->shop->id])
            ->delete(route('staff.destroy', $membership))
            ->assertRedirect(route('staff.index'));

        $this->assertSame(1, (int) $cashier->fresh()->permissions_version);
        $this->assertDatabaseMissing('shop_user', ['id' => $membership->id]);

        $this->actingAs($cashier->fresh())
            ->withSession(['current_shop_id' => $this->shop->id, 'permissions_version' => 0])
            ->get(route('dashboard'))
            ->assertRedirect(route('login'));

        $this->post(route('login'), ['email' => $cashier->email, 'password' => 'password'])
            ->assertRedirect(route('shops.picker'));
        $this->get(route('dashboard'))->assertForbidden();
    }

    public function test_duplicate_email_is_rejected_and_mass_assignment_inputs_are_not_trusted(): void
    {
        $this->actingAs($this->owner)
            ->withSession(['current_shop_id' => $this->shop->id])
            ->from(route('staff.create'))
            ->post(route('staff.store'), $this->staffData($this->owner->email, $this->roles['Cashier']))
            ->assertRedirect(route('staff.create'))
            ->assertSessionHasErrors('email');

        $otherShop = Shop::factory()->create();
        $foreignRole = TenantContext::runAs($otherShop, function () use ($otherShop): Role {
            return app(RbacService::class)->createDefaultRolesForShop($otherShop)['Cashier'];
        });

        $this->actingAs($this->owner)
            ->withSession(['current_shop_id' => $this->shop->id])
            ->from(route('staff.create'))
            ->post(route('staff.store'), $this->staffData('foreign-role@example.test', $foreignRole))
            ->assertRedirect(route('staff.create'))
            ->assertSessionHasErrors('role_id');

        $data = $this->staffData('mass-assignment@example.test', $this->roles['Cashier']);
        $data['shop_id'] = $otherShop->id;
        $data['is_platform_admin'] = true;

        $this->actingAs($this->owner)
            ->withSession(['current_shop_id' => $this->shop->id])
            ->post(route('staff.store'), $data)
            ->assertRedirect(route('staff.index'));

        $user = User::query()->where('email', 'mass-assignment@example.test')->firstOrFail();
        $membership = ShopUser::query()->where('user_id', $user->id)->firstOrFail();
        $this->assertFalse($user->is_platform_admin);
        $this->assertSame((int) $this->shop->id, (int) $membership->shop_id);
        $this->assertSame((int) $this->roles['Cashier']->id, (int) $membership->role_id);
        $this->assertDatabaseMissing('shop_user', ['user_id' => $user->id, 'shop_id' => $otherShop->id]);
    }

    public function test_existing_shop_owner_roles_receive_staff_manage_from_migration(): void
    {
        $otherShop = Shop::factory()->create();
        $legacyOwnerRole = TenantContext::runAs($otherShop, function () use ($otherShop): Role {
            $role = new Role;
            $role->shop_id = $otherShop->id;
            $role->name = 'Shop Owner';
            $role->guard_name = 'web';
            $role->save();

            return $role;
        });

        $permissionId = DB::table('permissions')->where('name', 'staff.manage')->value('id');
        DB::table('role_permissions')->where('role_id', $legacyOwnerRole->id)->where('permission_id', $permissionId)->delete();

        $migration = require database_path('migrations/2026_11_04_030000_add_staff_manage_permission_to_shop_owners.php');
        $migration->up();

        $this->assertDatabaseHas('role_permissions', [
            'role_id' => $legacyOwnerRole->id,
            'permission_id' => $permissionId,
        ]);
    }

    /** @return array{User, ShopUser} */
    private function createMember(Role $role, string $email): array
    {
        $user = User::factory()->create([
            'email' => $email,
            'email_verified_at' => now(),
        ]);
        $membership = ShopUser::create([
            'shop_id' => $this->shop->id,
            'user_id' => $user->id,
            'role_id' => $role->id,
            'status' => 'active',
        ]);

        return [$user, $membership];
    }

    /** @return array{name: string, email: string, phone: string, role_id: int, password: string} */
    private function staffData(string $email, Role $role): array
    {
        return [
            'name' => 'New Staff Member',
            'email' => $email,
            'phone' => '9841234567',
            'role_id' => (int) $role->id,
            'password' => 'Temporary123',
        ];
    }
}
