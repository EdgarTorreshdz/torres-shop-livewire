<?php

namespace Tests\Feature;

use App\Models\ActivityLog;
use App\Models\Product;
use App\Models\ProductColor;
use App\Models\ProductVariant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Volt\Volt;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Colors as a presentation concern — name, swatch, own photo gallery and
 * optional own price. Stock lives on the color x size matrix instead; see
 * ProductVariantsTest for that half.
 */
class ProductColorsTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        foreach (['products.manage', 'categories.manage', 'orders.manage', 'users.manage', 'activity.view', 'roles.manage'] as $permission) {
            Permission::findOrCreate($permission);
        }

        Role::findOrCreate('admin')->syncPermissions(Permission::pluck('name'));

        $user = User::factory()->create();
        $user->assignRole('admin');

        return $user;
    }

    public function test_an_admin_can_create_a_color_with_its_own_price(): void
    {
        $this->actingAs($this->admin());
        $product = Product::factory()->create(['price' => 300]);

        Volt::test('admin.products.colors.form', ['product' => $product])
            ->set('name', 'Rojo')
            ->set('hex', '#DC2626')
            ->set('price', '350')
            ->call('save')
            ->assertRedirect(route('admin.productos.colores', $product));

        $color = ProductColor::where('product_id', $product->id)->where('name', 'Rojo')->firstOrFail();
        $this->assertSame('#DC2626', $color->hex);
        $this->assertEquals(350, $color->price);
        $this->assertEquals(350, $color->effective_price);

        $this->assertNotNull(ActivityLog::where('action', 'product.color_created')->first());
    }

    public function test_a_color_without_its_own_price_falls_back_to_the_products_price(): void
    {
        $this->actingAs($this->admin());
        $product = Product::factory()->create(['price' => 300]);

        Volt::test('admin.products.colors.form', ['product' => $product])
            ->set('name', 'Azul')
            ->set('price', '')
            ->call('save');

        $color = ProductColor::where('name', 'Azul')->firstOrFail();
        $this->assertNull($color->price);
        $this->assertEquals(300, $color->effective_price);
    }

    public function test_a_new_color_gets_a_variant_row_so_it_has_somewhere_to_hold_stock(): void
    {
        $this->actingAs($this->admin());
        $product = Product::factory()->create();

        Volt::test('admin.products.colors.form', ['product' => $product])
            ->set('name', 'Rojo')
            ->call('save');

        $color = ProductColor::where('name', 'Rojo')->firstOrFail();
        $this->assertSame(1, $color->variants()->count());
        $this->assertNull($color->variants()->first()->size_id);
    }

    /**
     * Adding the first color to a product that already had sizeless
     * inventory must not strand that stock under a "Sin color" row the
     * matrix no longer renders — the existing rows are handed over to the
     * new color instead.
     */
    public function test_the_first_color_adopts_the_products_existing_colorless_stock(): void
    {
        $this->actingAs($this->admin());
        $product = Product::factory()->create();
        ProductVariant::factory()->for($product)->create(['stock' => 25]);

        Volt::test('admin.products.colors.form', ['product' => $product])
            ->set('name', 'Rojo')
            ->call('save');

        $color = ProductColor::where('name', 'Rojo')->firstOrFail();

        $this->assertSame(1, $product->fresh()->variants()->count());
        $this->assertSame(25, $color->variants()->first()->stock);
    }

    public function test_editing_a_color_that_belongs_to_a_different_product_is_forbidden(): void
    {
        $this->actingAs($this->admin());
        $productA = Product::factory()->create();
        $productB = Product::factory()->create();
        $colorOfA = ProductColor::factory()->for($productA)->create(['name' => 'Rojo']);

        $this->get(route('admin.productos.colores.editar', [$productB, $colorOfA]))->assertForbidden();
    }

    public function test_an_admin_can_upload_images_to_a_color_and_delete_them(): void
    {
        Storage::fake('public');
        $this->actingAs($this->admin());
        $product = Product::factory()->create();
        $color = ProductColor::factory()->for($product)->create(['name' => 'Rojo']);

        Volt::test('admin.products.colors.form', ['product' => $product, 'color' => $color])
            ->set('newImages', [UploadedFile::fake()->image('rojo.jpg', 1000, 1000)])
            ->call('save');

        $color->refresh();
        $this->assertCount(1, $color->images);
        $image = $color->images->first();
        Storage::disk('public')->assertExists($image->path);

        Volt::test('admin.products.colors.form', ['product' => $product, 'color' => $color])
            ->call('deleteImage', $image->id);

        Storage::disk('public')->assertMissing($image->path);
        $this->assertSame(0, $color->fresh()->images->count());
    }

    /**
     * A color's images live in product_images and its inventory in
     * product_variants, and neither cascades from product_colors (SQL
     * Server allows only one cascade path into each of those tables, and
     * products.id already uses it) — so both have to be cleaned up in
     * application code or they'd outlive the color.
     */
    public function test_deleting_a_color_removes_its_images_and_variants(): void
    {
        Storage::fake('public');
        $this->actingAs($this->admin());
        $product = Product::factory()->create();
        $color = ProductColor::factory()->for($product)->create(['name' => 'Rojo']);
        ProductVariant::factory()->for($product)->create(['product_color_id' => $color->id, 'stock' => 4]);

        Volt::test('admin.products.colors.form', ['product' => $product, 'color' => $color])
            ->set('newImages', [UploadedFile::fake()->image('rojo.jpg', 1000, 1000)])
            ->call('save');

        $imagePath = $color->fresh()->images->first()->path;

        Volt::test('admin.products.colors.index', ['product' => $product])->call('delete', $color->id);

        Storage::disk('public')->assertMissing($imagePath);
        $this->assertNull(ProductColor::find($color->id));
        $this->assertSame(0, ProductVariant::where('product_color_id', $color->id)->count());
    }

    /**
     * A color with no photo and no hex used to render as a gray circle
     * holding the first three letters of its name ("Roj"), which read as a
     * broken image rather than a color button. It's a full named chip now.
     */
    public function test_a_color_without_a_photo_still_shows_its_full_name_on_the_product_page(): void
    {
        $product = Product::factory()->create(['is_active' => true]);
        $color = ProductColor::factory()->for($product)->create(['name' => 'Rojo Carmesí', 'hex' => null]);
        ProductVariant::factory()->for($product)->create(['product_color_id' => $color->id, 'stock' => 3]);

        $response = $this->get("/producto/{$product->slug}");

        $response->assertOk();
        $response->assertSee('Rojo Carmesí');
        $response->assertDontSee('>Roj<', false);
    }
}
