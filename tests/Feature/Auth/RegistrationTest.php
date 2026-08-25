<?php

namespace Tests\Feature\Auth;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Volt\Volt;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class RegistrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_registration_screen_can_be_rendered(): void
    {
        $response = $this->get('/register');

        $response
            ->assertOk()
            ->assertSeeVolt('pages.auth.register');
    }

    public function test_new_users_can_register(): void
    {
        // Self-registration assigns 'customer' — see the register Volt
        // component — so the role has to exist first, same as it does in
        // the real app via DatabaseSeeder.
        Role::findOrCreate('customer');

        $component = Volt::test('pages.auth.register')
            ->set('name', 'Test User')
            ->set('email', 'test@example.com')
            ->set('password', 'password')
            ->set('password_confirmation', 'password');

        $component->call('register');

        $component->assertRedirect(route('dashboard', absolute: false));

        $this->assertAuthenticated();
        $this->assertTrue(auth()->user()->hasRole('customer'));
    }

    /**
     * /register existed as a route all along but nothing in the storefront
     * ever linked to it — a logged-out visitor had no way to find it short
     * of typing the URL. storefront-shell now links it from both the
     * desktop header and the mobile menu.
     */
    public function test_the_storefront_header_links_to_registration_when_logged_out(): void
    {
        $this->get('/')->assertSee(route('register'), false);
    }
}
