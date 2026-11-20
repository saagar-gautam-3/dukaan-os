<?php

declare(strict_types=1);

namespace Tests\Feature\Platform;

use App\Models\AuditLog;
use App\Models\Shop;
use App\Models\ShopUser;
use App\Models\User;
use App\Services\RbacService;
use App\Support\PlatformAuditEvents;
use App\Tenancy\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use PragmaRX\Google2FA\Google2FA;
use Tests\TestCase;

class TwoFactorTest extends TestCase
{
    use RefreshDatabase;

    private function google2fa(): Google2FA
    {
        return new Google2FA;
    }

    private function platformAdmin(array $attributes = [], bool $confirmed = false): User
    {
        $user = User::factory()->create(array_merge([
            'is_platform_admin' => true,
            'email_verified_at' => now(),
        ], $attributes));

        if ($confirmed) {
            $secret = $this->google2fa()->generateSecretKey();
            $user->forceFill([
                'two_factor_secret' => $secret,
                'two_factor_confirmed_at' => now(),
                'two_factor_recovery_codes' => [Hash::make('ABCDE-12345')],
                'two_factor_last_step' => null,
            ])->save();
        }

        return $user;
    }

    public function test_platform_routes_redirect_to_setup_when_2fa_not_confirmed(): void
    {
        $admin = $this->platformAdmin();
        $shop = Shop::factory()->create();

        $this->actingAs($admin);
        $this->get(route('platform.shops.index'))->assertRedirect(route('platform.2fa.setup'));
        $this->get(route('platform.shops.show', $shop))->assertRedirect(route('platform.2fa.setup'));
        $this->get(route('platform.audit.index'))->assertRedirect(route('platform.2fa.setup'));
    }

    public function test_platform_routes_redirect_to_challenge_when_confirmed_but_not_passed(): void
    {
        $admin = $this->platformAdmin([], true);
        $shop = Shop::factory()->create();

        $this->actingAs($admin);
        $this->get(route('platform.shops.index'))->assertRedirect(route('platform.2fa.challenge'));
        $this->get(route('platform.shops.show', $shop))->assertRedirect(route('platform.2fa.challenge'));
        $this->get(route('platform.audit.index'))->assertRedirect(route('platform.2fa.challenge'));
    }

    public function test_platform_routes_accessible_after_valid_challenge(): void
    {
        $secret = $this->google2fa()->generateSecretKey();
        $admin = $this->platformAdmin();
        $admin->forceFill([
            'two_factor_secret' => $secret,
            'two_factor_confirmed_at' => now(),
            'two_factor_recovery_codes' => [Hash::make('ABCDE-12345')],
            'two_factor_last_step' => null,
        ])->save();

        $this->actingAs($admin);
        $code = $this->google2fa()->getCurrentOtp($secret);
        $response = $this->post(route('platform.2fa.challenge.verify'), ['code' => $code]);
        $response->assertRedirect(route('platform.shops.index'));

        $this->get(route('platform.shops.index'))->assertOk();
        $this->get(route('platform.audit.index'))->assertOk();
    }

    public function test_challenge_required_again_after_12_hours(): void
    {
        $this->travelTo(Carbon::parse('2026-11-19 10:00:00', 'UTC'));
        $admin = $this->actingAsPlatformAdmin();

        $this->get(route('platform.shops.index'))->assertOk();

        $this->travelTo(Carbon::parse('2026-11-19 22:00:01', 'UTC'));
        $this->get(route('platform.shops.index'))->assertRedirect(route('platform.2fa.challenge'));
    }

    public function test_setup_wrong_code_rejected_and_not_confirmed(): void
    {
        $admin = $this->platformAdmin();
        $this->actingAs($admin);

        $this->get(route('platform.2fa.setup'))->assertOk();
        $response = $this->from(route('platform.2fa.setup'))->post(route('platform.2fa.setup.confirm'), ['code' => '000000']);
        $response->assertRedirect(route('platform.2fa.setup'));
        $response->assertSessionHasErrors('code');

        $admin->refresh();
        $this->assertNull($admin->two_factor_confirmed_at);
        $this->assertNull($admin->two_factor_recovery_codes);
    }

