<?php

namespace Tests\Feature;

use App\Models\ActivityLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Volt\Volt;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AdminRolesTest extends TestCase
{
    use RefreshDatabase;

    private function seedRolesAndPermissions(): void
    {
        foreach ([
            'products.manage', 'categories.manage', 'orders.manage',
            'users.manage', 'activity.view', 'roles.manage',
        ] as $permission) {
            Permission::findOrCreate($permission);
        }

        Role::findOrCreate('admin')->syncPermissions(Permission::pluck('name'));
        Role::findOrCreate('customer');
    }

    private function admin(): User
    {
        $this->seedRolesAndPermissions();
        $user = User::factory()->create();
        $user->assignRole('admin');

        return $user;
    }

    public function test_an_admin_can_create_a_role_with_permissions(): void
    {
        $this->actingAs($this->admin());

        Volt::test('admin.roles.form')
            ->set('name', 'editor')
            ->set('permissions', ['products.manage', 'categories.manage'])
            ->call('save')
            ->assertRedirect(route('admin.roles'));

        $role = Role::findByName('editor');
        $this->assertTrue($role->hasPermissionTo('products.manage'));
        $this->assertFalse($role->hasPermissionTo('users.manage'));

        $log = ActivityLog::where('action', 'role.created')->first();
        $this->assertSame(['products.manage', 'categories.manage'], $log->new_values['permissions']);
    }

    public function test_the_admin_role_cannot_be_renamed(): void
    {
        $this->actingAs($this->admin());
        $adminRole = Role::findByName('admin');

        Volt::test('admin.roles.form', ['role' => $adminRole])
            ->set('name', 'super-admin')
            ->call('save')
            ->assertHasErrors('name');

        $this->assertSame('admin', $adminRole->fresh()->name);
    }

    public function test_the_admin_role_always_keeps_every_permission(): void
    {
        $this->actingAs($this->admin());
        $adminRole = Role::findByName('admin');

        Volt::test('admin.roles.form', ['role' => $adminRole])
            ->set('permissions', [])
            ->call('save');

        $this->assertTrue($adminRole->fresh()->hasPermissionTo('users.manage'));
        $this->assertTrue($adminRole->fresh()->hasPermissionTo('roles.manage'));
    }

    public function test_the_admin_and_customer_roles_cannot_be_deleted(): void
    {
        $this->actingAs($this->admin());
        $adminRole = Role::findByName('admin');
        $customerRole = Role::findByName('customer');

        // The delete() method throws ValidationException directly (not via
        // $this->validate()) since there's no form field for it to attach
        // to — Livewire still catches it, it just doesn't show up as a
        // named field error, so we assert on the unchanged state instead.
        Volt::test('admin.roles.index')->call('delete', $adminRole->id);
        Volt::test('admin.roles.index')->call('delete', $customerRole->id);

        $this->assertNotNull(Role::find($adminRole->id));
        $this->assertNotNull(Role::find($customerRole->id));
    }

    public function test_a_custom_role_can_be_deleted(): void
    {
        $this->actingAs($this->admin());
        $custom = Role::findOrCreate('temp-role');

        Volt::test('admin.roles.index')->call('delete', $custom->id);

        $this->assertNull(Role::find($custom->id));
    }

    public function test_a_user_with_roles_manage_permission_can_manage_roles_without_being_admin(): void
    {
        $this->seedRolesAndPermissions();
        $manager = Role::findOrCreate('access-manager');
        $manager->syncPermissions(['roles.manage']);
        $user = User::factory()->create();
        $user->assignRole('access-manager');

        $this->actingAs($user);

        $this->get('/admin/roles')->assertOk();
        $this->get('/admin/productos')->assertForbidden();
    }

    public function test_changing_a_users_role_writes_an_activity_log_entry(): void
    {
        $admin = $this->admin();
        $target = User::factory()->create();
        $target->assignRole('customer');

        $this->actingAs($admin);

        Volt::test('admin.users-index')->call('updateRole', $target->id, 'admin');

        $this->assertTrue($target->fresh()->hasRole('admin'));

        $log = ActivityLog::where('action', 'user.role_updated')->first();
        $this->assertSame('customer', $log->old_values['role']);
        $this->assertSame('admin', $log->new_values['role']);
    }
}
