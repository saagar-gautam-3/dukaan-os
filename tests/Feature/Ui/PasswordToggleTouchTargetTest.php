<?php

declare(strict_types=1);

namespace Tests\Feature\Ui;

use App\Models\Shop;
use App\Models\ShopUser;
use App\Models\User;
use App\Services\RbacService;
use App\Tenancy\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * T8b: every password field uses the shared show/hide toggle and the shared
 * class guarantees a 40px tap target at 360px.
 */
final class PasswordToggleTouchTargetTest extends TestCase
{
    use RefreshDatabase;

    private Shop $shop;

    private User $owner;

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
    }

    protected function tearDown(): void
    {
        TenantContext::reset();
        parent::tearDown();
    }

    public function test_every_password_page_uses_the_shared_40px_toggle(): void
    {
        $pages = [
            'login' => ['url' => route('login'), 'toggles' => 1],
            'register' => ['url' => route('register'), 'toggles' => 2],
            'reset-password' => [
                'url' => route('password.reset', ['token' => 'qa-probe-token', 'email' => 'qa@example.test']),
                'toggles' => 2,
            ],
        ];

        foreach ($pages as $label => $page) {
            $html = (string) $this->get($page['url'])->assertOk()->getContent();
            $this->assertToggleMarkup($label, $html, $page['toggles']);
        }

        $html = (string) $this->actingAs($this->owner)
            ->withSession(['current_shop_id' => $this->shop->id])
            ->get(route('staff.create'))
            ->assertOk()
            ->getContent();
        $this->assertToggleMarkup('staff-create', $html, 1);
    }

    public function test_shared_toggle_class_declares_a_40px_minimum(): void
    {
        $scss = (string) file_get_contents(resource_path('scss/app.scss'));

        $this->assertMatchesRegularExpression(
            '/\.password-toggle\s*\{([^}]*)\}/s',
            $scss,
            'The shared .password-toggle class must exist.'
        );

        preg_match('/\.password-toggle\s*\{([^}]*)\}/s', $scss, $match);
        $body = $match[1] ?? '';

        $this->assertMatchesRegularExpression('/min-width:\s*40px;/', $body, '.password-toggle must declare min-width: 40px.');
        $this->assertMatchesRegularExpression('/min-height:\s*40px;/', $body, '.password-toggle must declare min-height: 40px.');

        $this->assertMatchesRegularExpression(
            '/\.password-generate\s*\{([^}]*min-height:\s*40px;[^}]*)\}/s',
            $scss,
            '.password-generate must also stay at a 40px minimum height.'
        );
    }

    private function assertToggleMarkup(string $label, string $html, int $expectedToggles): void
    {
        preg_match_all('/<button\b[^>]*data-password-toggle[^>]*>/', $html, $matches);
        $buttons = $matches[0] ?? [];

        $this->assertCount($expectedToggles, $buttons, $label.' must render one show/hide toggle per password field.');

        foreach ($buttons as $button) {
            $this->assertMatchesRegularExpression(
                '/\bclass="[^"]*\bpassword-toggle\b[^"]*"/',
                $button,
                $label.' toggle must use the shared password-toggle size class.'
            );
            $this->assertStringContainsString('aria-pressed="false"', $button, $label.' toggle must expose its pressed state.');
            $this->assertMatchesRegularExpression('/aria-controls="[^"]+"/', $button, $label.' toggle must target its input.');
        }
    }
}