    public function test_setup_right_code_confirms_shows_codes_once_and_prevents_reload_and_second_setup(): void
    {
        $admin = $this->platformAdmin();
        $this->actingAs($admin);

        $setup = $this->get(route('platform.2fa.setup'));
        $setup->assertOk();
        $admin->refresh();
        $secret = $admin->two_factor_secret;
        $this->assertNotEmpty($secret);

        $code = $this->google2fa()->getCurrentOtp($secret);
        $response = $this->post(route('platform.2fa.setup.confirm'), ['code' => $code]);
        $response->assertRedirect(route('platform.2fa.recovery-codes'));
        $response->assertSessionHas('platform_recovery_codes_once');

        $codes = session('platform_recovery_codes_once');
        $this->assertIsArray($codes);
        $this->assertCount(8, $codes);

        $admin->refresh();
        $this->assertNotNull($admin->two_factor_confirmed_at);
        $this->assertIsArray($admin->two_factor_recovery_codes);
        $this->assertCount(8, $admin->two_factor_recovery_codes);

        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $admin->id,
            'event' => PlatformAuditEvents::TWO_FACTOR_ENABLED,
        ]);

        $recoveryPage = $this->get(route('platform.2fa.recovery-codes'));
        $recoveryPage->assertOk();
        foreach ($codes as $plain) {
            $recoveryPage->assertSee($plain);
        }

        // Codes shown once: subsequent visit must not show them again.
        $reload = $this->get(route('platform.2fa.recovery-codes'));
        $reload->assertOk();
        foreach ($codes as $plain) {
            $reload->assertDontSee($plain);
        }

        // Second setup refused (already confirmed and passed).
        $this->get(route('platform.2fa.setup'))->assertRedirect(route('platform.shops.index'));
        $this->post(route('platform.2fa.setup.confirm'), ['code' => $code])->assertRedirect(route('platform.shops.index'));
    }

    public function test_challenge_wrong_code_rejected_and_rate_limited(): void
    {
        $secret = $this->google2fa()->generateSecretKey();
        $admin = $this->platformAdmin();
        $admin->forceFill([
            'two_factor_secret' => $secret,
            'two_factor_confirmed_at' => now(),
            'two_factor_recovery_codes' => [Hash::make('ABCDE-12345')],
            'two_factor_last_step' => null,
        ])->save();

        for ($i = 0; $i < 5; $i++) {
            $this->actingAs($admin);
            $this->withSession([]);
            $response = $this->from(route('platform.2fa.challenge'))->post(route('platform.2fa.challenge.verify'), ['code' => '000000']);
            $response->assertRedirect(route('platform.2fa.challenge'));
            $response->assertSessionHasErrors('code');
        }

        $this->actingAs($admin);
        $this->withSession([]);
        $this->post(route('platform.2fa.challenge.verify'), ['code' => '000000'])->assertStatus(429);

        $this->assertGreaterThanOrEqual(5, AuditLog::where('event', PlatformAuditEvents::TWO_FACTOR_FAILED)->count());
    }

    public function test_totp_replay_is_rejected(): void
    {
        $secret = $this->google2fa()->generateSecretKey();
        $admin = $this->platformAdmin();
        $admin->forceFill([
            'two_factor_secret' => $secret,
            'two_factor_confirmed_at' => now(),
            'two_factor_recovery_codes' => [Hash::make('ABCDE-12345')],
            'two_factor_last_step' => null,
        ])->save();

        $code = $this->google2fa()->getCurrentOtp($secret);

        $this->actingAs($admin);
        $this->post(route('platform.2fa.challenge.verify'), ['code' => $code])->assertRedirect(route('platform.shops.index'));
        $this->assertNotNull($admin->fresh()->two_factor_last_step);

        // Replay same code in a new session/challenge context.
        $this->flushSession();
        $this->actingAs($admin);
        $response = $this->from(route('platform.2fa.challenge'))->post(route('platform.2fa.challenge.verify'), ['code' => $code]);
        $response->assertRedirect(route('platform.2fa.challenge'));
        $response->assertSessionHasErrors('code');
    }

    public function test_recovery_code_works_once_warns_when_low_and_audits(): void
    {
        $secret = $this->google2fa()->generateSecretKey();
        $recoveryCodes = ['RCODE-11111', 'RCODE-22222', 'RCODE-33333'];
        $admin = $this->platformAdmin();
        $admin->forceFill([
            'two_factor_secret' => $secret,
            'two_factor_confirmed_at' => now(),
            'two_factor_recovery_codes' => array_map(fn (string $c) => Hash::make($c), $recoveryCodes),
            'two_factor_last_step' => null,
        ])->save();

        $this->actingAs($admin);
        $response = $this->post(route('platform.2fa.challenge.verify'), ['code' => 'RCODE-11111']);
        $response->assertRedirect(route('platform.shops.index'));
        $response->assertSessionHasNoErrors();

        $admin->refresh();
        $this->assertCount(2, $admin->two_factor_recovery_codes);
        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $admin->id,
            'event' => PlatformAuditEvents::TWO_FACTOR_RECOVERY_USED,
        ]);

        // Second use of same recovery code fails.
        $this->flushSession();
        $this->actingAs($admin);
        $failed = $this->from(route('platform.2fa.challenge'))->post(route('platform.2fa.challenge.verify'), ['code' => 'RCODE-11111']);
        $failed->assertRedirect(route('platform.2fa.challenge'));
        $failed->assertSessionHasErrors('code');

        // Use another code, leaving 1 remaining -> warning.
        $this->flushSession();
        $this->actingAs($admin);
        $warn = $this->post(route('platform.2fa.challenge.verify'), ['code' => 'RCODE-22222']);
        $warn->assertRedirect(route('platform.shops.index'));
        $warn->assertSessionHas('warning');
    }

    public function test_storage_hashes_secret_encrypted_and_recovery_codes_hashed(): void
    {
        $admin = $this->platformAdmin();
        $this->actingAs($admin);

        $this->get(route('platform.2fa.setup'))->assertOk();
        $admin->refresh();
        $rawSecret = $admin->two_factor_secret;
        $this->assertNotEmpty($rawSecret);

        $rawDbSecret = $admin->getRawOriginal('two_factor_secret');
        $this->assertNotSame($rawSecret, $rawDbSecret);

        $code = $this->google2fa()->getCurrentOtp($rawSecret);
        $response = $this->post(route('platform.2fa.setup.confirm'), ['code' => $code]);
        $response->assertRedirect(route('platform.2fa.recovery-codes'));
        $plainCodes = session('platform_recovery_codes_once');
        $this->assertIsArray($plainCodes);

        $admin->refresh();
        $hashes = $admin->two_factor_recovery_codes;
        $this->assertIsArray($hashes);
        $this->assertCount(8, $hashes);
        foreach ($plainCodes as $plain) {
            $this->assertNotContains($plain, $hashes);
        }
        $this->assertTrue(Hash::check($plainCodes[0], $hashes[0]));
    }

    public function test_regenerate_requires_password_and_code_old_codes_stop_working(): void
    {
        $secret = $this->google2fa()->generateSecretKey();
        $oldCode = 'OLDCD-11111';
        $admin = User::factory()->create([
            'is_platform_admin' => true,
            'email_verified_at' => now(),
            'password' => 'password123',
        ]);
        $admin->forceFill([
            'two_factor_secret' => $secret,
            'two_factor_confirmed_at' => now(),
            'two_factor_recovery_codes' => [Hash::make($oldCode)],
            'two_factor_last_step' => null,
        ])->save();

        $this->actingAs($admin);
        $this->withSession(['platform_2fa_passed_at' => now()->timestamp]);

        $this->get(route('platform.2fa.recovery-codes'))->assertOk();

        // Wrong password rejected.
        $code = $this->google2fa()->getCurrentOtp($secret);
        $wrongPw = $this->from(route('platform.2fa.recovery-codes'))->post(route('platform.2fa.recovery-codes.regenerate'), [
            'password' => 'wrong-password',
            'code' => $code,
        ]);
        $wrongPw->assertRedirect(route('platform.2fa.recovery-codes'));
        $wrongPw->assertSessionHasErrors('password');
        $this->assertTrue(Hash::check($oldCode, $admin->fresh()->two_factor_recovery_codes[0]));

        // Correct password and code regenerate.
        $currentCode = $this->google2fa()->getCurrentOtp($secret);
        // Ensure not replaying if needed by moving step context; if same time slice, use a fresh step check is okay because last_step may be null still.
        $response = $this->post(route('platform.2fa.recovery-codes.regenerate'), [
            'password' => 'password123',
            'code' => $currentCode,
        ]);
        $response->assertRedirect(route('platform.2fa.recovery-codes'));
        $response->assertSessionHas('platform_recovery_codes_once');
        $newCodes = session('platform_recovery_codes_once');
        $this->assertIsArray($newCodes);
        $this->assertCount(8, $newCodes);

        $admin->refresh();
        $this->assertFalse(Hash::check($oldCode, $admin->two_factor_recovery_codes[0]));
        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $admin->id,
            'event' => PlatformAuditEvents::TWO_FACTOR_RECOVERY_REGENERATED,
        ]);
    }

    public function test_non_platform_users_cannot_access_2fa_routes_and_tenant_flows_unaffected(): void
    {
        $shop = Shop::factory()->create();
        TenantContext::setShop($shop);
        $roles = app(RbacService::class)->createDefaultRolesForShop($shop);

        $owner = User::factory()->create(['email_verified_at' => now()]);
        ShopUser::factory()->create([
            'shop_id' => $shop->id,
            'user_id' => $owner->id,
            'role_id' => $roles['Shop Owner']->id,
            'status' => 'active',
        ]);

        $cashier = User::factory()->create(['email_verified_at' => now()]);
        ShopUser::factory()->create([
            'shop_id' => $shop->id,
            'user_id' => $cashier->id,
            'role_id' => $roles['Cashier']->id,
            'status' => 'active',
        ]);

        // Anonymous
        $this->get(route('platform.2fa.setup'))->assertRedirect('/login');
        $this->get(route('platform.2fa.challenge'))->assertRedirect('/login');

        // Shop owner / cashier forbidden on platform 2FA routes.
        foreach ([$owner, $cashier] as $user) {
            $this->actingAs($user);
            $this->get(route('platform.2fa.setup'))->assertStatus(403);
            $this->post(route('platform.2fa.setup.confirm'), ['code' => '123456'])->assertStatus(403);
            $this->get(route('platform.2fa.challenge'))->assertStatus(403);
            $this->post(route('platform.2fa.challenge.verify'), ['code' => '123456'])->assertStatus(403);
            $this->get(route('platform.2fa.recovery-codes'))->assertStatus(403);
            $this->post(route('platform.2fa.recovery-codes.regenerate'), ['password' => 'x', 'code' => 'x'])->assertStatus(403);
        }

        // Tenant routes unaffected for shop users.
        $this->actingAs($owner);
        TenantContext::setShop($shop);
        $this->get(route('dashboard'))->assertOk();
    }

    public function test_audit_rows_contain_no_secrets_or_codes_and_viewer_lists_events(): void
    {
        $secret = $this->google2fa()->generateSecretKey();
        $admin = $this->platformAdmin();
        $admin->forceFill([
            'two_factor_secret' => $secret,
            'two_factor_confirmed_at' => now(),
            'two_factor_recovery_codes' => [Hash::make('SAFE1-11111')],
            'two_factor_last_step' => null,
        ])->save();

        $this->actingAs($admin);
        $this->withSession([]);
        $this->post(route('platform.2fa.challenge.verify'), ['code' => '000000']); // fail
        $this->withSession([]);
        $this->post(route('platform.2fa.challenge.verify'), ['code' => 'SAFE1-11111']); // recovery used

        $logs = AuditLog::query()->whereIn('event', [
            PlatformAuditEvents::TWO_FACTOR_FAILED,
            PlatformAuditEvents::TWO_FACTOR_RECOVERY_USED,
        ])->get();
        $this->assertGreaterThanOrEqual(2, $logs->count());

        foreach ($logs as $log) {
            $json = json_encode([$log->old_values, $log->new_values]);
            $this->assertStringNotContainsString($secret, (string) $json);
            $this->assertStringNotContainsString('000000', (string) $json);
            $this->assertStringNotContainsString('SAFE1-11111', (string) $json);
        }

        $this->actingAsPlatformAdmin();
        $viewer = $this->get(route('platform.audit.index'));
        $viewer->assertOk();
        $viewer->assertSee(PlatformAuditEvents::TWO_FACTOR_FAILED);
        $viewer->assertSee(PlatformAuditEvents::TWO_FACTOR_RECOVERY_USED);
    }

    public function test_platform_audit_viewer_lists_2fa_events_in_nepali_and_english_without_raw_keys(): void
    {
        $adminEn = $this->actingAsPlatformAdmin(null, ['locale' => 'en']);
        $shop = Shop::factory()->create();

        AuditLog::create([
            'shop_id' => null,
            'user_id' => $adminEn->id,
            'event' => PlatformAuditEvents::TWO_FACTOR_ENABLED,
            'auditable_type' => (new User)->getMorphClass(),
            'auditable_id' => $adminEn->id,
            'old_values' => null,
            'new_values' => ['enabled' => true],
            'ip_address' => null,
            'user_agent' => null,
            'created_at' => now(),
        ]);

        app()->setLocale('en');
        $this->actingAsPlatformAdmin($adminEn, ['locale' => 'en']);
        $en = $this->get(route('platform.audit.index'));
        $en->assertOk();
        $en->assertSee(__('platform.events.platform_2fa_enabled', [], 'en'));
        $en->assertSee(PlatformAuditEvents::TWO_FACTOR_ENABLED);
        $en->assertDontSee('platform.events.platform_2fa_enabled');

        $adminNe = $this->actingAsPlatformAdmin(null, ['locale' => 'ne']);
        app()->setLocale('ne');
        $this->actingAsPlatformAdmin($adminNe, ['locale' => 'ne']);
        $ne = $this->get(route('platform.audit.index'));
        $ne->assertOk();
        $ne->assertSee(__('platform.events.platform_2fa_enabled', [], 'ne'));
        $ne->assertSee(PlatformAuditEvents::TWO_FACTOR_ENABLED);
        $ne->assertDontSee('platform.events.platform_2fa_enabled');
    }

    protected function setUp(): void
    {
        parent::setUp();
        RateLimiter::clear('platform-2fa');
    }

    protected function tearDown(): void
    {
        TenantContext::reset();
        RateLimiter::clear('platform-2fa');
        parent::tearDown();
    }
}
