<?php

declare(strict_types=1);

namespace Tests\Feature\Ui;

use App\Enums\LedgerEntryType;
use App\Models\Customer;
use App\Models\CustomerLedgerEntry;
use App\Models\Sale;
use App\Models\Shop;
use App\Models\ShopUser;
use App\Models\User;
use App\Services\RbacService;
use App\Support\NepaliNumber;
use App\Tenancy\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * One money rule everywhere: digits follow the shop setting
 * settings.devanagari_digits if set, otherwise the locale;
 * amounts always use South-Asian grouping.
 */
final class MoneyFormattingConsistencyTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        TenantContext::reset();
        parent::tearDown();
    }

    public function test_shared_formatter_respects_setting_and_grouping(): void
    {
        $shop = Shop::factory()->create();
        TenantContext::setShop($shop);

        foreach ([false, true] as $on) {
            $shop->update(['settings' => array_merge($shop->settings ?? [], ['devanagari_digits' => $on])]);
            $shop->refresh();

            $htmlOffOrOn = TenantContext::runAs($shop, function (): string {
                app()->setLocale('en');

                return NepaliNumber::formatNpr('123456.00');
            });

            if ($on) {
                $this->assertSame('Rs. १,२३,४५६.००', $htmlOffOrOn);
            } else {
                $this->assertSame('Rs. 1,23,456.00', $htmlOffOrOn);
            }
        }
    }

    public function test_dashboard_and_pos_use_ascii_grouped_money_in_english_when_setting_off(): void
    {
        [$shop, $user] = $this->createOwnerWithData(false);

        $dashboard = $this->actingAs($user)
            ->withSession(['current_shop_id' => $shop->id, 'locale' => 'en'])
            ->get(route('dashboard'));

        $dashboard->assertOk();
        $dashboardHtml = (string) $dashboard->getContent();
        $this->assertStringContainsString('Rs. 8,130.00', $dashboardHtml);
        $this->assertDoesNotMatchRegularExpression('/[०-९]/u', $dashboardHtml, 'English dashboard must not render Devanagari digits with the setting off.');

        $pos = $this->actingAs($user)
            ->withSession(['current_shop_id' => $shop->id, 'locale' => 'en'])
            ->get(route('pos'));

        $pos->assertOk();
        $this->assertStringContainsString('useDevanagariDigits = false', (string) $pos->getContent());
    }

    public function test_dashboard_and_pos_use_devanagari_when_setting_on(): void
    {
        [$shop, $user] = $this->createOwnerWithData(true);

        $dashboard = $this->actingAs($user)
            ->withSession(['current_shop_id' => $shop->id, 'locale' => 'en'])
            ->get(route('dashboard'));

        $dashboard->assertOk();
        $dashboardHtml = (string) $dashboard->getContent();
        $this->assertStringContainsString('Rs. ८,१३०.००', $dashboardHtml);

        $pos = $this->actingAs($user)
            ->withSession(['current_shop_id' => $shop->id, 'locale' => 'en'])
            ->get(route('pos'));

        $pos->assertOk();
        $this->assertStringContainsString('useDevanagariDigits = true', (string) $pos->getContent());
    }

    /** @return array{Shop, User} */
    private function createOwnerWithData(bool $devanagari): array
    {
        $shop = Shop::factory()->create();
        $shop->update(['settings' => array_merge($shop->settings ?? [], ['devanagari_digits' => $devanagari])]);
        TenantContext::setShop($shop->fresh());
        $roles = app(RbacService::class)->createDefaultRolesForShop($shop);
        $user = User::factory()->create(['locale' => 'en']);
        ShopUser::create([
            'shop_id' => $shop->id,
            'user_id' => $user->id,
            'role_id' => $roles['Shop Owner']->id,
            'status' => 'active',
        ]);

        $customer = Customer::factory()->create([
            'shop_id' => $shop->id,
            'name' => 'Ram Bahadur',
            'phone' => '9800000001',
        ]);

        CustomerLedgerEntry::create([
            'shop_id' => $shop->id,
            'customer_id' => $customer->id,
            'entry_type' => LedgerEntryType::ManualDebit->value,
            'debit' => '8130.00',
            'credit' => '0.00',
        ]);

        Sale::factory()->posted()->create([
            'shop_id' => $shop->id,
            'subtotal' => '2100.00',
            'grand_total' => '2100.00',
            'paid_total' => '2100.00',
        ]);

        return [$shop->fresh(), $user];
    }
}
