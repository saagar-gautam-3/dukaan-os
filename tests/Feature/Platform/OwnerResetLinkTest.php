<?php

declare(strict_types=1);

namespace Tests\Feature\Platform;

use App\Models\AuditLog;
use App\Models\Customer;
use App\Models\Shop;
use App\Models\ShopUser;
use App\Models\User;
use App\Services\RbacService;
use App\Tenancy\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

class OwnerResetLinkTest extends TestCase
{
    use RefreshDatabase;

    private function admin(?User $user = null): User
    {
        return $this->actingAsPlatformAdmin($user);
    }

    private function shopWithOwner(array $ownerAttrs = []): array
    {
        $shop = Shop::factory()->create(['is_active' => true, 'timezone' => 'Asia/Kathmandu']);
        TenantContext::setShop($shop);
        $rbac = app(RbacService::class);
        $roles = $rbac->createDefaultRolesForShop($shop);
        $owner = User::factory()->create(array_merge(['email_verified_at' => now()], $ownerAttrs));
        ShopUser::factory()->create([
            'shop_id' => $shop->id,
            'user_id' => $owner->id,
            'role_id' => $roles['Shop Owner']->id,
            'status' => 'active',
        ]);
        TenantContext::reset();

        return [$shop, $owner];
    }

    private function extractLink(string $content): ?string
    {
        if (preg_match('/id="reset-link"[^>]*value="([^"]+)"/', $content, $m)) {
            return html_entity_decode($m[1], ENT_QUOTES);
        }
        if (preg_match('/value="([^"]+)"[^>]*id="reset-link"/', $content, $m)) {
            return html_entity_decode($m[1], ENT_QUOTES);
        }

        return null;
    }

    public function test_authorization_matrix(): void
    {
        [$shop] = $this->shopWithOwner();

        // anonymous
        $this->get(route('platform.shops.owner-reset', $shop))->assertRedirect('/login');
        $this->post(route('platform.shops.owner-reset.store', $shop), ['reason' => 'valid reason here', 'current_password' => 'x'])->assertRedirect('/login');

        // shop owner
        TenantContext::setShop($shop);
        $rbac = app(RbacService::class);
        $roles = $rbac->createDefaultRolesForShop($shop);
        $member = User::factory()->create(['email_verified_at' => now()]);
        ShopUser::factory()->create([
            'shop_id' => $shop->id,
            'user_id' => $member->id,
            'role_id' => $roles['Shop Owner']->id,
            'status' => 'active',
        ]);
        TenantContext::reset();
        $this->actingAs($member);
        $this->get(route('platform.shops.owner-reset', $shop))->assertStatus(403);
        $this->post(route('platform.shops.owner-reset.store', $shop), ['reason' => 'valid reason here', 'current_password' => 'x'])->assertStatus(403);

        // unverified admin
        $unverified = User::factory()->create(['is_platform_admin' => true, 'email_verified_at' => null]);
        $this->actingAs($unverified);
        $this->get(route('platform.shops.owner-reset', $shop))->assertRedirect(route('verification.notice'));
        $this->post(route('platform.shops.owner-reset.store', $shop), ['reason' => 'valid reason here', 'current_password' => 'x'])->assertRedirect(route('verification.notice'));

        // verified admin without 2FA
        $no2fa = User::factory()->create(['is_platform_admin' => true, 'email_verified_at' => now()]);
        $this->actingAs($no2fa);
        $this->get(route('platform.shops.owner-reset', $shop))->assertRedirect(route('platform.2fa.setup'));
        $this->post(route('platform.shops.owner-reset.store', $shop), ['reason' => 'valid reason here', 'current_password' => 'x'])->assertRedirect(route('platform.2fa.setup'));

        // admin with 2FA
        $admin = $this->admin();
        $this->get(route('platform.shops.owner-reset', $shop))->assertOk();
    }

    public function test_reason_validation(): void
    {
        [$shop] = $this->shopWithOwner();
        $admin = $this->admin();

        foreach (['', 'abcd', str_repeat('a', 501)] as $reason) {
            $this->actingAsPlatformAdmin($admin);
            $this->post(route('platform.shops.owner-reset.store', $shop), [
                'reason' => $reason,
                'current_password' => 'password',
            ])->assertSessionHasErrors('reason');
        }
    }

    public function test_wrong_admin_password_rejected_no_token_no_audit(): void
    {
        [$shop] = $this->shopWithOwner();
        $admin = $this->admin();
        $admin->password = bcrypt('correct-password');
        $admin->save();

        $this->actingAsPlatformAdmin($admin);

        $beforeTokens = \DB::table('password_reset_tokens')->count();
        $beforeAudits = AuditLog::where('event', 'platform.owner_reset_link_created')->count();

        $this->post(route('platform.shops.owner-reset.store', $shop), [
            'reason' => 'valid reason here',
            'current_password' => 'wrong-password',
        ])->assertSessionHasErrors('current_password');

        $this->assertSame($beforeTokens, \DB::table('password_reset_tokens')->count());
        $this->assertSame($beforeAudits, AuditLog::where('event', 'platform.owner_reset_link_created')->count());
    }

