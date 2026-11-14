<?php

declare(strict_types=1);

namespace Tests\Feature\Security;

use App\Models\Customer;
use App\Models\Product;
use App\Models\Shop;
use App\Models\ShopUser;
use App\Models\Unit;
use App\Models\User;
use App\Services\RbacService;
use App\Tenancy\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * T7a: the POS renders server data with innerHTML, so the client must escape it.
 *
 * Stored payloads must never be interpolated raw into a POS template; otherwise
 * a product or customer name becomes a DOM-XSS vector even though Blade escapes.
 */
final class PosClientEscapingTest extends TestCase
{
    use RefreshDatabase;

    private const PAYLOAD = '<script>alert(1)</script>"><img src=x onerror=alert(1)>';

    private Shop $shop;

    private User $owner;

    private Product $product;

    private Customer $customer;

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

        $unit = Unit::firstOrCreate(['symbol' => 'pcs'], ['name' => 'Piece', 'is_fractional' => false]);

        $this->product = Product::factory()->create([
            'shop_id' => $this->shop->id,
            'unit_id' => $unit->id,
            'name' => self::PAYLOAD,
            'name_ne' => self::PAYLOAD,
            'sku' => 'XSS-SKU',
            'selling_price' => '25.00',
            'is_active' => true,
        ]);

        $this->customer = Customer::factory()->create([
            'shop_id' => $this->shop->id,
            'name' => self::PAYLOAD,
            'phone' => '9800000001',
        ]);
    }

    protected function tearDown(): void
    {
        TenantContext::reset();
        parent::tearDown();
    }

    public function test_pos_client_escapes_every_server_supplied_string_before_inner_html(): void
    {
        $html = (string) $this->actingAs($this->owner)
            ->withSession(['current_shop_id' => $this->shop->id])
            ->get(route('pos'))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('function escapeHtml(', $html, 'POS must define an HTML escaping helper.');

        $rawInterpolations = [
            '${p.name}',
            '${p.name_ne}',
            '${p.sku',
            '${p.unit',
            '${c.name}',
            '${c.phone',
            '${item.name}',
        ];

        foreach ($rawInterpolations as $raw) {
            $this->assertStringNotContainsString(
                $raw,
                $html,
                'Unescaped interpolation '.$raw.' is written into POS innerHTML.'
            );
        }

        $escapedInterpolations = [
            'escapeHtml(p.name)',
            'escapeHtml(p.name_ne)',
            'escapeHtml(p.sku',
            'escapeHtml(p.unit',
            'escapeHtml(c.name)',
            'escapeHtml(c.phone',
            'escapeHtml(item.name)',
        ];

        foreach ($escapedInterpolations as $escaped) {
            $this->assertStringContainsString($escaped, $html, 'POS must escape '.$escaped.'.');
        }
    }

    public function test_pos_json_endpoints_still_deliver_the_stored_payload_to_the_client(): void
    {
        $this->actingAs($this->owner)->withSession(['current_shop_id' => $this->shop->id]);

        $products = $this->get(route('pos.products', ['q' => 'XSS-SKU']))->assertOk()->json();
        $this->assertSame(self::PAYLOAD, $products[0]['name'] ?? null);
        $this->assertSame(self::PAYLOAD, $products[0]['name_ne'] ?? null);

        $customers = $this->get(route('pos.customers', ['q' => '9800000001']))->assertOk()->json();
        $this->assertSame(self::PAYLOAD, $customers[0]['name'] ?? null);
    }
}
