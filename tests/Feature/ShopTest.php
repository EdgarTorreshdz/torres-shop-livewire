<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Services\Cart;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Volt\Volt;
use Tests\TestCase;

class ShopTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_public_catalog_only_shows_active_products(): void
    {
        Product::factory()->create(['name' => 'Visible Product', 'is_active' => true]);
        Product::factory()->create(['name' => 'Hidden Product', 'is_active' => false]);

        Volt::test('storefront.shop')
            ->assertSee('Visible Product')
            ->assertDontSee('Hidden Product');
    }

    public function test_the_catalog_can_be_filtered_by_category(): void
    {
        $electronics = Category::factory()->create(['name' => 'Electrónica']);
        $home = Category::factory()->create(['name' => 'Hogar']);

        Product::factory()->create(['name' => 'Audífonos', 'category_id' => $electronics->id, 'is_active' => true]);
        Product::factory()->create(['name' => 'Sartén', 'category_id' => $home->id, 'is_active' => true]);

        Volt::test('storefront.shop')
            ->set('category', $electronics->slug)
            ->assertSee('Audífonos')
            ->assertDontSee('Sartén');
    }

    public function test_the_product_page_shows_the_real_meta_description_when_set(): void
    {
        $product = Product::factory()->create([
            'name' => 'Producto SEO',
            'meta_title' => 'Título SEO personalizado',
            'meta_description' => 'Descripción SEO personalizada',
            'is_active' => true,
        ]);

        $response = $this->get("/producto/{$product->slug}");

        $response->assertOk();
        $response->assertSee('Título SEO personalizado — '.config('app.name'), false);
        $response->assertSee('Descripción SEO personalizada', false);
    }

    public function test_adding_a_product_to_the_cart_updates_the_session_cart(): void
    {
        $product = Product::factory()->create(['stock' => 10, 'is_active' => true]);

        \Livewire\Livewire::test(\App\Livewire\AddToCart::class, ['product' => $product])
            ->set('quantity', 3)
            ->call('add');

        $this->assertSame(3, Cart::count());
    }

    public function test_checkout_computes_the_total_from_the_database_and_decrements_stock(): void
    {
        $product = Product::factory()->create(['price' => 100, 'stock' => 5, 'is_active' => true]);
        Cart::add($product->id, 2);

        $component = Volt::test('storefront.checkout')
            ->set('customer_name', 'Cliente de Prueba')
            ->set('customer_email', 'cliente@example.com')
            ->set('shipping_address', 'Calle Falsa 123')
            ->call('placeOrder');

        $order = \App\Models\Order::first();
        $this->assertNotNull($order);
        $this->assertEquals(200, $order->total);
        $this->assertSame(3, $product->fresh()->stock);
        $this->assertSame(0, Cart::count());

        $component->assertRedirect(route('checkout.success', $order->id));
    }

    public function test_checkout_fails_cleanly_when_stock_is_insufficient(): void
    {
        $product = Product::factory()->create(['price' => 100, 'stock' => 1, 'is_active' => true]);
        Cart::add($product->id, 5);

        Volt::test('storefront.checkout')
            ->set('customer_name', 'Cliente de Prueba')
            ->set('customer_email', 'cliente@example.com')
            ->set('shipping_address', 'Calle Falsa 123')
            ->call('placeOrder')
            ->assertHasErrors('shipping_address');

        $this->assertSame(0, \App\Models\Order::count());
        $this->assertSame(1, $product->fresh()->stock);
    }
}
