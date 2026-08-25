<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use App\Services\Cart;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Volt\Volt;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class CustomerOrdersTest extends TestCase
{
    use RefreshDatabase;

    private function customer(): User
    {
        Role::findOrCreate('customer');
        $user = User::factory()->create();
        $user->assignRole('customer');

        return $user;
    }

    /**
     * Places a real order through the actual checkout flow (same as
     * ShopTest's checkout coverage) rather than a factory — user_id only
     * ever gets set by Order::create(['user_id' => auth()->id(), ...])
     * inside placeOrder(), so building it any other way would risk testing
     * against a shape the app never actually produces.
     */
    private function placeOrderAs(?User $user, string $name = 'Cliente de Prueba'): Order
    {
        if ($user) {
            $this->actingAs($user);
        }

        $product = Product::factory()->create(['price' => 100, 'stock' => 5, 'is_active' => true]);
        Cart::add($product->id, 1);

        Volt::test('storefront.checkout')
            ->set('customer_name', $name)
            ->set('customer_email', 'cliente@example.com')
            ->set('shipping_address', 'Calle Falsa 123')
            ->call('placeOrder');

        return Order::latest()->firstOrFail();
    }

    public function test_the_dashboard_only_shows_the_current_users_own_orders(): void
    {
        $me = $this->customer();
        $someoneElse = $this->customer();

        $myOrder = $this->placeOrderAs($me, 'Mi Pedido');
        $this->placeOrderAs($someoneElse, 'Pedido Ajeno');

        $this->actingAs($me);

        Volt::test('account.orders')
            ->assertSee("#{$myOrder->id}")
            ->assertDontSee('Pedido Ajeno');
    }

    public function test_a_guest_checkout_order_never_shows_on_any_customers_dashboard(): void
    {
        $this->placeOrderAs(null, 'Pedido de Invitado');

        $this->actingAs($this->customer());

        Volt::test('account.orders')->assertSee('Todavía no has hecho ningún pedido.');
    }

    public function test_a_customer_can_view_their_own_order_detail_with_items(): void
    {
        $me = $this->customer();
        $order = $this->placeOrderAs($me);

        Volt::test('account.order-show', ['order' => $order])
            ->assertOk()
            ->assertSee('$100.00');
    }

    public function test_a_customer_cannot_view_someone_elses_order_detail(): void
    {
        $owner = $this->customer();
        $order = $this->placeOrderAs($owner);

        $this->actingAs($this->customer());

        $this->get(route('pedidos.show', $order))->assertForbidden();
    }

    public function test_a_customer_cannot_view_a_guest_checkout_orders_detail(): void
    {
        $order = $this->placeOrderAs(null);

        $this->actingAs($this->customer());

        $this->get(route('pedidos.show', $order))->assertForbidden();
    }
}
