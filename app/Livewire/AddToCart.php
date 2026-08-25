<?php

namespace App\Livewire;

use App\Models\Product;
use App\Services\Cart;
use Livewire\Component;

/**
 * The one reactive piece of the (otherwise plain-Blade) product page —
 * see ProductController::show() for why the page itself isn't a full
 * Livewire component.
 */
class AddToCart extends Component
{
    public Product $product;

    public int $quantity = 1;

    public ?string $added = null;

    public function add(): void
    {
        $this->added = null;
        $this->resetErrorBag();

        if ($this->quantity < 1 || $this->quantity > $this->product->stock) {
            $this->addError('quantity', 'Cantidad no disponible.');

            return;
        }

        Cart::add($this->product->id, $this->quantity);
        $this->dispatch('cart-updated');
        $this->added = "Se agregó \"{$this->product->name}\" al carrito.";
    }

    public function render()
    {
        return view('livewire.storefront.add-to-cart');
    }
}
