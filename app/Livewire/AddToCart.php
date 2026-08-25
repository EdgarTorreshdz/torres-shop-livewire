<?php

namespace App\Livewire;

use App\Models\Product;
use App\Models\ProductColor;
use App\Services\Cart;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Component;

/**
 * The one reactive piece of the (otherwise plain-Blade) product page —
 * see ProductController::show() for why the page itself isn't a full
 * Livewire component.
 *
 * The color swatches themselves live in the parent Blade template
 * (product-show.blade.php), driven by Alpine for an instant client-side
 * photo swap with no server round trip — but *this* component still needs
 * to know which color is selected, to validate stock and charge the right
 * price. The parent's Alpine code calls the global `Livewire.dispatch()`
 * JS helper on every swatch click, which #[On] below picks up regardless
 * of where in the DOM it came from.
 */
class AddToCart extends Component
{
    public Product $product;

    public int $quantity = 1;

    public ?int $colorId = null;

    public ?string $added = null;

    public function mount(Product $product): void
    {
        $this->product = $product;

        // Pre-select the first color (by sort_order) so a product with
        // colors shows a real price/stock immediately, without an extra
        // click — has to agree with the parent gallery's own default
        // selection or the two would disagree about which color is
        // "currently shown".
        $this->colorId = $product->colors->first()?->id;
    }

    #[On('color-selected')]
    public function onColorSelected(?int $colorId = null): void
    {
        $this->colorId = $colorId;
        $this->quantity = 1;
        $this->added = null;
        $this->resetErrorBag();
    }

    #[Computed]
    public function selectedColor(): ?ProductColor
    {
        return $this->colorId ? $this->product->colors->firstWhere('id', $this->colorId) : null;
    }

    #[Computed]
    public function currentPrice(): string
    {
        return (string) ($this->selectedColor?->effective_price ?? $this->product->price);
    }

    #[Computed]
    public function currentStock(): int
    {
        return $this->selectedColor?->stock ?? $this->product->stock;
    }

    public function add(): void
    {
        $this->added = null;
        $this->resetErrorBag();

        // Defensive, not just decorative: the swatches pre-select a color
        // and the parent keeps this in sync, but a product that HAS
        // colors must never add to cart with none chosen (e.g. if that
        // sync somehow didn't happen) — there'd be no price/stock to
        // validate against.
        if ($this->product->colors->isNotEmpty() && ! $this->selectedColor) {
            $this->addError('colorId', 'Selecciona un color.');

            return;
        }

        if ($this->quantity < 1 || $this->quantity > $this->currentStock) {
            $this->addError('quantity', 'Cantidad no disponible.');

            return;
        }

        Cart::add($this->product->id, $this->colorId, $this->quantity);
        $this->dispatch('cart-updated');

        $colorSuffix = $this->selectedColor ? " ({$this->selectedColor->name})" : '';
        $this->added = "Se agregó \"{$this->product->name}\"{$colorSuffix} al carrito.";
    }

    public function render()
    {
        return view('livewire.storefront.add-to-cart');
    }
}
