<?php

namespace Tests\Feature;

use App\Models\ActivityLog;
use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Livewire\Volt\Volt;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class SoftDeletesTest extends TestCase
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

    public function test_deleting_a_category_soft_deletes_it(): void
    {
        $this->actingAs($this->admin());
        $category = Category::factory()->create();

        Volt::test('admin.categories.index')->call('delete', $category->id);

        $this->assertNull(Category::find($category->id));
        $this->assertNotNull(Category::withTrashed()->find($category->id));
        $this->assertNotNull(Category::withTrashed()->find($category->id)->deleted_at);
    }

    public function test_a_soft_deleted_category_shows_up_in_the_trash_and_can_be_restored(): void
    {
        $this->actingAs($this->admin());
        $category = Category::factory()->create(['name' => 'Trashed Category']);
        $category->delete();

        Volt::test('admin.categories.trash')
            ->assertSee('Trashed Category')
            ->call('restore', $category->id)
            ->assertDispatched('toast', type: 'success');

        $this->assertNotNull(Category::find($category->id));
        $this->assertNull(Category::find($category->id)->deleted_at);

        $log = ActivityLog::where('action', 'category.restored')->first();
        $this->assertNotNull($log);
    }

    public function test_force_deleting_a_category_permanently_removes_it(): void
    {
        $this->actingAs($this->admin());
        $category = Category::factory()->create();
        $category->delete();

        Volt::test('admin.categories.trash')->call('forceDelete', $category->id);

        $this->assertNull(Category::withTrashed()->find($category->id));

        $log = ActivityLog::where('action', 'category.force_deleted')->first();
        $this->assertNotNull($log);
    }

    public function test_deleting_a_product_soft_deletes_it(): void
    {
        $this->actingAs($this->admin());
        $product = Product::factory()->create();

        Volt::test('admin.products.index')->call('delete', $product->id);

        $this->assertNull(Product::find($product->id));
        $this->assertNotNull(Product::withTrashed()->find($product->id));
    }

    public function test_a_soft_deleted_product_can_be_restored_from_the_trash(): void
    {
        $this->actingAs($this->admin());
        $product = Product::factory()->create(['name' => 'Trashed Product']);
        $product->delete();

        Volt::test('admin.products.trash')
            ->assertSee('Trashed Product')
            ->call('restore', $product->id);

        $this->assertNotNull(Product::find($product->id));
    }

    public function test_force_deleting_a_product_removes_its_image_files_from_disk(): void
    {
        Storage::fake('public');
        $this->actingAs($this->admin());
        $product = Product::factory()->create();
        $path = "products/{$product->id}/test.jpg";
        Storage::disk('public')->put($path, 'fake-image-content');
        $product->images()->create(['path' => $path, 'sort_order' => 0]);
        $product->delete();

        Volt::test('admin.products.trash')->call('forceDelete', $product->id);

        $this->assertNull(Product::withTrashed()->find($product->id));
        Storage::disk('public')->assertMissing($path);
    }

    public function test_a_customer_cannot_access_the_trash_pages(): void
    {
        $this->admin(); // seeds roles/permissions
        $customer = User::factory()->create();
        $customer->assignRole('customer');
        $this->actingAs($customer);

        $this->get('/admin/categorias/papelera')->assertForbidden();
        $this->get('/admin/productos/papelera')->assertForbidden();
    }

    public function test_a_soft_deleted_category_no_longer_shows_up_on_the_storefront(): void
    {
        $category = Category::factory()->create(['name' => 'Visible Then Gone']);
        Product::factory()->create(['category_id' => $category->id, 'is_active' => true]);
        $category->delete();

        $response = $this->get('/');

        $response->assertDontSee('Visible Then Gone');
    }
}
