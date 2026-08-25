<?php

namespace Tests\Feature;

use App\Livewire\AddToCart;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductColor;
use App\Models\ProductVariant;
use App\Models\Size;
use App\Models\User;
use App\Services\Cart;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Livewire\Volt\Volt;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * The color x size inventory matrix: the size catalog, the admin screen
 * that materializes combinations, and everything downstream that now
 * reads stock/price off a variant instead of a product or a color.
 */
class ProductVariantsTest extends TestCase
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

    private function customer(): User
    {
        Role::findOrCreate('customer');
        $user = User::factory()->create();
        $user->assignRole('customer');

        return $user;
    }

    // --- Size catalog -----------------------------------------------------

    public function test_a_customer_is_forbidden_from_the_size_catalog(): void
    {
        $this->actingAs($this->customer());

        $this->get('/admin/tallas')->assertForbidden();
    }

    public function test_an_admin_can_create_a_size_and_duplicates_are_rejected(): void
    {
        $this->actingAs($this->admin());

        Volt::test('admin.sizes.index')
            ->set('newName', 'M')
            ->set('newSortOrder', '2')
            ->call('create')
            ->assertHasNoErrors();

        $this->assertSame(1, Size::where('name', 'M')->count());

        Volt::test('admin.sizes.index')
            ->set('newName', 'M')
            ->call('create')
            ->assertHasErrors('newName');

        $this->assertSame(1, Size::where('name', 'M')->count());
    }

    public function test_deleting_a_size_also_deletes_the_variants_that_used_it(): void
    {
        $this->actingAs($this->admin());
        $product = Product::factory()->create();
        $size = Size::factory()->create(['name' => 'M']);
        ProductVariant::factory()->for($product)->create(['size_id' => $size->id, 'stock' => 9]);

        Volt::test('admin.sizes.index')->call('delete', $size->id);

        $this->assertNull(Size::find($size->id));
        $this->assertSame(0, ProductVariant::where('size_id', $size->id)->count());
    }

    // --- The matrix screen ------------------------------------------------

    public function test_applying_sizes_builds_one_variant_per_color_and_size(): void
    {
        $this->actingAs($this->admin());
        $product = Product::factory()->create();
        $red = ProductColor::factory()->for($product)->create(['name' => 'Rojo']);
        $blue = ProductColor::factory()->for($product)->create(['name' => 'Azul']);
        $m = Size::factory()->create(['name' => 'M']);
        $l = Size::factory()->create(['name' => 'L']);

        Volt::test('admin.products.variants.index', ['product' => $product])
            ->set('selectedSizeIds', [$m->id, $l->id])
            ->call('syncSizes');

        // 2 colors x 2 sizes
        $this->assertSame(4, $product->fresh()->variants()->count());
        foreach ([$red, $blue] as $color) {
            foreach ([$m, $l] as $size) {
                $this->assertSame(1, ProductVariant::where('product_color_id', $color->id)->where('size_id', $size->id)->count());
            }
        }
    }

    public function test_unchecking_a_size_removes_its_combinations(): void
    {
        $this->actingAs($this->admin());
        $product = Product::factory()->create();
        ProductColor::factory()->for($product)->create(['name' => 'Rojo']);
        $m = Size::factory()->create(['name' => 'M']);
        $l = Size::factory()->create(['name' => 'L']);

        $component = Volt::test('admin.products.variants.index', ['product' => $product])
            ->set('selectedSizeIds', [$m->id, $l->id])
            ->call('syncSizes');

        $this->assertSame(2, $product->fresh()->variants()->count());

        $component->set('selectedSizeIds', [$m->id])->call('syncSizes');

        $this->assertSame(1, $product->fresh()->variants()->count());
        $this->assertSame(0, ProductVariant::where('size_id', $l->id)->count());
    }

    public function test_an_admin_can_save_stock_for_each_combination(): void
    {
        $this->actingAs($this->admin());
        $product = Product::factory()->create();
        $color = ProductColor::factory()->for($product)->create(['name' => 'Rojo']);
        $m = Size::factory()->create(['name' => 'M']);
        $l = Size::factory()->create(['name' => 'L']);

        $component = Volt::test('admin.products.variants.index', ['product' => $product])
            ->set('selectedSizeIds', [$m->id, $l->id])
            ->call('syncSizes');

        $variantM = ProductVariant::where('size_id', $m->id)->firstOrFail();
        $variantL = ProductVariant::where('size_id', $l->id)->firstOrFail();

        $component
            ->set("stocks.{$variantM->id}", '7')
            ->set("stocks.{$variantL->id}", '3')
            ->call('saveStock')
            ->assertHasNoErrors();

        $this->assertSame(7, $variantM->fresh()->stock);
        $this->assertSame(3, $variantL->fresh()->stock);
        $this->assertSame(10, $product->fresh()->total_stock);
        $this->assertSame(10, $color->fresh()->total_stock);
    }

    // --- Stock aggregation ------------------------------------------------

    public function test_a_product_with_variants_ignores_its_own_stock_column(): void
    {
        // products.stock is left over from before this product had variants;
        // trusting it would advertise inventory that no combination has.
        $product = Product::factory()->create(['stock' => 999]);
        ProductVariant::factory()->for($product)->create(['stock' => 3]);
        ProductVariant::factory()->for($product)->create(['stock' => 4]);

        $this->assertSame(7, $product->fresh()->total_stock);
        $this->assertTrue($product->fresh()->is_in_stock);
    }

    public function test_a_product_with_no_variants_still_uses_its_own_stock(): void
    {
        $product = Product::factory()->create(['stock' => 7]);

        $this->assertSame(7, $product->fresh()->total_stock);
        $this->assertTrue($product->fresh()->is_in_stock);
    }

    public function test_a_product_whose_variants_are_all_empty_is_not_in_stock(): void
    {
        $product = Product::factory()->create(['stock' => 999]);
        ProductVariant::factory()->for($product)->create(['stock' => 0]);

        $this->assertSame(0, $product->fresh()->total_stock);
        $this->assertFalse($product->fresh()->is_in_stock);
    }

    // --- Cart and checkout ------------------------------------------------

    public function test_the_same_product_in_two_combinations_is_two_cart_lines(): void
    {
        $product = Product::factory()->create(['price' => 100, 'is_active' => true]);
        $color = ProductColor::factory()->for($product)->create(['name' => 'Rojo']);
        $m = Size::factory()->create(['name' => 'M']);
        $l = Size::factory()->create(['name' => 'L']);

        $variantM = ProductVariant::factory()->for($product)->create(['product_color_id' => $color->id, 'size_id' => $m->id, 'stock' => 5]);
        $variantL = ProductVariant::factory()->for($product)->create(['product_color_id' => $color->id, 'size_id' => $l->id, 'stock' => 5]);

        Cart::add($product->id, $variantM->id, 1);
        Cart::add($product->id, $variantL->id, 2);

        $items = Cart::items();
        $this->assertCount(2, $items);
        $this->assertSame(1, $items->firstWhere('key', Cart::lineKey($product->id, $variantM->id))->quantity);
        $this->assertSame(2, $items->firstWhere('key', Cart::lineKey($product->id, $variantL->id))->quantity);
        $this->assertSame('Rojo / M', $items->firstWhere('key', Cart::lineKey($product->id, $variantM->id))->variant->label);
    }

    public function test_a_colors_own_price_is_used_for_every_size_of_that_color(): void
    {
        $product = Product::factory()->create(['price' => 100, 'is_active' => true]);
        $premium = ProductColor::factory()->for($product)->create(['name' => 'Edición Especial', 'price' => 150]);
        $m = Size::factory()->create(['name' => 'M']);
        $variant = ProductVariant::factory()->for($product)->create(['product_color_id' => $premium->id, 'size_id' => $m->id, 'stock' => 5]);

        Cart::add($product->id, $variant->id, 2);

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
        $this->assertSame('M', $item->size_name);
        $this->assertSame('Edición Especial / M', $item->variant_label);
        $this->assertSame(3, $variant->fresh()->stock);
    }

    public function test_checkout_decrements_the_variants_stock_not_the_products(): void
    {
        $product = Product::factory()->create(['price' => 100, 'stock' => 999, 'is_active' => true]);
        $variant = ProductVariant::factory()->for($product)->create(['stock' => 5]);

        Cart::add($product->id, $variant->id, 3);

        Volt::test('storefront.checkout')
            ->set('customer_name', 'Cliente')
            ->set('customer_email', 'cliente@example.com')
            ->set('shipping_address', 'Calle 123')
            ->call('placeOrder');

        $this->assertSame(2, $variant->fresh()->stock);
        $this->assertSame(999, $product->fresh()->stock);
    }

    public function test_checkout_fails_cleanly_when_a_combination_runs_out(): void
    {
        $product = Product::factory()->create(['price' => 100, 'is_active' => true]);
        $variant = ProductVariant::factory()->for($product)->create(['stock' => 1]);

        Cart::add($product->id, $variant->id, 5);

        Volt::test('storefront.checkout')
            ->set('customer_name', 'Cliente')
            ->set('customer_email', 'cliente@example.com')
            ->set('shipping_address', 'Calle 123')
            ->call('placeOrder')
            ->assertHasErrors('shipping_address');

        $this->assertSame(0, Order::count());
        $this->assertSame(1, $variant->fresh()->stock);
    }

    // --- Storefront picker ------------------------------------------------

    public function test_the_size_picker_reports_stock_per_size_for_the_selected_color(): void
    {
        $product = Product::factory()->create(['price' => 100, 'is_active' => true]);
        $red = ProductColor::factory()->for($product)->create(['name' => 'Rojo', 'sort_order' => 0]);
        $blue = ProductColor::factory()->for($product)->create(['name' => 'Azul', 'sort_order' => 1]);
        $m = Size::factory()->create(['name' => 'M', 'sort_order' => 0]);
        $l = Size::factory()->create(['name' => 'L', 'sort_order' => 1]);

        // Rojo: M available, L sold out. Azul: the other way round.
        ProductVariant::factory()->for($product)->create(['product_color_id' => $red->id, 'size_id' => $m->id, 'stock' => 4]);
        ProductVariant::factory()->for($product)->create(['product_color_id' => $red->id, 'size_id' => $l->id, 'stock' => 0]);
        ProductVariant::factory()->for($product)->create(['product_color_id' => $blue->id, 'size_id' => $m->id, 'stock' => 0]);
        ProductVariant::factory()->for($product)->create(['product_color_id' => $blue->id, 'size_id' => $l->id, 'stock' => 6]);

        $component = Livewire::test(AddToCart::class, ['product' => $product->fresh()]);

        // Rojo is pre-selected (lowest sort_order), and so is the first size
        // that actually has stock in it.
        $this->assertSame($red->id, $component->get('colorId'));
        $this->assertSame($m->id, $component->get('sizeId'));
        $this->assertSame([4, 0], array_column($component->instance()->sizeOptions(), 'stock'));

        // Switching color re-picks the size, because M is sold out in Azul.
        $component->dispatch('color-selected', colorId: $blue->id);
        $this->assertSame($blue->id, $component->get('colorId'));
        $this->assertSame($l->id, $component->get('sizeId'));
        $this->assertSame([0, 6], array_column($component->instance()->sizeOptions(), 'stock'));
    }

    public function test_adding_a_sold_out_combination_to_the_cart_is_rejected(): void
    {
        $product = Product::factory()->create(['price' => 100, 'is_active' => true]);
        $color = ProductColor::factory()->for($product)->create(['name' => 'Rojo']);
        $size = Size::factory()->create(['name' => 'M']);
        ProductVariant::factory()->for($product)->create(['product_color_id' => $color->id, 'size_id' => $size->id, 'stock' => 0]);

        Livewire::test(AddToCart::class, ['product' => $product->fresh()])
            ->set('quantity', 1)
            ->call('add')
            ->assertHasErrors('quantity');

        $this->assertSame(0, Cart::count());
    }
}
