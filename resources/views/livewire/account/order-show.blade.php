<?php

use App\Models\Order;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('layouts.app')] class extends Component
{
    public Order $order;

    public function mount(Order $order): void
    {
        // Only the customer who placed it can see it — a guest checkout
        // (user_id null) or someone else's order both 403 here rather than
        // silently rendering nothing, same as any other "not yours" check
        // in this app. Admins go through /admin/pedidos/{order} instead,
        // which checks the 'orders.manage' permission, not ownership.
        abort_unless($order->user_id === auth()->id(), 403);

        $this->order = $order->load('items');
    }
}; ?>

<div>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">{{ __('Pedido') }} #{{ $order->id }}</h2>
    </x-slot>

    <div class="py-12">
        <div class="mx-auto max-w-3xl sm:px-6 lg:px-8">
            <a href="{{ route('dashboard') }}" wire:navigate class="mb-4 inline-block text-sm text-gray-500 hover:text-gray-700">&larr; Volver a mis pedidos</a>

            <div class="rounded-lg border border-gray-200 bg-white p-6">
                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <div>
                        <p class="text-xs uppercase text-gray-500">Fecha</p>
                        <p class="font-medium text-gray-900">{{ $order->created_at->format('d/m/Y H:i') }}</p>
                        <p class="mt-2 text-xs uppercase text-gray-500">Estado</p>
                        <span class="rounded-full bg-gray-100 px-2 py-0.5 text-xs font-medium">{{ $order->status }}</span>
                    </div>
                    <div>
                        <p class="text-xs uppercase text-gray-500">Envío</p>
                        <p class="text-sm text-gray-700">{{ $order->shipping_address }}</p>
                    </div>
                </div>

                <div class="mt-6 divide-y divide-gray-200 border-y border-gray-200">
                    @foreach ($order->items as $item)
                        <div class="flex items-center justify-between py-3 text-sm">
                            <span>{{ $item->product_name }} &times; {{ $item->quantity }}</span>
                            <span class="font-medium">${{ number_format($item->subtotal, 2) }}</span>
                        </div>
                    @endforeach
                </div>

                <div class="mt-4 flex items-center justify-between font-semibold text-gray-900">
                    <span>Total</span>
                    <span>${{ number_format($order->total, 2) }}</span>
                </div>
            </div>
        </div>
    </div>
</div>
