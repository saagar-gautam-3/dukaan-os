<?php

declare(strict_types=1);

namespace Tests\Feature\Udhar;

use App\Enums\LedgerEntryType;
use App\Models\Customer;
use App\Models\CustomerLedgerEntry;
use App\Models\ReminderLog;
use App\Models\Shop;
use App\Models\ShopUser;
use App\Models\User;
use App\Services\RbacService;
use App\Support\NepaliNumber;
use App\Tenancy\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UdharReminderTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        TenantContext::reset();
        parent::tearDown();
    }

    private function createShopWithOwner(): array
    {
        $shop = Shop::factory()->create();
        TenantContext::setShop($shop);
        $rbac = app(RbacService::class);
        $roles = $rbac->createDefaultRolesForShop($shop);
        $user = User::factory()->create(['locale' => 'en']);
        ShopUser::create([
            'shop_id' => $shop->id,
            'user_id' => $user->id,
            'role_id' => $roles['Shop Owner']->id,
            'status' => 'active',
        ]);

        return [$shop, $user, $roles];
    }

    private function createCustomerWithBalance(Shop $shop, ?string $phone = '9800000001', string $balance = '100.00'): Customer
    {
        TenantContext::setShop($shop);
        $customer = Customer::factory()->create(['shop_id' => $shop->id, 'phone' => $phone]);

        CustomerLedgerEntry::create([
            'shop_id' => $shop->id,
            'customer_id' => $customer->id,
            'entry_type' => LedgerEntryType::ManualDebit->value,
            'debit' => $balance,
            'credit' => '0.00',
            'created_by' => null,
        ]);

        return $customer;
    }

    public function test_u1_happy_path_redirects_to_exact_wa_me_url(): void
    {
        app()->setLocale('ne');
        [$shop, $user] = $this->createShopWithOwner();
        $user->update(['locale' => 'ne']);
        $customer = $this->createCustomerWithBalance($shop);

        $amountText = NepaliNumber::formatNpr('100.00');
        $message = __('udhar.reminder_whatsapp_message', [
            'shop' => $shop->name,
            'customer' => $customer->name,
            'amount' => $amountText,
            'date' => '',
        ]);
        $expectedUrl = 'https://wa.me/9779800000001?text='.rawurlencode($message);

        $response = $this->actingAs($user)
            ->withSession(['current_shop_id' => $shop->id])
            ->post(route('udhar.reminders.send', $customer));

        $response->assertRedirect($expectedUrl);
    }

    public function test_u1b_devanagari_name_is_encoded(): void
    {
        [$shop, $user] = $this->createShopWithOwner();
        $customer = $this->createCustomerWithBalance($shop, '9800000002', '50.00');
        $customer->update(['name' => 'राम श्रेष्ठ']);

        $response = $this->actingAs($user)
            ->withSession(['current_shop_id' => $shop->id])
            ->post(route('udhar.reminders.send', $customer));

        $this->assertStringContainsString('9779800000002', (string) $response->headers->get('Location'));
        $this->assertStringContainsString(rawurlencode('राम श्रेष्ठ'), (string) $response->headers->get('Location'));
    }

    public function test_u2_creates_reminder_log_row(): void
    {
        [$shop, $user] = $this->createShopWithOwner();
        $customer = $this->createCustomerWithBalance($shop);

        $this->actingAs($user)
            ->withSession(['current_shop_id' => $shop->id])
            ->post(route('udhar.reminders.send', $customer));

        $log = ReminderLog::where('shop_id', $shop->id)->firstOrFail();
        $this->assertSame($customer->id, $log->customer_id);
        $this->assertSame($user->id, $log->user_id);
        $this->assertSame('whatsapp', $log->channel);
        $this->assertSame('opened', $log->status);
        $this->assertSame('100.00', (string) $log->amount);
    }

    public function test_u3_zero_outstanding_does_not_redirect_or_log(): void
    {
        [$shop, $user] = $this->createShopWithOwner();
        TenantContext::setShop($shop);
        $customer = Customer::factory()->create(['shop_id' => $shop->id, 'phone' => '9800000003']);

        $response = $this->actingAs($user)
            ->withSession(['current_shop_id' => $shop->id])
            ->post(route('udhar.reminders.send', $customer));

        $response->assertSessionHas('error');
        $this->assertStringNotContainsString('wa.me', (string) $response->headers->get('Location'));
        $this->assertSame(0, ReminderLog::where('shop_id', $shop->id)->count());
    }

    public function test_u4_invalid_phone_does_not_redirect_or_log(): void
    {
        [$shop, $user] = $this->createShopWithOwner();
        $customer = $this->createCustomerWithBalance($shop, null, '100.00');
        $customer->update(['phone' => null]);

        $response = $this->actingAs($user)
            ->withSession(['current_shop_id' => $shop->id])
            ->post(route('udhar.reminders.send', $customer));

        $response->assertSessionHas('error');
        $this->assertSame(0, ReminderLog::where('shop_id', $shop->id)->count());

        $customer2 = $this->createCustomerWithBalance($shop, '0141234567', '100.00');
        $response2 = $this->actingAs($user)
            ->withSession(['current_shop_id' => $shop->id])
            ->post(route('udhar.reminders.send', $customer2));

        $response2->assertSessionHas('error');
    }

    public function test_u5_other_shop_customer_returns_404_and_no_log(): void
    {
        [$shop, $user] = $this->createShopWithOwner();
        $shop2 = Shop::factory()->create();
        TenantContext::setShop($shop2);
        $customer2 = Customer::factory()->create(['shop_id' => $shop2->id, 'phone' => '9800000005']);
        CustomerLedgerEntry::create([
            'shop_id' => $shop2->id,
            'customer_id' => $customer2->id,
            'entry_type' => LedgerEntryType::ManualDebit->value,
            'debit' => '100.00',
            'credit' => '0.00',
        ]);
        TenantContext::setShop($shop);

        $response = $this->actingAs($user)
            ->withSession(['current_shop_id' => $shop->id])
            ->post(route('udhar.reminders.send', $customer2));

        $response->assertStatus(404);
        $this->assertSame(0, ReminderLog::where('shop_id', $shop->id)->count());
    }

    public function test_u6_role_matrix(): void
    {
        [$shop, $owner, $roles] = $this->createShopWithOwner();
        $customer = $this->createCustomerWithBalance($shop);

        foreach (array_keys($roles) as $roleName) {
            $roleUser = User::factory()->create();
            ShopUser::create([
                'shop_id' => $shop->id,
                'user_id' => $roleUser->id,
                'role_id' => $roles[$roleName]->id,
                'status' => 'active',
            ]);

            $response = $this->actingAs($roleUser)
                ->withSession(['current_shop_id' => $shop->id])
                ->post(route('udhar.reminders.send', $customer));

            $hasPermission = in_array($roleName, ['Shop Owner', 'Manager'], true);
            if ($hasPermission) {
                $this->assertSame(302, $response->getStatusCode(), $roleName);
                $this->assertStringContainsString('wa.me', (string) $response->headers->get('Location'));
            } else {
                $this->assertSame(403, $response->getStatusCode(), $roleName);
            }
        }
    }

    public function test_u7_guests_and_unverified_are_blocked(): void
    {
        [$shop] = $this->createShopWithOwner();
        TenantContext::setShop($shop);
        $customer = $this->createCustomerWithBalance($shop);

        $guestResponse = $this->post(route('udhar.reminders.send', $customer), ['_token' => csrf_token()]);
        $guestResponse->assertRedirect(route('login'));

        $unverified = User::factory()->unverified()->create();
        ShopUser::create([
            'shop_id' => $shop->id,
            'user_id' => $unverified->id,
            'role_id' => app(RbacService::class)->createDefaultRolesForShop($shop)['Shop Owner']->id,
            'status' => 'active',
        ]);
        TenantContext::setShop($shop);
        $unverifiedResponse = $this->actingAs($unverified)
            ->withSession(['current_shop_id' => $shop->id])
            ->post(route('udhar.reminders.send', $customer));

        $this->assertNotSame(200, $unverifiedResponse->getStatusCode());
    }

    public function test_u8_throttle_returns_429_on_31st_request(): void
    {
        [$shop, $user] = $this->createShopWithOwner();
        $customer = $this->createCustomerWithBalance($shop);

        for ($i = 0; $i < 30; $i++) {
            CustomerLedgerEntry::create([
                'shop_id' => $shop->id,
                'customer_id' => $customer->id,
                'entry_type' => LedgerEntryType::ManualDebit->value,
                'debit' => '1.00',
                'credit' => '0.00',
            ]);
            $this->actingAs($user)
                ->withSession(['current_shop_id' => $shop->id])
                ->post(route('udhar.reminders.send', $customer));
        }

        $response = $this->actingAs($user)
            ->withSession(['current_shop_id' => $shop->id])
            ->post(route('udhar.reminders.send', $customer));

        $this->assertSame(429, $response->getStatusCode());
    }

    public function test_u9_last_reminded_text_appears(): void
    {
        [$shop, $user] = $this->createShopWithOwner();
        $customer = $this->createCustomerWithBalance($shop);

        $this->actingAs($user)
            ->withSession(['current_shop_id' => $shop->id])
            ->post(route('udhar.reminders.send', $customer));

        $response = $this->actingAs($user)
            ->withSession(['current_shop_id' => $shop->id])
            ->get(route('udhar.outstanding'));

        $response->assertOk();
        $this->assertStringContainsString(trim(__('udhar.last_reminded', ['time' => ''])), $response->getContent());
    }

    public function test_u10_posted_body_cannot_override_phone_amount_or_message(): void
    {
        [$shop, $user] = $this->createShopWithOwner();
        $customer = $this->createCustomerWithBalance($shop);

        $this->actingAs($user)
            ->withSession(['current_shop_id' => $shop->id])
            ->post(route('udhar.reminders.send', $customer), [
                'phone' => '9999999999',
                'amount' => '999.99',
                'message' => 'hacked',
            ]);

        $log = ReminderLog::where('shop_id', $shop->id)->firstOrFail();
        $this->assertSame('100.00', (string) $log->amount);
        $this->assertStringNotContainsString('hacked', (string) $log->message);
    }

    public function test_u11_both_locales_pass(): void
    {
        [$shop, $user] = $this->createShopWithOwner();
        $customer = $this->createCustomerWithBalance($shop);

        foreach (['en', 'ne'] as $locale) {
            app()->setLocale($locale);
            $response = $this->actingAs($user)
                ->withSession(['current_shop_id' => $shop->id, 'locale' => $locale])
                ->post(route('udhar.reminders.send', $customer));

            $this->assertSame(302, $response->getStatusCode(), $locale);
            $this->assertStringContainsString('wa.me', (string) $response->headers->get('Location'), $locale);
        }
    }
}
