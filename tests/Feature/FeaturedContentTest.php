<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Volt\Volt;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class FeaturedContentTest extends TestCase
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

    public function test_a_category_banner_can_be_uploaded_as_a_real_file(): void
    {
        Storage::fake('public');
        $this->actingAs($this->admin());

        Volt::test('admin.categories.form')
            ->set('name', 'Con Imagen')
            ->set('bannerImage', UploadedFile::fake()->image('banner.jpg'))
            ->call('save');

        $category = Category::where('name', 'Con Imagen')->firstOrFail();
        $this->assertNotNull($category->banner_image_path);
        Storage::disk('public')->assertExists($category->banner_image_path);
        $this->assertStringContainsString($category->banner_image_path, $category->banner_image_url);
    }

    public function test_an_admin_can_curate_which_categories_show_in_the_customer_menu(): void
    {
        $this->actingAs($this->admin());
        $featured = Category::factory()->create(['name' => 'Destacada']);
        $hidden = Category::factory()->create(['name' => 'No Destacada']);

        Volt::test('admin.categories.featured')
            ->set("orders.{$featured->id}", '1')
            ->call('save');

        $this->assertSame(1, $featured->fresh()->featured_order);
        $this->assertNull($hidden->fresh()->featured_order);
    }

    public function test_the_customer_nav_only_shows_featured_categories(): void
    {
        Category::factory()->create(['name' => 'Destacada', 'featured_order' => 1]);
        Category::factory()->create(['name' => 'No Destacada']);

        $response = $this->get('/');

        $response->assertSee('Destacada');
        $response->assertDontSee('No Destacada');
    }

    public function test_a_customer_cannot_manage_featured_categories(): void
    {
        $this->seedRolesAndPermissions();
        $customer = User::factory()->create();
        $customer->assignRole('customer');
        $this->actingAs($customer);

        $this->get('/admin/categorias/destacadas')->assertForbidden();
    }

    public function test_an_admin_can_curate_selected_products(): void
    {
        $this->actingAs($this->admin());
        $featured = Product::factory()->create(['name' => 'Seleccionado']);
        $notFeatured = Product::factory()->create(['name' => 'No Seleccionado']);

        Volt::test('admin.products.featured')
            ->set("orders.{$featured->id}", '1')
            ->call('save');

        $this->assertSame(1, $featured->fresh()->featured_order);
        $this->assertNull($notFeatured->fresh()->featured_order);
    }

    public function test_the_home_page_shows_the_curated_selected_products(): void
    {
        Product::factory()->create(['name' => 'Producto Curado', 'is_active' => true, 'featured_order' => 1]);
        Product::factory()->create(['name' => 'Producto No Curado', 'is_active' => true]);

        $response = $this->get('/');

        $response->assertSee('Producto Curado');
        $response->assertDontSee('Producto No Curado');
    }

    public function test_the_product_page_shows_a_carousel_of_related_and_selected_products(): void
    {
        $category = Category::factory()->create();
        $product = Product::factory()->create(['category_id' => $category->id, 'is_active' => true]);
        $related = Product::factory()->create(['category_id' => $category->id, 'name' => 'Producto Relacionado', 'is_active' => true]);
        $otherCategoryProduct = Product::factory()->create(['name' => 'De Otra Categoría', 'is_active' => true]);
        $featured = Product::factory()->create(['name' => 'Producto Estrella', 'is_active' => true, 'featured_order' => 1]);

        $response = $this->get("/producto/{$product->slug}");

        $response->assertSee('Producto Relacionado');
        $response->assertSee('Producto Estrella');
        $response->assertDontSee('De Otra Categoría');
    }
}
