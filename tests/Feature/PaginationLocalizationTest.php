<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Product;
use App\Models\Shop;
use App\Models\ShopUser;
use App\Models\User;
use App\Services\RbacService;
use App\Tenancy\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PaginationLocalizationTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        TenantContext::reset();
        parent::tearDown();
    }

    public function test_nepali_product_pagination_has_one_localized_summary_and_page_two_works(): void
    {
        app()->setLocale('ne');
        $shop = Shop::factory()->create(['settings' => ['devanagari_digits' => true]]);
        TenantContext::setShop($shop);
        $roles = app(RbacService::class)->createDefaultRolesForShop($shop);
        $user = User::factory()->create();
        ShopUser::factory()->create([
            'shop_id' => $shop->id,
            'user_id' => $user->id,
            'role_id' => $roles['Shop Owner']->id,
            'status' => 'active',
        ]);
        Product::factory()->count(30)->create(['shop_id' => $shop->id]);

        $response = $this->actingAs($user)
            ->withSession(['current_shop_id' => $shop->id])
            ->get(route('products.index', ['page' => 2]));

        $response->assertOk();
        foreach (['Previous', 'Next', 'Showing', 'results'] as $englishLabel) {
            $response->assertDontSee($englishLabel);
        }
        $response->assertSee('pagination-summary');
        self::assertSame(1, substr_count($response->getContent(), 'pagination-summary'));
        $response->assertSee('aria-current="page"', false);
        self::assertStringContainsString('१६', $response->getContent());
    }
}
