<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Shop;
use App\Models\ShopUser;
use App\Models\Supplier;
use App\Models\User;
use App\Services\RbacService;
use App\Support\NepaliPhone;
use App\Tenancy\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class PhoneValidationTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        TenantContext::reset();
        parent::tearDown();
    }

    public function test_valid_phone_formats_normalize_and_persist_across_phone_forms(): void
    {
        [$shop, $user] = $this->tenantOwner();

        $this->tenantRequest($user, $shop)->put(route('settings.shop.update'), [
            'name' => $shop->name,
            'business_type' => $shop->business_type,
            'timezone' => $shop->timezone,
            'phone' => '+977 ९८४-१२३-४५६७',
        ])->assertSessionHasNoErrors();
        self::assertSame('9841234567', $shop->fresh()->settings['phone']);

        $this->tenantRequest($user, $shop)->post(route('customers.store'), [
            'name' => 'Phone Test Customer',
            'phone' => '+९७७ ९८५-१२३-४५६७',
        ])->assertSessionHasNoErrors();
        self::assertDatabaseHas('customers', ['phone' => '9851234567']);
        $customer = Customer::query()->where('phone', '9851234567')->firstOrFail();
        $this->tenantRequest($user, $shop)->put(route('customers.update', $customer), [
            'name' => $customer->name,
            'phone' => '९८८ १२३-४५६७',
        ])->assertSessionHasNoErrors();
        self::assertSame('9881234567', $customer->fresh()->phone);

        $this->tenantRequest($user, $shop)->post(route('suppliers.store'), [
            'name' => 'Phone Test Supplier',
            'phone' => '९८६-१२३-४५६७',
        ])->assertSessionHasNoErrors();
        self::assertDatabaseHas('suppliers', ['phone' => '9861234567']);
        $supplier = Supplier::query()->where('phone', '9861234567')->firstOrFail();
        $this->tenantRequest($user, $shop)->put(route('suppliers.update', $supplier), [
            'name' => $supplier->name,
            'phone' => '+977-9891234567',
        ])->assertSessionHasNoErrors();
        self::assertSame('9891234567', $supplier->fresh()->phone);

    }

    public function test_registration_normalizes_valid_phone_before_persisting(): void
    {
        $this->post(route('register'), [
            'name' => 'Phone Test User',
            'email' => 'phone-test@example.test',
            'phone' => '+977 ९८७-१२३-४५६७',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'shop_name' => 'Phone Test Shop',
            'business_type' => 'kirana',
        ])->assertRedirect(route('dashboard'))->assertSessionHasNoErrors();
        self::assertDatabaseHas('users', ['email' => 'phone-test@example.test', 'phone' => '9871234567']);
    }

    public function test_invalid_phone_values_are_rejected_by_every_phone_route_without_server_errors(): void
    {
        [$shop, $user] = $this->tenantOwner();
        $supplier = Supplier::factory()->create(['shop_id' => $shop->id]);
        $customer = Customer::factory()->create(['shop_id' => $shop->id]);
        $invalidValues = [
            '28_digit' => '9899999999999999999999999999',
            'letters' => '98ABC12345',
            'nine_digits' => '984123456',
            'eleven_digits' => '98412345678',
        ];

        foreach ($invalidValues as $label => $phone) {
            $requests = [
                'shop settings' => fn () => $this->tenantRequest($user, $shop)->put(route('settings.shop.update'), $this->shopPayload($shop, $phone)),
                'customer create' => fn () => $this->tenantRequest($user, $shop)->post(route('customers.store'), ['name' => 'Invalid Customer '.$label, 'phone' => $phone]),
                'customer update' => fn () => $this->tenantRequest($user, $shop)->put(route('customers.update', $customer), ['name' => $customer->name, 'phone' => $phone]),
                'supplier create' => fn () => $this->tenantRequest($user, $shop)->post(route('suppliers.store'), ['name' => 'Invalid Supplier '.$label, 'phone' => $phone]),
                'supplier update' => fn () => $this->tenantRequest($user, $shop)->put(route('suppliers.update', $supplier), ['name' => $supplier->name, 'phone' => $phone]),
                'registration' => function () use ($phone, $label) {
                    auth()->logout();

                    return $this->post(route('register'), [
                        'name' => 'Invalid User '.$label,
                        'email' => 'invalid-'.$label.'@example.test',
                        'phone' => $phone,
                        'password' => 'password123',
                        'password_confirmation' => 'password123',
                        'shop_name' => 'Invalid Phone Shop',
                    ]);
                },
            ];

            foreach ($requests as $form => $makeRequest) {
                $response = $makeRequest();
                self::assertLessThan(500, $response->getStatusCode(), 'Invalid phone request returned a server error.');
                $response->assertSessionHasErrors('phone');
            }
        }
    }

    public function test_nine_digit_mobile_is_invalid_and_kathmandu_landlines_are_not_supported(): void
    {
        self::assertFalse(NepaliPhone::isValidMobile('014422222'));
        self::assertFalse(NepaliPhone::isValidMobile('984123456'));
    }

    public function test_invalid_phone_message_is_localized_in_nepali(): void
    {
        app()->setLocale('ne');
        [$shop, $user] = $this->tenantOwner();

        $this->tenantRequest($user, $shop)->put(route('settings.shop.update'), $this->shopPayload($shop, '98ABC12345'))
            ->assertSessionHasErrors(['phone' => __('common.invalid_nepal_phone')]);
    }

    public function test_phone_columns_are_limited_to_normalized_mobile_length(): void
    {
        foreach (['users', 'customers', 'suppliers'] as $table) {
            $column = DB::selectOne(
                'select character_maximum_length as length from information_schema.columns where table_schema = database() and table_name = ? and column_name = ?',
                [$table, 'phone'],
            );

            self::assertSame(10, (int) $column->length, $table.'.phone must not admit overlong raw values.');
        }
    }

    /** @return array{Shop, User} */
    private function tenantOwner(): array
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

        return [$shop, $user];
    }

    private function tenantRequest(User $user, Shop $shop): static
    {
        return $this->actingAs($user)->withSession(['current_shop_id' => $shop->id]);
    }

    /** @return array<string, mixed> */
    private function shopPayload(Shop $shop, string $phone): array
    {
        return [
            'name' => $shop->name,
            'business_type' => $shop->business_type,
            'timezone' => $shop->timezone,
            'phone' => $phone,
        ];
    }
}
