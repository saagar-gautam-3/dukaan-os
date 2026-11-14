<?php

declare(strict_types=1);

namespace Tests\Feature\Security;

use App\Models\Shop;
use App\Models\ShopUser;
use App\Models\User;
use App\Services\RbacService;
use App\Tenancy\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Password;
use Monolog\Handler\TestHandler;
use Tests\TestCase;

/**
 * T8a: plaintext passwords must never leak into audit logs, the application
 * log, or flash/session data during staff creation, registration or reset.
 */
final class PasswordSecrecyTest extends TestCase
{
    use RefreshDatabase;

    private Shop $shop;

    private User $owner;

    private TestHandler $logHandler;

    /** @var list<string> */
    private array $passwords = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->shop = Shop::factory()->create();
        TenantContext::setShop($this->shop);

        $roles = app(RbacService::class)->createDefaultRolesForShop($this->shop);
        $this->owner = User::factory()->create();
        ShopUser::factory()->create([
            'shop_id' => $this->shop->id,
            'user_id' => $this->owner->id,
            'role_id' => $roles['Shop Owner']->id,
            'status' => 'active',
        ]);

        $this->logHandler = new TestHandler;
        Log::getLogger()->pushHandler($this->logHandler);
    }

    protected function tearDown(): void
    {
        TenantContext::reset();
        parent::tearDown();
    }

    public function test_passwords_never_reach_audit_logs_application_log_or_session(): void
    {
        $this->createStaffMember();
        $this->registerShopOwner();
        $this->resetPassword();

        $this->assertAuditLogsContainNoPassword();
        $this->assertApplicationLogContainsNoPassword();
        $this->assertSessionContainsNoPassword();
        $this->assertPasswordsAreHashedAtRest();
    }

    private function createStaffMember(): void
    {
        $password = 'Staff-Str0ng!23';
        $this->passwords[] = $password;

        $roles = app(RbacService::class)->createDefaultRolesForShop($this->shop);

        $response = $this->actingAs($this->owner)
            ->withSession(['current_shop_id' => $this->shop->id])
            ->post(route('staff.store'), [
                'name' => 'Password Probe Staff',
                'email' => 'password.probe.staff@example.test',
                'phone' => '9841234501',
                'role_id' => $roles['Cashier']->id,
                'password' => $password,
            ]);

        $response->assertRedirect(route('staff.index'))->assertSessionHasNoErrors();
        $this->assertStringNotInSession($password, 'staff creation');
        $this->assertStringNotInLogs($password, 'staff creation');
    }

    private function registerShopOwner(): void
    {
        $this->post(route('logout'))->assertRedirect();

        $password = 'Register-Str0ng!23';
        $this->passwords[] = $password;

        $response = $this->post(route('register'), [
            'name' => 'Password Probe Owner',
            'email' => 'password.probe.owner@example.test',
            'phone' => '9841234502',
            'password' => $password,
            'password_confirmation' => $password,
            'shop_name' => 'Password Probe Shop',
            'business_type' => 'kirana',
        ]);

        $response->assertRedirect(route('dashboard'))->assertSessionHasNoErrors();
        $this->assertStringNotInSession($password, 'registration');
        $this->assertStringNotInLogs($password, 'registration');
    }

    private function resetPassword(): void
    {
        $this->post(route('logout'))->assertRedirect();

        $user = User::factory()->create([
            'email' => 'password.probe.reset@example.test',
            'password' => 'Old-Password!23',
        ]);

        $password = 'Reset-Str0ng!23';
        $this->passwords[] = $password;
        $token = Password::broker()->createToken($user);

        $response = $this->post(route('password.store'), [
            'token' => $token,
            'email' => $user->email,
            'password' => $password,
            'password_confirmation' => $password,
        ]);

        $response->assertRedirect(route('login'))->assertSessionHasNoErrors();
        $this->assertStringNotInSession($password, 'password reset');
        $this->assertStringNotInLogs($password, 'password reset');
    }

    private function assertAuditLogsContainNoPassword(): void
    {
        $rows = DB::table('audit_logs')->get();

        $this->assertNotEmpty($rows, 'No audit rows were written; the check would be meaningless.');

        foreach ($rows as $row) {
            $payload = json_encode([
                'old_values' => $row->old_values,
                'new_values' => $row->new_values,
            ], JSON_THROW_ON_ERROR);

            $this->assertNoPasswordIn($payload, 'audit_logs row '.$row->id);
        }
    }

    private function assertApplicationLogContainsNoPassword(): void
    {
        $records = $this->logHandler->getRecords();

        foreach ($records as $record) {
            $payload = $record->message.' '.json_encode([
                'context' => $record->context,
                'extra' => $record->extra,
            ], JSON_THROW_ON_ERROR);

            $this->assertNoPasswordIn($payload, 'application log record');
        }
    }

    private function assertSessionContainsNoPassword(): void
    {
        $payload = json_encode(session()->all(), JSON_THROW_ON_ERROR);

        $this->assertNoPasswordIn($payload, 'session/flash data');
    }

    private function assertPasswordsAreHashedAtRest(): void
    {
        foreach ([
            'password.probe.staff@example.test' => $this->passwords[0],
            'password.probe.owner@example.test' => $this->passwords[1],
            'password.probe.reset@example.test' => $this->passwords[2],
        ] as $email => $password) {
            $stored = (string) DB::table('users')->where('email', $email)->value('password');

            $this->assertNotSame($password, $stored, $email.' stored its password in plaintext.');
            $this->assertTrue(Hash::check($password, $stored), $email.' did not store a hash of the submitted password.');
        }
    }

    private function assertStringNotInSession(string $needle, string $context): void
    {
        $this->assertNoPasswordIn(
            json_encode(session()->all(), JSON_THROW_ON_ERROR),
            $context.' session'
        );
    }

    private function assertStringNotInLogs(string $needle, string $context): void
    {
        foreach ($this->logHandler->getRecords() as $record) {
            $payload = $record->message.' '.json_encode([
                'context' => $record->context,
                'extra' => $record->extra,
            ], JSON_THROW_ON_ERROR);

            $this->assertNoPasswordIn($payload, $context.' application log');
        }
    }

    private function assertNoPasswordIn(string $payload, string $location): void
    {
        foreach ($this->passwords as $password) {
            $this->assertStringNotContainsString(
                $password,
                $payload,
                'A plaintext password leaked into '.$location.'.'
            );
        }
    }
}
