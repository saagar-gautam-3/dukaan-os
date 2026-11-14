<?php

declare(strict_types=1);

namespace Tests\Feature\Reports;

use App\Actions\Inventory\QueryStockAlerts;
use App\Models\Permission;
use App\Models\Shop;
use App\Models\ShopUser;
use App\Models\User;
use App\Services\RbacService;
use App\Tenancy\TenantContext;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class DailySummaryPageTest extends TestCase
{
    use RefreshDatabase;

    private Shop $shop;

    protected function setUp(): void
    {
        parent::setUp();

        $this->shop = Shop::factory()->create(['timezone' => 'Asia/Kathmandu', 'name' => 'Annapurna Test Shop']);
        TenantContext::setShop($this->shop);
    }

    protected function tearDown(): void
    {
        TenantContext::reset();
        parent::tearDown();
    }

    public function test_role_matrix_for_summary_route(): void
    {
        foreach (['Shop Owner', 'Manager', 'Accountant'] as $role) {
            $user = $this->memberWithRole($role);
            $response = $this->actingAs($user)
                ->withSession(['current_shop_id' => $this->shop->id])
                ->get(route('reports.daily-summary'));

            $response->assertOk();
        }

        foreach (['Cashier', 'Inventory Staff'] as $role) {
            $user = $this->memberWithRole($role);
            $response = $this->actingAs($user)
                ->withSession(['current_shop_id' => $this->shop->id])
                ->get(route('reports.daily-summary'));

            $response->assertForbidden();
        }
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get(route('reports.daily-summary'))->assertRedirect(route('login'));
    }

    public function test_other_shop_user_never_sees_this_shops_numbers(): void
    {
        $otherShop = Shop::factory()->create(['name' => 'Other Shop']);
        $otherUser = $this->memberWithRole('Shop Owner', $otherShop);

        $response = $this->actingAs($otherUser)
            ->withSession(['current_shop_id' => $otherShop->id])
            ->get(route('reports.daily-summary').'?date='.Carbon::now('Asia/Kathmandu')->toDateString());

        // The visitor stays in their own tenant (or gets denied): this shop's
        // figures must never appear in the response.
        $this->assertContains($response->getStatusCode(), [200, 302, 403]);
        $this->assertStringNotContainsString('Annapurna Test Shop', (string) $response->getContent());
    }

    public function test_invalid_and_future_dates_fail_validation(): void
    {
        $user = $this->memberWithRole('Shop Owner');

        $this->actingAs($user)
            ->withSession(['current_shop_id' => $this->shop->id])
            ->get(route('reports.daily-summary', ['date' => 'not-a-date']))
            ->assertSessionHasErrors('date');

        $future = Carbon::now('Asia/Kathmandu')->addDays(2)->toDateString();
        $this->actingAs($user)
            ->withSession(['current_shop_id' => $this->shop->id])
            ->get(route('reports.daily-summary', ['date' => $future]))
            ->assertSessionHasErrors('date');
    }

    public function test_whatsapp_url_decodes_to_expected_text(): void
    {
        $user = $this->memberWithRole('Shop Owner');

        $response = $this->actingAs($user)
            ->withSession(['current_shop_id' => $this->shop->id])
            ->get(route('reports.daily-summary'));

        $response->assertOk();
        preg_match('/https:\/\/wa\.me\/\?text=([^"&]+)/', (string) $response->getContent(), $matches);
        $this->assertNotEmpty($matches, 'WhatsApp share link must be rendered.');

        $text = urldecode((string) $matches[1]);
        $this->assertStringContainsString('Annapurna Test Shop', $text);
        $this->assertStringContainsString('Bills:', $text);
        $this->assertStringContainsString('Rs. 0.00', $text);
        $this->assertLessThanOrEqual(900, mb_strlen($text));
    }

    public function test_suggested_quantity_rules(): void
    {
        $this->assertSame('8.000', QueryStockAlerts::suggestedQuantity('5.000', '2.000', true));
        // Fractionless unit rounds to whole numbers.
        $this->assertSame('8', QueryStockAlerts::suggestedQuantity('5.000', '1.500', false));
        // Never below one.
        $this->assertSame('1.000', QueryStockAlerts::suggestedQuantity('0.500', '0.000', true));
        $this->assertSame('1', QueryStockAlerts::suggestedQuantity('0.500', '0.000', false));
    }

    public function test_owner_on_empty_day_sees_all_sections_with_zeros(): void
    {
        $user = $this->memberWithRole('Shop Owner');

        $response = $this->actingAs($user)
            ->withSession(['current_shop_id' => $this->shop->id])
            ->get(route('reports.daily-summary', ['date' => Carbon::now('Asia/Kathmandu')->toDateString()]));

        $response->assertOk();
        $html = (string) $response->getContent();
        // Every section renders even with zero figures — never hidden on empty data.
        $this->assertStringContainsString(__('daily.sales'), $html);
        $this->assertStringContainsString(__('daily.udhar'), $html);
        $this->assertStringContainsString(__('daily.cash'), $html);
        $this->assertStringContainsString(__('daily.expenses'), $html);
        $this->assertStringContainsString(__('daily.purchases'), $html);
        $this->assertStringContainsString(__('daily.stock'), $html);
        $this->assertStringContainsString(__('daily.no_activity'), $html);
        $this->assertStringContainsString('Rs. 0.00', $html);
    }

    public function test_role_missing_one_permission_loses_only_that_section(): void
    {
        $roles = app(RbacService::class)->createDefaultRolesForShop($this->shop);
        $user = User::factory()->create(['locale' => 'en']);
        $member = ShopUser::create([
            'shop_id' => $this->shop->id,
            'user_id' => $user->id,
            'role_id' => $roles['Shop Owner']->id,
            'status' => 'active',
        ]);
        $member->role->permissions()->sync(Permission::where('name', '!=', 'expenses.view')->pluck('id')->all());
        auth()->logout();

        $response = $this->actingAs($user)
            ->withSession(['current_shop_id' => $this->shop->id])
            ->get(route('reports.daily-summary', ['date' => Carbon::now('Asia/Kathmandu')->toDateString()]));

        $response->assertOk();
        $html = (string) $response->getContent();
        $this->assertStringContainsString(__('daily.sales'), $html);
        $this->assertStringContainsString(__('daily.purchases'), $html);
        // Expenses is the one permission-less section: its distinctive body
        // content ('Top expense categories' + the expense totals) must be absent.
        $this->assertStringNotContainsString(__('daily.top_categories'), $html);
        $this->assertStringContainsString('Daily Summary', $html);
    }

    public function test_route_and_date_heading_structure(): void
    {
        $user = $this->memberWithRole('Shop Owner');

        $response = $this->actingAs($user)
            ->withSession(['current_shop_id' => $this->shop->id])
            ->get(route('reports.daily-summary', ['date' => Carbon::now('Asia/Kathmandu')->toDateString()]));

        $response->assertOk();
        $html = (string) $response->getContent();
        // Date control row: prev/date/next/Today + WhatsApp separate.
        $this->assertStringContainsString('dailyDateInput', $html);
        $this->assertStringContainsString('wa.me', $html);
        // Heading is a readable localized date (not ISO).
        $this->assertMatchesRegularExpression('/\d{1,2} Nov 2026/', $html);
    }

    private function memberWithRole(string $role, ?Shop $shop = null): User
    {
        $shop ??= $this->shop;
        $roles = app(RbacService::class)->createDefaultRolesForShop($shop);
        $user = User::factory()->create(['locale' => 'en']);

        ShopUser::create([
            'shop_id' => $shop->id,
            'user_id' => $user->id,
            'role_id' => $roles[$role]->id,
            'status' => 'active',
        ]);

        return $user;
    }
}
