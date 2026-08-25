<?php

namespace Tests\Feature;

use App\Models\ActivityLog;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductColor;
use App\Models\User;
use App\Services\Cart;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Volt\Volt;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

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

    public function test_an_admin_can_create_a_color_with_its_own_price_and_stock(): void
    {
        $this->actingAs($this->admin());
        $product = Product::factory()->create(['price' => 300]);

        Volt::test('admin.products.colors.form', ['product' => $product])
            ->set('name', 'Rojo')
            ->set('hex', '#DC2626')
            ->set('price', '350')
            ->set('stock', '20')
            ->call('save')
            ->assertRedirect(route('admin.productos.colores', $product));

        $color = ProductColor::where('product_id', $product->id)->where('name', 'Rojo')->firstOrFail();
        $this->assertSame('#DC2626', $color->hex);
        $this->assertEquals(350, $color->price);
        $this->assertEquals(350, $color->effective_price);
        $this->assertSame(20, $color->stock);

        $this->assertNotNull(ActivityLog::where('action', 'product.color_created')->first());
    }

    public function test_a_color_without_its_own_price_falls_back_to_the_products_price(): void
    {
        $this->actingAs($this->admin());
        $product = Product::factory()->create(['price' => 300]);

        Volt::test('admin.products.colors.form', ['product' => $product])
            ->set('name', 'Azul')
            ->set('price', '')
            ->set('stock', '10')
            ->call('save');

        $color = ProductColor::where('name', 'Azul')->firstOrFail();
        $this->assertNull($color->price);
        $this->assertEquals(300, $color->effective_price);
    }

    public function test_editing_a_color_that_belongs_to_a_different_product_is_forbidden(): void
    {
        $this->actingAs($this->admin());
        $productA = Product::factory()->create();
        $productB = Product::factory()->create();
        $colorOfA = $productA->colors()->create(['name' => 'Rojo', 'stock' => 5]);

        $this->get(route('admin.productos.colores.editar', [$productB, $colorOfA]))->assertForbidden();
    }

    public function test_an_admin_can_upload_images_to_a_color_and_delete_them(): void
    {
        Storage::fake('public');
        $this->actingAs($this->admin());
        $product = Product::factory()->create();
        // Via the factory, not $product->colors()->create() directly — the
        // latter leaves sort_order null in-memory until refreshed (see
        // ProductColorFactory's own comment for why), which would make
        // the form component's mount() see an empty, invalid sort_order.
        $color = ProductColor::factory()->for($product)->create(['name' => 'Rojo', 'stock' => 5]);

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

    public function test_deleting_a_color_removes_its_images_from_disk_and_the_database(): void
    {
        Storage::fake('public');
        $this->actingAs($this->admin());
        $product = Product::factory()->create();
        // Via the factory, not $product->colors()->create() directly — the
        // latter leaves sort_order null in-memory until refreshed (see
        // ProductColorFactory's own comment for why), which would make
        // the form component's mount() see an empty, invalid sort_order.
        $color = ProductColor::factory()->for($product)->create(['name' => 'Rojo', 'stock' => 5]);

        Volt::test('admin.products.colors.form', ['product' => $product, 'color' => $color])
            ->set('newImages', [UploadedFile::fake()->image('rojo.jpg', 1000, 1000)])
            ->call('save');

        $imagePath = $color->fresh()->images->first()->path;

        Volt::test('admin.products.colors.index', ['product' => $product])->call('delete', $color->id);

        Storage::disk('public')->assertMissing($imagePath);
        $this->assertNull(ProductColor::find($color->id));
    }

    public function test_a_product_with_colors_reports_aggregate_stock_and_a_colorless_one_keeps_its_own(): void
    {
        // products.stock (999) must be ignored once colors exist — total_stock
        // has to come from summing the colors (3 + 4 = 7), not the column.
        $withColors = Product::factory()->create(['stock' => 999]);
        $withColors->colors()->createMany([
            ['name' => 'Rojo', 'stock' => 3],
            ['name' => 'Azul', 'stock' => 4],
        ]);

        $withoutColors = Product::factory()->create(['stock' => 7, 'is_active' => true]);

        $this->assertSame(7, $withColors->fresh(['colors'])->total_stock);
        $this->assertTrue($withColors->fresh(['colors'])->is_in_stock);
        $this->assertSame(7, $withoutColors->fresh()->total_stock);
        $this->assertTrue($withoutColors->fresh()->is_in_stock);
    }

    public function test_a_product_whose_colors_are_all_out_of_stock_is_not_in_stock(): void
    {
        $product = Product::factory()->create(['stock' => 999]);
        $product->colors()->createMany([
            ['name' => 'Rojo', 'stock' => 0],
            ['name' => 'Azul', 'stock' => 0],
        ]);

        $this->assertSame(0, $product->fresh(['colors'])->total_stock);
        $this->assertFalse($product->fresh(['colors'])->is_in_stock);
    }

    public function test_adding_the_same_product_in_two_different_colors_creates_two_cart_lines(): void
    {
        $product = Product::factory()->create(['price' => 100, 'is_active' => true]);
        $red = $product->colors()->create(['name' => 'Rojo', 'stock' => 5]);
        $blue = $product->colors()->create(['name' => 'Azul', 'stock' => 5]);

        Cart::add($product->id, $red->id, 1);
        Cart::add($product->id, $blue->id, 2);

        $items = Cart::items();
        $this->assertCount(2, $items);
        $this->assertSame(1, $items->firstWhere('key', Cart::lineKey($product->id, $red->id))->quantity);
        $this->assertSame(2, $items->firstWhere('key', Cart::lineKey($product->id, $blue->id))->quantity);
    }

    public function test_a_colors_own_price_is_used_in_the_cart_and_at_checkout(): void
    {
        $product = Product::factory()->create(['price' => 100, 'is_active' => true]);
        $premium = $product->colors()->create(['name' => 'Edición Especial', 'price' => 150, 'stock' => 5]);

        Cart::add($product->id, $premium->id, 2);

        $this->assertEquals(300, Cart::total()); // 150 * 2, not 100 * 2

        Volt::test('storefront.checkout')
            ->set('customer_name', 'Cliente')
            ->set('customer_email', 'cliente@example.com')
            ->set('shipping_address', 'Calle 123')
            ->call('placeOrder');

        $order = Order::firstOrFail();
        $this->assertEquals(300, $order->total);
        $item = $order->items->first();
        $this->assertEquals(150, $item->unit_price);
        $this->assertSame('Edición Especial', $item->color_name);
        $this->assertSame(3, $premium->fresh()->stock); // 5 - 2
    }

    public function test_checkout_decrements_the_colors_stock_not_the_products(): void
    {
        $product = Product::factory()->create(['price' => 100, 'stock' => 999, 'is_active' => true]);
        $color = $product->colors()->create(['name' => 'Rojo', 'stock' => 5]);

        Cart::add($product->id, $color->id, 3);

        Volt::test('storefront.checkout')
            ->set('customer_name', 'Cliente')
            ->set('customer_email', 'cliente@example.com')
            ->set('shipping_address', 'Calle 123')
            ->call('placeOrder');

        $this->assertSame(2, $color->fresh()->stock); // 5 - 3
        $this->assertSame(999, $product->fresh()->stock); // untouched
    }

    public function test_checkout_fails_cleanly_when_a_colors_stock_is_insufficient(): void
    {
        $product = Product::factory()->create(['price' => 100, 'is_active' => true]);
        $color = $product->colors()->create(['name' => 'Rojo', 'stock' => 1]);

        Cart::add($product->id, $color->id, 5);

        Volt::test('storefront.checkout')
            ->set('customer_name', 'Cliente')
            ->set('customer_email', 'cliente@example.com')
            ->set('shipping_address', 'Calle 123')
            ->call('placeOrder')
            ->assertHasErrors('shipping_address');

        $this->assertSame(0, Order::count());
        $this->assertSame(1, $color->fresh()->stock);
    }
}