    public function test_throttle_429_after_five(): void
    {
        [$shop] = $this->shopWithOwner();
        $admin = $this->admin();
        $admin->password = bcrypt('correct-password');
        $admin->save();

        RateLimiter::clear('owner-reset:'.$admin->id);

        $this->actingAsPlatformAdmin($admin);

        for ($i = 0; $i < 5; $i++) {
            $this->post(route('platform.shops.owner-reset.store', $shop), [
                'reason' => 'valid reason here',
                'current_password' => 'wrong-password',
            ]);
        }

        $response = $this->post(route('platform.shops.owner-reset.store', $shop), [
            'reason' => 'valid reason here',
            'current_password' => 'wrong-password',
        ]);

        $this->assertSame(429, $response->getStatusCode());
    }

    public function test_success_link_shown_once_and_resets_password(): void
    {
        [$shop, $owner] = $this->shopWithOwner();
        $admin = $this->admin();
        $admin->password = bcrypt('correct-password');
        $admin->save();

        $this->actingAsPlatformAdmin($admin);

        $response = $this->followingRedirects()->post(route('platform.shops.owner-reset.store', $shop), [
            'reason' => 'Owner forgot password',
            'current_password' => 'correct-password',
        ]);

        $response->assertOk();
        $response->assertHeader('Cache-Control');

        $content = $response->getContent();
        $link = $this->extractLink($content);
        $this->assertNotNull($link);

        // Cache-Control no-store on final response
        $this->assertStringContainsString('no-store', (string) $response->headers->get('Cache-Control'));

        // Reload result page should not show link
        $reload = $this->get(route('platform.shops.owner-reset.result', $shop));
        $reload->assertOk();
        $this->assertStringNotContainsString($link, $reload->getContent());

        // token never in audit logs
        foreach (AuditLog::all() as $log) {
            $payload = json_encode(['old' => $log->old_values, 'new' => $log->new_values]);
            $token = basename(parse_url($link, PHP_URL_PATH));
            $this->assertStringNotContainsString($token, (string) $payload);
            $this->assertStringNotContainsString($link, (string) $payload);
        }

        auth()->logout();
        $this->flushSession();

        // link resets password through normal form
        $parsed = parse_url($link);
        $path = $parsed['path'] ?? '';
        $token = basename($path);
        parse_str($parsed['query'] ?? '', $query);
        $email = $query['email'] ?? $owner->email;

        $newPassword = 'New-Str0ng!23';
        $resetResponse = $this->post(route('password.store'), [
            'token' => $token,
            'email' => $email,
            'password' => $newPassword,
            'password_confirmation' => $newPassword,
        ]);
        $resetResponse->assertRedirect(route('login'))->assertSessionHasNoErrors();

        // second use fails
        $second = $this->post(route('password.store'), [
            'token' => $token,
            'email' => $email,
            'password' => 'Another-Str0ng!23',
            'password_confirmation' => 'Another-Str0ng!23',
        ]);
        $this->assertTrue(in_array($second->getStatusCode(), [302, 422]));
        // should have error
        $second->assertSessionHasErrors();
    }

    public function test_link_fails_after_expiry(): void
    {
        [$shop, $owner] = $this->shopWithOwner();
        $admin = $this->admin();
        $admin->password = bcrypt('correct-password');
        $admin->save();

        $this->actingAsPlatformAdmin($admin);

        $response = $this->followingRedirects()->post(route('platform.shops.owner-reset.store', $shop), [
            'reason' => 'Owner forgot password',
            'current_password' => 'correct-password',
        ]);

        $link = $this->extractLink($response->getContent());
        $this->assertNotNull($link);

        $parsed = parse_url($link);
        $token = basename($parsed['path'] ?? '');
        parse_str($parsed['query'] ?? '', $query);
        $email = $query['email'] ?? $owner->email;

        auth()->logout();
        $this->flushSession();

        $this->travel(61)->minutes();

        $newPassword = 'New-Str0ng!23';
        $resetResponse = $this->post(route('password.store'), [
            'token' => $token,
            'email' => $email,
            'password' => $newPassword,
            'password_confirmation' => $newPassword,
        ]);
        $this->assertTrue(in_array($resetResponse->getStatusCode(), [302, 422]));
        $resetResponse->assertSessionHasErrors();

        $this->travelBack();
    }

