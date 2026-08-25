<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Volt\Volt;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Reference test for the role-gated admin pattern this template
 * demonstrates. Concrete projects will replace the example route/
 * component but should keep the shape of these assertions.
 */
class AdminAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_guest_is_redirected_to_login(): void
    {
        $this->get('/admin/usuarios')->assertRedirect('/login');
    }

    public function test_a_regular_user_is_forbidden(): void
    {
        Role::findOrCreate('admin');
        $user = User::factory()->create();

        $this->actingAs($user)->get('/admin/usuarios')->assertForbidden();
    }

    public function test_an_admin_can_see_the_users_list(): void
    {
        Role::findOrCreate('admin');
        $admin = User::factory()->create();
        $admin->assignRole('admin');
        $other = User::factory()->create(['name' => 'Searchable Person']);

        $this->actingAs($admin);

        Volt::test('admin.users-index')
            ->assertOk()
            ->assertSee($other->name)
            ->set('search', 'Searchable')
            ->assertSee($other->name)
            ->set('search', 'no-such-name')
            ->assertDontSee($other->name);
    }
}
