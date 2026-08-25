<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Volt\Volt;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AdminNotificationsTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        foreach ([
            'products.manage', 'categories.manage', 'orders.manage',
            'users.manage', 'activity.view', 'roles.manage',
        ] as $permission) {
            Permission::findOrCreate($permission);
        }

        Role::findOrCreate('admin')->syncPermissions(Permission::pluck('name'));
        Role::findOrCreate('customer');

        $user = User::factory()->create();
        $user->assignRole('admin');

        return $user;
    }

    public function test_creating_a_category_dispatches_a_success_toast(): void
    {
        $this->actingAs($this->admin());

        Volt::test('admin.categories.form')
            ->set('name', 'Toast Test')
            ->call('save')
            ->assertDispatched('toast', type: 'success');
    }

    public function test_deleting_a_category_dispatches_a_success_toast(): void
    {
        $this->actingAs($this->admin());
        $category = Category::factory()->create();

        Volt::test('admin.categories.index')
            ->call('delete', $category->id)
            ->assertDispatched('toast', type: 'success');
    }

    public function test_deleting_a_product_dispatches_a_success_toast(): void
    {
        $this->actingAs($this->admin());
        $product = Product::factory()->create();

        Volt::test('admin.products.index')
            ->call('delete', $product->id)
            ->assertDispatched('toast', type: 'success');
    }

    public function test_trying_to_remove_your_own_admin_role_dispatches_an_error_toast_without_changing_anything(): void
    {
        $admin = $this->admin();
        $this->actingAs($admin);

        Volt::test('admin.users-index')
            ->call('updateRole', $admin->id, 'customer')
            ->assertDispatched('toast', type: 'error');

        $this->assertTrue($admin->fresh()->hasRole('admin'));
    }

    public function test_trying_to_delete_a_protected_role_dispatches_an_error_toast(): void
    {
        $this->actingAs($this->admin());
        $adminRole = Role::findByName('admin');

        Volt::test('admin.roles.index')
            ->call('delete', $adminRole->id)
            ->assertDispatched('toast', type: 'error');

        $this->assertNotNull(Role::find($adminRole->id));
    }
}
