<?php

declare(strict_types=1);

namespace Tests\Feature\Shop;

use App\Actions\Sales\CreateSale;
use App\Actions\Sales\PostSale;
use App\Models\Category;
use App\Models\Product;
use App\Models\Shop;
use App\Models\ShopUser;
use App\Models\Unit;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\RbacService;
use App\Tenancy\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PrintingSettingsTest extends TestCase
{
    use RefreshDatabase;

    private Shop $shop;

    private User $owner;

    private User $cashier;

    private Warehouse $warehouse;

    private Unit $unit;

    private Category $category;

    protected function setUp(): void
    {
        parent::setUp();
        $this->shop = Shop::factory()->create();
        TenantContext::setShop($this->shop);

        $rbac = app(RbacService::class);
        $roles = $rbac->createDefaultRolesForShop($this->shop);

        $this->owner = User::factory()->create();
        ShopUser::factory()->create([
            'shop_id' => $this->shop->id,
            'user_id' => $this->owner->id,
            'role_id' => $roles['Shop Owner']->id,
            'status' => 'active',
        ]);

        $this->cashier = User::factory()->create();
        ShopUser::factory()->create([
            'shop_id' => $this->shop->id,
            'user_id' => $this->cashier->id,
            'role_id' => $roles['Cashier']->id,
            'status' => 'active',
        ]);

        $this->warehouse = Warehouse::factory()->create([
            'shop_id' => $this->shop->id,
            'is_default' => true,
        ]);

        $this->unit = Unit::firstOrCreate(['symbol' => 'pkt'], [
            'name' => 'Packet',
            'is_fractional' => false,
        ]);

        $this->category = Category::factory()->create(['shop_id' => $this->shop->id]);
    }

    protected function tearDown(): void
    {
        TenantContext::reset();
        parent::tearDown();
    }

    private function actingOwner()
    {
        return $this->actingAs($this->owner)->withSession(['current_shop_id' => $this->shop->id]);
    }

    public function test_thermal_paper_width_valid_and_invalid(): void
    {
        $this->actingOwner()->put(route('settings.shop.update'), [
            'name' => $this->shop->name,
            'business_type' => $this->shop->business_type,
            'timezone' => $this->shop->timezone,
            'thermal_paper_width' => '58',
        ])->assertRedirect()->assertSessionHasNoErrors();

        $this->assertSame('58', $this->shop->fresh()->settings['thermal_paper_width']);

        $this->actingOwner()->put(route('settings.shop.update'), [
            'name' => $this->shop->name,
            'business_type' => $this->shop->business_type,
            'timezone' => $this->shop->timezone,
            'thermal_paper_width' => '70',
        ])->assertSessionHasErrors('thermal_paper_width');
    }

    public function test_invoice_footer_length_and_escaping(): void
    {
        $footer = '<script>alert(1)</script>';
        $this->actingOwner()->put(route('settings.shop.update'), [
            'name' => $this->shop->name,
            'business_type' => $this->shop->business_type,
            'timezone' => $this->shop->timezone,
            'invoice_footer' => str_repeat('a', 201),
        ])->assertSessionHasErrors('invoice_footer');

        $this->actingOwner()->put(route('settings.shop.update'), [
            'name' => $this->shop->name,
            'business_type' => $this->shop->business_type,
            'timezone' => $this->shop->timezone,
            'invoice_footer' => $footer,
        ])->assertRedirect()->assertSessionHasNoErrors();

        $product = Product::factory()->create([
            'shop_id' => $this->shop->id,
            'category_id' => $this->category->id,
            'unit_id' => $this->unit->id,
            'name' => 'Juice',
            'selling_price' => '120.00',
            'track_stock' => false,
        ]);
        $sale = app(CreateSale::class)->handle([
            'warehouse_id' => $this->warehouse->id,
            'items' => [[
                'product_id' => $product->id,
                'quantity' => '1.000',
                'unit_price' => '120.00',
            ]],
        ]);
        $posted = app(PostSale::class)->handle($sale, $this->owner->id, [
            'payment_method' => 'cash',
            'payment_amount' => '120.00',
        ]);

        $resInvoice = $this->actingOwner()->get(route('sales.invoice', $posted));
        $resInvoice->assertStatus(200);
        $resInvoice->assertSee('&lt;script&gt;alert(1)&lt;/script&gt;', false);

        $resThermal = $this->actingOwner()->get(route('sales.thermal', $posted));
        $resThermal->assertStatus(200);
        $resThermal->assertSee('&lt;script&gt;alert(1)&lt;/script&gt;', false);
    }

    public function test_logo_upload_validation_and_storage(): void
    {
        Storage::fake('public');

        $file = UploadedFile::fake()->image('logo.png', 10, 10);
        $this->actingOwner()->put(route('settings.shop.update'), [
            'name' => $this->shop->name,
            'business_type' => $this->shop->business_type,
            'timezone' => $this->shop->timezone,
            'logo' => $file,
        ])->assertRedirect()->assertSessionHasNoErrors();

        $path = $this->shop->fresh()->settings['logo_path'] ?? null;
        $this->assertIsString($path);
        $this->assertTrue(Storage::disk('public')->exists($path));

        // invalid: svg
        $svg = UploadedFile::fake()->create('evil.svg', 1, 'image/svg+xml');
        $this->actingOwner()->put(route('settings.shop.update'), [
            'name' => $this->shop->name,
            'business_type' => $this->shop->business_type,
            'timezone' => $this->shop->timezone,
            'logo' => $svg,
        ])->assertSessionHasErrors('logo');

        // invalid: gif
        $gif = UploadedFile::fake()->image('evil.gif');
        $this->actingOwner()->put(route('settings.shop.update'), [
            'name' => $this->shop->name,
            'business_type' => $this->shop->business_type,
            'timezone' => $this->shop->timezone,
            'logo' => $gif,
        ])->assertSessionHasErrors('logo');

        // invalid: php renamed to png
        $php = UploadedFile::fake()->create('shell.png', 1, 'application/x-httpd-php');
        $this->actingOwner()->put(route('settings.shop.update'), [
            'name' => $this->shop->name,
            'business_type' => $this->shop->business_type,
            'timezone' => $this->shop->timezone,
            'logo' => $php,
        ])->assertSessionHasErrors('logo');

        // invalid: oversized
        $big = UploadedFile::fake()->image('big.png')->size(2000);
        $this->actingOwner()->put(route('settings.shop.update'), [
            'name' => $this->shop->name,
            'business_type' => $this->shop->business_type,
            'timezone' => $this->shop->timezone,
            'logo' => $big,
        ])->assertSessionHasErrors('logo');

        // invalid: non-image extension
        $txt = UploadedFile::fake()->create('note.txt', 1, 'text/plain');
        $this->actingOwner()->put(route('settings.shop.update'), [
            'name' => $this->shop->name,
            'business_type' => $this->shop->business_type,
            'timezone' => $this->shop->timezone,
            'logo' => $txt,
        ])->assertSessionHasErrors('logo');
    }

    public function test_logo_replace_and_remove(): void
    {
        Storage::fake('public');

        $first = UploadedFile::fake()->image('first.png', 10, 10);
        $this->actingOwner()->put(route('settings.shop.update'), [
            'name' => $this->shop->name,
            'business_type' => $this->shop->business_type,
            'timezone' => $this->shop->timezone,
            'logo' => $first,
        ])->assertRedirect()->assertSessionHasNoErrors();
        $firstPath = $this->shop->fresh()->settings['logo_path'];

        $second = UploadedFile::fake()->image('second.png', 10, 10);
        $this->actingOwner()->put(route('settings.shop.update'), [
            'name' => $this->shop->name,
            'business_type' => $this->shop->business_type,
            'timezone' => $this->shop->timezone,
            'logo' => $second,
        ])->assertRedirect()->assertSessionHasNoErrors();
        $secondPath = $this->shop->fresh()->settings['logo_path'];

        $this->assertNotSame($firstPath, $secondPath);
        $this->assertFalse(Storage::disk('public')->exists($firstPath));
        $this->assertTrue(Storage::disk('public')->exists($secondPath));

        $this->actingOwner()->put(route('settings.shop.update'), [
            'name' => $this->shop->name,
            'business_type' => $this->shop->business_type,
            'timezone' => $this->shop->timezone,
            'remove_logo' => '1',
        ])->assertRedirect()->assertSessionHasNoErrors();

        $this->assertArrayNotHasKey('logo_path', $this->shop->fresh()->settings ?? []);
        $this->assertFalse(Storage::disk('public')->exists($secondPath));
    }

    public function test_logo_tenant_isolation(): void
    {
        Storage::fake('public');

        $otherShop = Shop::factory()->create();
        TenantContext::setShop($otherShop);
        $rbac = app(RbacService::class);
        $roles = $rbac->createDefaultRolesForShop($otherShop);
        $otherUser = User::factory()->create();
        ShopUser::factory()->create([
            'shop_id' => $otherShop->id,
            'user_id' => $otherUser->id,
            'role_id' => $roles['Shop Owner']->id,
            'status' => 'active',
        ]);

        // upload logo for other shop
        $this->actingAs($otherUser)->withSession(['current_shop_id' => $otherShop->id])
            ->put(route('settings.shop.update'), [
                'name' => $otherShop->name,
                'business_type' => $otherShop->business_type,
                'timezone' => $otherShop->timezone,
                'logo' => UploadedFile::fake()->image('other.png', 10, 10),
            ])->assertRedirect()->assertSessionHasNoErrors();

        TenantContext::setShop($this->shop);

        // original shop logo remains unset
        $this->assertArrayNotHasKey('logo_path', $this->shop->fresh()->settings ?? []);
    }

    public function test_settings_permission_cashier_denied(): void
    {
        $this->actingAs($this->cashier)->withSession(['current_shop_id' => $this->shop->id])
            ->put(route('settings.shop.update'), [
                'name' => $this->shop->name,
                'business_type' => $this->shop->business_type,
                'timezone' => $this->shop->timezone,
            ])->assertStatus(403);
    }

    public function test_thermal_width_rendering_and_a4_stylesheet(): void
    {
        $product = Product::factory()->create([
            'shop_id' => $this->shop->id,
            'category_id' => $this->category->id,
            'unit_id' => $this->unit->id,
            'name' => 'Juice',
            'selling_price' => '120.00',
            'track_stock' => false,
        ]);

        // 80 default
        $sale = app(CreateSale::class)->handle([
            'warehouse_id' => $this->warehouse->id,
            'items' => [[
                'product_id' => $product->id,
                'quantity' => '1.000',
                'unit_price' => '120.00',
            ]],
        ]);
        $posted = app(PostSale::class)->handle($sale, $this->owner->id, [
            'payment_method' => 'cash',
            'payment_amount' => '120.00',
        ]);

        $res80 = $this->actingOwner()->get(route('sales.thermal', $posted));
        $res80->assertStatus(200);
        $res80->assertSee('80mm');

        // 58
        $this->shop->update(['settings' => array_merge($this->shop->settings ?? [], ['thermal_paper_width' => '58'])]);
        $res58 = $this->actingOwner()->get(route('sales.thermal', $posted));
        $res58->assertStatus(200);
        $res58->assertSee('58mm');

        $resInvoice = $this->actingOwner()->get(route('sales.invoice', $posted));
        $resInvoice->assertStatus(200);
        $resInvoice->assertSee('size: A4', false);
    }

    public function test_footer_and_logo_render_and_missing_logo_no_crash(): void
    {
        Storage::fake('public');
        $logo = UploadedFile::fake()->image('logo.png', 10, 10);
        $this->actingOwner()->put(route('settings.shop.update'), [
            'name' => $this->shop->name,
            'business_type' => $this->shop->business_type,
            'timezone' => $this->shop->timezone,
            'invoice_footer' => 'Thank you!',
            'logo' => $logo,
        ])->assertRedirect()->assertSessionHasNoErrors();

        $product = Product::factory()->create([
            'shop_id' => $this->shop->id,
            'category_id' => $this->category->id,
            'unit_id' => $this->unit->id,
            'name' => 'Juice',
            'selling_price' => '120.00',
            'track_stock' => false,
        ]);
        $sale = app(CreateSale::class)->handle([
            'warehouse_id' => $this->warehouse->id,
            'items' => [[
                'product_id' => $product->id,
                'quantity' => '1.000',
                'unit_price' => '120.00',
            ]],
        ]);
        $posted = app(PostSale::class)->handle($sale, $this->owner->id, [
            'payment_method' => 'cash',
            'payment_amount' => '120.00',
        ]);

        $resInvoice = $this->actingOwner()->get(route('sales.invoice', $posted));
        $resInvoice->assertStatus(200);
        $resInvoice->assertSee('Thank you!');
        $resInvoice->assertSee('storage/', false);

        $resThermal = $this->actingOwner()->get(route('sales.thermal', $posted));
        $resThermal->assertStatus(200);
        $resThermal->assertSee('Thank you!');

        // missing logo file does not crash
        $this->shop->update(['settings' => array_merge($this->shop->settings ?? [], ['logo_path' => 'shops/'.$this->shop->id.'/logo/missing.png'])]);
        $this->actingOwner()->get(route('sales.invoice', $posted))->assertStatus(200);
        $this->actingOwner()->get(route('sales.thermal', $posted))->assertStatus(200);
    }
}
