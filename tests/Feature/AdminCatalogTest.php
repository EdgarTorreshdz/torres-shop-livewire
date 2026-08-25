<?php

namespace Tests\Feature;

use App\Models\ActivityLog;
use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Volt\Volt;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AdminCatalogTest extends TestCase
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

    private function customer(): User
    {
        $this->seedRolesAndPermissions();
        $user = User::factory()->create();
        $user->assignRole('customer');

        return $user;
    }

    public function test_a_customer_is_forbidden_from_the_products_admin_page(): void
    {
        $this->actingAs($this->customer());

        $this->get('/admin/productos')->assertForbidden();
    }

    public function test_an_admin_can_create_a_product_and_it_is_logged_with_new_values(): void
    {
        $this->actingAs($this->admin());

        Volt::test('admin.products.form')
            ->set('name', 'Producto de Prueba')
            ->set('price', '199.99')
            ->set('stock', '10')
            ->call('save')
            ->assertRedirect(route('admin.productos'));

        $product = Product::where('name', 'Producto de Prueba')->first();
        $this->assertNotNull($product);

        $log = ActivityLog::where('action', 'product.created')->first();
        $this->assertNotNull($log);
        $this->assertNull($log->old_values);
        $this->assertSame('Producto de Prueba', $log->new_values['name']);
    }

    public function test_updating_a_product_records_both_old_and_new_values(): void
    {
        $this->actingAs($this->admin());
        $product = Product::factory()->create(['name' => 'Nombre Original', 'price' => 50, 'stock' => 5]);

        Volt::test('admin.products.form', ['product' => $product])
            ->set('name', 'Nombre Actualizado')
            ->set('price', '75')
            ->set('stock', '5')
            ->call('save');

        $log = ActivityLog::where('action', 'product.updated')->first();
        $this->assertSame('Nombre Original', $log->old_values['name']);
        $this->assertSame('Nombre Actualizado', $log->new_values['name']);
    }

    public function test_deleting_a_product_records_old_values_with_no_new_values(): void
    {
        $this->actingAs($this->admin());
        $product = Product::factory()->create(['name' => 'Producto a Eliminar']);

        Volt::test('admin.products.index')->call('delete', $product->id);

        $log = ActivityLog::where('action', 'product.deleted')->first();
        $this->assertSame('Producto a Eliminar', $log->old_values['name']);
        $this->assertNull($log->new_values);
        $this->assertNull(Product::find($product->id));
    }

    public function test_an_admin_can_create_and_update_a_category(): void
    {
        $this->actingAs($this->admin());

        Volt::test('admin.categories.form')
            ->set('name', 'Categoría Nueva')
            ->call('save')
            ->assertRedirect(route('admin.categorias'));

        $category = Category::where('name', 'Categoría Nueva')->firstOrFail();

        Volt::test('admin.categories.form', ['category' => $category])
            ->set('name', 'Categoría Renombrada')
            ->call('save');

        $this->assertSame('Categoría Renombrada', $category->fresh()->name);

        $log = ActivityLog::where('action', 'category.updated')->first();
        $this->assertSame('Categoría Nueva', $log->old_values['name']);
        $this->assertSame('Categoría Renombrada', $log->new_values['name']);
    }

    public function test_a_user_with_only_activity_view_permission_can_read_the_log_without_being_admin(): void
    {
        $this->seedRolesAndPermissions();
        $editor = Role::findOrCreate('bitacora-viewer');
        $editor->syncPermissions(['activity.view']);
        $user = User::factory()->create();
        $user->assignRole('bitacora-viewer');

        $this->actingAs($user);

        $this->get('/admin/bitacora')->assertOk();
        $this->get('/admin/productos')->assertForbidden();
    }
}
