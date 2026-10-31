<?php

declare(strict_types=1);

namespace Tests\Feature\Localization;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LocaleSwitchTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_defaults_to_ne_locale(): void
    {
        app()->setLocale('ne');

        $response = $this->get('/');
        $response->assertOk();
        $this->assertEquals('ne', app()->getLocale());
    }

    public function test_guest_can_switch_locale_via_session_and_cookie(): void
    {
        $response = $this->get(route('locale.switch', 'en'));
        $response->assertRedirect();
        $response->assertSessionHas('locale', 'en');
        $response->assertCookie('locale', 'en');

        $this->withSession(['locale' => 'en'])->get('/');
        $this->assertEquals('en', app()->getLocale());
    }

    public function test_authenticated_user_saves_locale_to_database(): void
    {
        $user = User::factory()->create(['locale' => 'ne']);

        $response = $this->actingAs($user)->get(route('locale.switch', 'en'));
        $response->assertRedirect();

        $user->refresh();
        $this->assertEquals('en', $user->locale);

        $this->actingAs($user)->get('/');
        $this->assertEquals('en', app()->getLocale());
    }
}
