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

final class PasswordInputRenderingTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        TenantContext::reset();

        parent::tearDown();
    }

    public function test_authentication_pages_render_password_component_and_suggestions(): void
    {
        $this->get(route('login'))
            ->assertOk()
            ->assertSee('data-password-toggle', false)
            ->assertSee('autocomplete="current-password"', false);

        $registerResponse = $this->get(route('register'));
        $registerResponse
            ->assertOk()
            ->assertSee('data-password-generate', false)
            ->assertSee('data-password-confirmation="password_confirmation"', false)
            ->assertSee('data-password-strength', false)
            ->assertSee('data-password-generated', false)
            ->assertSee('aria-live="polite"', false)
            ->assertSee('autocomplete="new-password"', false);
        $this->assertSame(2, substr_count($registerResponse->getContent(), 'data-password-toggle'));

        $this->get(route('password.request'))
            ->assertOk()
            ->assertDontSee('data-password-toggle', false);

        $this->get(route('password.reset', ['token' => 'sample-reset-token']))
            ->assertOk()
            ->assertSee('data-password-toggle', false)
            ->assertSee('data-password-generate', false)
            ->assertSee('data-password-strength', false)
            ->assertSee('autocomplete="new-password"', false);
    }

    public function test_staff_creation_form_renders_password_component_and_generator(): void
    {
        $shop = Shop::factory()->create();
        $roles = TenantContext::runAs(
            $shop,
            fn () => app(RbacService::class)->createDefaultRolesForShop($shop),
        );
        $owner = User::factory()->create();
        ShopUser::factory()->create([
            'shop_id' => $shop->id,
            'user_id' => $owner->id,
            'role_id' => $roles['Shop Owner']->id,
            'status' => 'active',
        ]);

        $this->actingAs($owner)
            ->withSession(['current_shop_id' => $shop->id])
            ->get(route('staff.create'))
            ->assertOk()
            ->assertSee('data-password-toggle', false)
            ->assertSee('data-password-generate', false)
            ->assertSee('data-password-strength', false)
            ->assertSee('autocomplete="new-password"', false)
            ->assertSee(__('staff.temporary_password_help'));
    }
}