    public function test_second_generation_invalidates_first(): void
    {
        [$shop, $owner] = $this->shopWithOwner();
        $admin = $this->admin();
        $admin->password = bcrypt('correct-password');
        $admin->save();

        $this->actingAsPlatformAdmin($admin);

        $r1 = $this->followingRedirects()->post(route('platform.shops.owner-reset.store', $shop), [
            'reason' => 'first generation',
            'current_password' => 'correct-password',
        ]);
        $link1 = $this->extractLink($r1->getContent());

        $r2 = $this->followingRedirects()->post(route('platform.shops.owner-reset.store', $shop), [
            'reason' => 'second generation',
            'current_password' => 'correct-password',
        ]);
        $link2 = $this->extractLink($r2->getContent());

        $this->assertNotNull($link1);
        $this->assertNotNull($link2);
        $this->assertNotSame($link1, $link2);

        auth()->logout();
        $this->flushSession();

        // first link now invalid
        $parsed = parse_url($link1);
        $token = basename($parsed['path'] ?? '');
        parse_str($parsed['query'] ?? '', $query);
        $email = $query['email'] ?? $owner->email;

        $resetResponse = $this->post(route('password.store'), [
            'token' => $token,
            'email' => $email,
            'password' => 'New-Str0ng!23',
            'password_confirmation' => 'New-Str0ng!23',
        ]);
        $resetResponse->assertSessionHasErrors();
    }

    public function test_refuses_no_owner(): void
    {
        $shop = Shop::factory()->create(['is_active' => true]);
        $admin = $this->admin();
        $admin->password = bcrypt('correct-password');
        $admin->save();

        $this->actingAsPlatformAdmin($admin);

        $response = $this->post(route('platform.shops.owner-reset.store', $shop), [
            'reason' => 'valid reason here',
            'current_password' => 'correct-password',
        ]);

        $this->assertTrue(in_array($response->getStatusCode(), [302, 422, 404]));
    }

    public function test_refuses_platform_admin_owner(): void
    {
        $shop = Shop::factory()->create(['is_active' => true]);
        TenantContext::setShop($shop);
        $rbac = app(RbacService::class);
        $roles = $rbac->createDefaultRolesForShop($shop);
        $owner = User::factory()->create(['is_platform_admin' => true, 'email_verified_at' => now()]);
        ShopUser::factory()->create([
            'shop_id' => $shop->id,
            'user_id' => $owner->id,
            'role_id' => $roles['Shop Owner']->id,
            'status' => 'active',
        ]);
        TenantContext::reset();

        $admin = $this->admin();
        $admin->password = bcrypt('correct-password');
        $admin->save();

        $this->actingAsPlatformAdmin($admin);

        $response = $this->post(route('platform.shops.owner-reset.store', $shop), [
            'reason' => 'valid reason here',
            'current_password' => 'correct-password',
        ]);

        $this->assertTrue(in_array($response->getStatusCode(), [302, 422, 404]));
    }

    public function test_audit_event_present_and_viewer_lists(): void
    {
        [$shop] = $this->shopWithOwner();
        $admin = $this->admin();
        $admin->password = bcrypt('correct-password');
        $admin->save();

        $this->actingAsPlatformAdmin($admin);

        $this->post(route('platform.shops.owner-reset.store', $shop), [
            'reason' => 'Audit reason here',
            'current_password' => 'correct-password',
        ])->assertRedirect();

        $log = AuditLog::where('event', 'platform.owner_reset_link_created')->latest('id')->first();
        $this->assertNotNull($log);
        $this->assertSame($admin->id, $log->user_id);
        $this->assertSame('Audit reason here', $log->new_values['reason']);
        $this->assertSame($shop->id, $log->new_values['shop_id']);

        // audit viewer lists in en and ne
        foreach (['en', 'ne'] as $locale) {
            $this->actingAsPlatformAdmin($admin, ['locale' => $locale]);
            $viewer = $this->get(route('platform.audit.index'));
            $viewer->assertOk();
            $this->assertStringContainsString('platform.owner_reset_link_created', $viewer->getContent());
        }
    }

    public function test_pii_not_exposed(): void
    {
        [$shop, $owner] = $this->shopWithOwner(['phone' => '9800000001', 'email' => 'owner@pii.test']);
        $admin = $this->admin();
        $admin->password = bcrypt('correct-password');
        $admin->save();

        TenantContext::setShop($shop);
        $rbac = app(RbacService::class);
        $rbac->createDefaultRolesForShop($shop);
        $customerName = 'Distinctive Customer Name';
        Customer::factory()->create(['shop_id' => $shop->id, 'name' => $customerName, 'phone' => '9811111111']);
        TenantContext::reset();

        $this->actingAsPlatformAdmin($admin);

        $urls = [
            '/platform/shops',
            '/platform/shops/'.$shop->id,
            '/platform/shops/'.$shop->id.'/owner-reset',
        ];
        foreach ($urls as $url) {
            $response = $this->get($url);
            $response->assertOk();
            foreach (['Distinctive Customer Name', '9811111111', '9800000001'] as $secret) {
                $this->assertStringNotContainsString($secret, $response->getContent());
            }
        }
    }
}
