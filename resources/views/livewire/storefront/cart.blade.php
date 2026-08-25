<?php

use App\Services\Cart;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('components.storefront-shell', ['title' => 'Carrito', 'noindex' => true])] class extends Component
{
    public function updateQuantity(int $productId, $quantity): void
    {
        Cart::update($productId, (int) $quantity);
        $this->dispatch('cart-updated');
    }

    public function remove(int $productId): void
    {
        Cart::remove($productId);
        $this->dispatch('cart-updated');
    }

    public function with(): array
    {
        return [
            'items' => Cart::items(),
            'total' => Cart::total(),
        ];
    }
}; ?>

<div class="mx-auto max-w-4xl px-4 py-10 sm:px-6 lg:px-8">
    <h1 class="text-2xl font-bold text-gray-900">Carrito</h1>

    @if ($items->isEmpty())
        <p class="mt-8 text-gray-500">Tu carrito está vacío. <a href="{{ route('shop') }}" wire:navigate class="text-indigo-600 hover:underline">Ir a la tienda</a>.</p>
    @else
        <div class="mt-8 divide-y divide-gray-200 border-y border-gray-200">
            @foreach ($items as $item)
                <div class="flex items-center justify-between gap-4 py-4" wire:key="cart-item-{{ $item->product->id }}">
                    <div class="flex-1">
                        <a href="{{ route('product.show', $item->product->slug) }}" wire:navigate class="font-medium text-gray-900 hover:underline">
                            {{ $item->product->name }}
                        </a>
                        <p class="text-sm text-gray-500">${{ number_format($item->product->price, 2) }} c/u</p>
                    </div>

                    <input
                        type="number"
                        min="1"
                        max="{{ $item->product->stock }}"
                        value="{{ $item->quantity }}"
                        wire:change="updateQuantity({{ $item->product->id }}, $event.target.value)"
                        class="w-20 rounded border-gray-300 text-sm"
                    />

                    <p class="w-24 text-right font-semibold text-gray-900">${{ number_format($item->subtotal, 2) }}</p>

                    <button type="button" wire:click="remove({{ $item->product->id }})" class="text-sm text-red-600 hover:underline">
                        Quitar
                    </button>
                </div>
            @endforeach
        </div>

        <div class="mt-6 flex items-center justify-between">
            <span class="text-lg font-semibold text-gray-900">Total: ${{ number_format($total, 2) }}</span>
            <a href="{{ route('checkout') }}" wire:navigate class="rounded-full bg-gray-900 px-6 py-2.5 text-sm font-semibold text-white hover:bg-gray-700">
                Continuar al checkout
            </a>
        </div>
    @endif
</div>
