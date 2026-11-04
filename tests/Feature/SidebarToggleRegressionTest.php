<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Shop;
use App\Models\ShopUser;
use App\Models\User;
use App\Services\RbacService;
use App\Tenancy\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SidebarToggleRegressionTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        TenantContext::reset();

        parent::tearDown();
    }

    public function test_js_sidebar_toggle_selector_exists_in_topbar_layout(): void
    {
        $appJs = file_get_contents(resource_path('js/app.js'));
        $this->assertIsString($appJs);

        preg_match("/getElementById\\('([^']+)'\\)/", $appJs, $matches);
        $this->assertNotEmpty($matches, 'app.js must look up the sidebar toggle by id');

        // Only consider the sidebar toggle lookup (the one assigned to sidebarToggle)
        preg_match("/const sidebarToggle = document\\.getElementById\\('([^']+)'\\)/", $appJs, $m);
        $this->assertNotEmpty($m, 'sidebar toggle lookup must be present');
        $jsId = $m[1];

        $topbar = file_get_contents(resource_path('views/layouts/partials/topbar.blade.php'));
        $this->assertIsString($topbar);
        $this->assertStringContainsString('id="'.$jsId.'"', $topbar,
            "The id the JS looks up ({$jsId}) must exist in topbar.blade.php");
    }

    public function test_sidebar_scroll_rules_and_bottom_menu_items_render(): void
    {
        $styles = file_get_contents(resource_path('scss/app.scss'));
        $this->assertIsString($styles);
        foreach ([
            'height: 100vh;',
            'height: 100dvh;',
            'min-height: 0;',
            'overflow-y: auto;',
            'overscroll-behavior: contain;',
            '-webkit-overflow-scrolling: touch;',
            'env(safe-area-inset-bottom)',
        ] as $rule) {
            $this->assertStringContainsString($rule, $styles);
        }

        $shop = Shop::factory()->create();
        $roles = TenantContext::runAs(
            $shop,
            fn () => app(RbacService::class)->createDefaultRolesForShop($shop),
        );
        $owner = User::factory()->create(['is_platform_admin' => true]);
        ShopUser::factory()->create([
            'shop_id' => $shop->id,
            'user_id' => $owner->id,
            'role_id' => $roles['Shop Owner']->id,
            'status' => 'active',
        ]);

        $response = $this->actingAs($owner)
            ->withSession(['current_shop_id' => $shop->id])
            ->get(route('dashboard'));

        $response->assertOk();
        $html = $response->getContent();
        $navStart = strpos($html, 'data-sidebar-scroll-region');
        $this->assertNotFalse($navStart);
        $navEnd = strpos($html, '</nav>', $navStart);
        $this->assertNotFalse($navEnd);
        $sidebarNav = substr($html, $navStart, $navEnd - $navStart);

        foreach ([
            __('common.nav.staff'),
            __('common.nav.reports'),
            __('common.nav.settings'),
            __('common.platform_shops'),
            __('common.logout'),
        ] as $label) {
            $this->assertStringContainsString(e($label), $sidebarNav);
        }
    }
}
