<?php

namespace App\Livewire;

use App\Services\Cart;
use Livewire\Attributes\On;
use Livewire\Component;

/**
 * Tiny standalone component embedded in the storefront nav
 * (`<livewire:cart-count />`) so the cart badge updates itself the moment
 * any page adds/removes/clears an item — those actions dispatch
 * 'cart-updated' (a plain Livewire event, no JS wiring needed) and every
 * copy of this component re-renders in response.
 */
class CartCount extends Component
{
    #[On('cart-updated')]
    public function refresh(): void
    {
        // Livewire re-renders on any listened-for event; nothing to do here
        // beyond existing so the render() call below picks up the new count.
    }

    public function render()
    {
        return view('livewire.cart-count', ['count' => Cart::count()]);
    }
}
