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

    public function test_an_admin_can_set_merchandising_fields_and_the_margin_is_computed(): void
    {
        $this->actingAs($this->admin());

        Volt::test('admin.products.form')
            ->set('name', 'Playera Premium')
            ->set('price', '300')
            ->set('stock', '10')
            ->set('sku', 'PLY-001')
            ->set('material', 'Algodón 100%')
            ->set('wholesale_price', '220')
            ->set('cost', '180')
            ->call('save')
            ->assertRedirect(route('admin.productos'));

        $product = Product::where('sku', 'PLY-001')->firstOrFail();
        $this->assertSame('Algodón 100%', $product->material);
        $this->assertEquals(220, $product->wholesale_price);
        $this->assertEquals(180, $product->cost);
        $this->assertEquals(120, $product->margin_amount); // 300 - 180
        $this->assertEquals(40.0, $product->margin_percent); // (300-180)/300 * 100
    }

    /**
     * Regression test: Livewire binds a blank number input as '' — and ''
     * !== null in PHP, so a plain 'nullable' rule does NOT skip 'numeric'
     * for it (Laravel's nullable check is a strict is_null()). Without
     * normalizing '' to null before validating, leaving "Precio mayoreo"
     * or "Costo de producción" blank would incorrectly fail validation.
     */
    public function test_leaving_optional_pricing_fields_blank_does_not_fail_validation(): void
    {
        $this->actingAs($this->admin());

        Volt::test('admin.products.form')
            ->set('name', 'Producto Sin Costos')
            ->set('price', '150')
            ->set('stock', '5')
            ->set('wholesale_price', '')
            ->set('cost', '')
            ->set('sku', '')
            ->call('save')
            ->assertHasNoErrors()
            ->assertRedirect(route('admin.productos'));

        $product = Product::where('name', 'Producto Sin Costos')->firstOrFail();
        $this->assertNull($product->wholesale_price);
        $this->assertNull($product->cost);
        $this->assertNull($product->sku);
        $this->assertNull($product->margin_amount);
        $this->assertNull($product->margin_percent);
    }

    public function test_sku_must_be_unique_among_products(): void
    {
        $this->actingAs($this->admin());
        Product::factory()->create(['sku' => 'DUPLICADO']);

        Volt::test('admin.products.form')
            ->set('name', 'Otro Producto')
            ->set('price', '100')
            ->set('stock', '1')
            ->set('sku', 'DUPLICADO')
            ->call('save')
            ->assertHasErrors('sku');
    }

    public function test_the_product_page_shows_material_and_color_names_but_never_internal_pricing(): void
    {
        $product = Product::factory()->create([
            'name' => 'Producto Con Detalles',
            'is_active' => true,
            'material' => 'Piel Genuina',
            'wholesale_price' => 199.99,
            'cost' => 150.00,
        ]);
        $product->colors()->create(['name' => 'Verde Bosque', 'stock' => 5]);

        $response = $this->get("/producto/{$product->slug}");

        $response->assertOk();
        $response->assertSee('Verde Bosque');
        $response->assertSee('Piel Genuina');
        // Internal-only figures must never leak to the public product page.
        $response->assertDontSee('199.99');
        $response->assertDontSee('150.00');
        $response->assertDontSee('150.0');
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
