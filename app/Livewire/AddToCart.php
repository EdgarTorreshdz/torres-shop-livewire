<?php

namespace App\Livewire;

use App\Models\Product;
use App\Models\ProductColor;
use App\Models\ProductVariant;
use App\Services\Cart;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Component;

/**
 * The one reactive piece of the (otherwise plain-Blade) product page —
 * see ProductController::show() for why the page itself isn't a full
 * Livewire component.
 *
 * Split of responsibilities with the parent template: the *color*
 * swatches live up there in Alpine, because clicking one swaps the photo
 * gallery and that shouldn't cost a server round trip. The *size* picker
 * lives in here instead — a size never changes the photo, and what it
 * does change (which variant, and therefore the stock ceiling) is a
 * server-side fact anyway. The parent's Alpine calls the global
 * Livewire.dispatch() JS helper on every swatch click, picked up by
 * #[On('color-selected')] below regardless of where in the DOM it came
 * from.
 */
class AddToCart extends Component
{
    public Product $product;

    public int $quantity = 1;

    public ?int $colorId = null;

    public ?int $sizeId = null;

    public ?string $added = null;

    public function mount(Product $product): void
    {
        $this->product = $product->loadMissing(['colors', 'variants.color', 'variants.size']);

        // Pre-select the first color so a price/stock shows immediately
        // without an extra click — has to agree with the parent gallery's
        // own default selection or the two would disagree about which
        // color is "currently shown".
        $this->colorId = $product->colors->first()?->id;
        $this->sizeId = $this->defaultSizeIdForColor($this->colorId);
    }

    #[On('color-selected')]
    public function onColorSelected(?int $colorId = null): void
    {
        $this->colorId = $colorId;
        // The size that was selected may not even be sold in the new color
        // — re-pick rather than leave a selection that resolves to no
        // variant at all.
        $this->sizeId = $this->defaultSizeIdForColor($colorId);
        $this->quantity = 1;
        $this->added = null;
        $this->resetErrorBag();
    }

    public function selectSize(int $sizeId): void
    {
        $this->sizeId = $sizeId;
        $this->quantity = 1;
        $this->added = null;
        $this->resetErrorBag();
    }

    /** First size with stock in this color, falling back to the first sold at all. */
    private function defaultSizeIdForColor(?int $colorId): ?int
    {
        $sizes = $this->product->available_sizes;

        if ($sizes->isEmpty()) {
            return null;
        }

        $inStock = $sizes->first(function ($size) use ($colorId) {
            $variant = $this->variantFor($colorId, $size->id);

            return $variant && $variant->stock > 0;
        });

        return ($inStock ?? $sizes->first())->id;
    }

    private function variantFor(?int $colorId, ?int $sizeId): ?ProductVariant
    {
        return $this->product->variants
            ->first(fn (ProductVariant $v) => $v->product_color_id === $colorId && $v->size_id === $sizeId);
    }

    #[Computed]
    public function selectedColor(): ?ProductColor
    {
        return $this->colorId ? $this->product->colors->firstWhere('id', $this->colorId) : null;
    }

    #[Computed]
    public function selectedVariant(): ?ProductVariant
    {
        return $this->product->variants->isEmpty() ? null : $this->variantFor($this->colorId, $this->sizeId);
    }

    /**
     * Every size this product is sold in, each carrying the stock of its
     * variant *for the currently selected color* — so the picker can grey
     * out "M is sold out in Rojo" without hiding that M exists at all.
     */
    #[Computed]
    public function sizeOptions(): array
    {
        return $this->product->available_sizes
            ->map(fn ($size) => [
                'id' => $size->id,
                'name' => $size->name,
                'stock' => $this->variantFor($this->colorId, $size->id)?->stock ?? 0,
            ])
            ->all();
    }

    #[Computed]
    public function currentPrice(): string
    {
        return (string) ($this->selectedVariant?->effective_price
            ?? $this->selectedColor?->effective_price
            ?? $this->product->price);
    }

    #[Computed]
    public function currentStock(): int
    {
        // A product with variants has no meaningful stock of its own —
        // products.stock is left over from before it had any, and trusting
        // it here would let a sold-out combination be added to the cart.
        return $this->product->variants->isNotEmpty()
            ? ($this->selectedVariant?->stock ?? 0)
            : $this->product->stock;
    }

    public function add(): void
    {
        $this->added = null;
        $this->resetErrorBag();

        if ($this->product->colors->isNotEmpty() && ! $this->selectedColor) {
            $this->addError('colorId', 'Selecciona un color.');

            return;
        }

        if (! empty($this->sizeOptions) && ! $this->sizeId) {
            $this->addError('sizeId', 'Selecciona una talla.');

            return;
        }

        // Defensive: the pickers only offer combinations that exist, but a
        // product with variants must never reach the cart without one —
        // there'd be no stock or price to charge against.
        if ($this->product->variants->isNotEmpty() && ! $this->selectedVariant) {
            $this->addError('sizeId', 'Esa combinación no está disponible.');

            return;
        }

        if ($this->quantity < 1 || $this->quantity > $this->currentStock) {
            $this->addError('quantity', 'Cantidad no disponible.');

            return;
        }

        Cart::add($this->product->id, $this->selectedVariant?->id, $this->quantity);
        $this->dispatch('cart-updated');

        $label = $this->selectedVariant?->label;
        $this->added = "Se agregó \"{$this->product->name}\"".($label ? " ({$label})" : '').' al carrito.';
    }

    public function render()
    {
        return view('livewire.storefront.add-to-cart');
    }
}
