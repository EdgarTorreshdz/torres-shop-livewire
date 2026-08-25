<?php

use App\Models\Order;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('layouts.app')] class extends Component
{
    public Order $order;

    public function mount(Order $order): void
    {
        abort_unless(auth()->user()->can('orders.manage'), 403);
        $this->order = $order->load('items');
    }
}; ?>

<div>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">{{ __('Pedido') }} #{{ $order->id }}</h2>
    </x-slot>

    <div class="py-12">
        <div class="mx-auto max-w-3xl sm:px-6 lg:px-8">
            <a href="{{ route('admin.pedidos') }}" wire:navigate class="mb-4 inline-block text-sm text-gray-500 hover:text-gray-700">&larr; Volver a pedidos</a>

            <div class="rounded-lg border border-gray-200 bg-white p-6">
                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <div>
                        <p class="text-xs uppercase text-gray-500">Cliente</p>
                        <p class="font-medium text-gray-900">{{ $order->customer_name }}</p>
                        <p class="text-sm text-gray-500">{{ $order->customer_email }}</p>
                        @if ($order->customer_phone)
                            <p class="text-sm text-gray-500">{{ $order->customer_phone }}</p>
                        @endif
                    </div>
                    <div>
                        <p class="text-xs uppercase text-gray-500">Envío</p>
                        <p class="text-sm text-gray-700">{{ $order->shipping_address }}</p>
                    </div>
                </div>

                <div class="mt-6 divide-y divide-gray-200 border-y border-gray-200">
                    @foreach ($order->items as $item)
                        <div class="flex items-center justify-between py-3 text-sm">
                            <span>{{ $item->product_name }}{{ $item->variant_label ? " ({$item->variant_label})" : '' }} &times; {{ $item->quantity }}</span>
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
