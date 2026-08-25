<?php

use App\Models\Order;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;
use Livewire\WithPagination;

// This is what `/dashboard` actually renders — the generic Breeze stub
// ("You're logged in!") got replaced with the one thing every logged-in
// customer actually wants to land on: their own order history. No
// mount()-level permission check beyond the route's own 'auth' middleware
// — any authenticated user (customer or admin) has orders of their own to
// see here, scoped by user_id, never someone else's.
new #[Layout('layouts.app')] class extends Component
{
    use WithPagination;

    public function with(): array
    {
        return [
            // Guest checkouts (user_id null) never show up here, by
            // construction — only orders placed while logged in as this
            // user get linked (see storefront.checkout's placeOrder()).
            'orders' => Order::where('user_id', auth()->id())->latest()->paginate(10),
        ];
    }
}; ?>

<div>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">{{ __('Mis pedidos') }}</h2>
    </x-slot>

    <div class="py-12">
        <div class="mx-auto max-w-5xl sm:px-6 lg:px-8">
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6">
                @if ($orders->isEmpty())
                    <div class="py-12 text-center">
                        <p class="text-gray-500">Todavía no has hecho ningún pedido.</p>
                        <a href="{{ route('shop') }}" wire:navigate class="mt-4 inline-block rounded-full bg-gray-900 px-6 py-2.5 text-sm font-semibold text-white hover:bg-gray-700">
                            Ir a la tienda
                        </a>
                    </div>
                @else
                    <table class="w-full text-left text-sm">
                        <thead>
                            <tr class="border-b text-gray-500">
                                <th class="py-2 pr-4">Pedido</th>
                                <th class="py-2 pr-4">Fecha</th>
                                <th class="py-2 pr-4">Total</th>
                                <th class="py-2 pr-4">Estado</th>
                                <th class="py-2">Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($orders as $order)
                                <tr class="border-b" wire:key="order-{{ $order->id }}">
                                    <td class="py-2 pr-4 font-medium text-gray-900">#{{ $order->id }}</td>
                                    <td class="py-2 pr-4 text-gray-500">{{ $order->created_at->format('d/m/Y H:i') }}</td>
                                    <td class="py-2 pr-4">${{ number_format($order->total, 2) }}</td>
                                    <td class="py-2 pr-4">
                                        <span class="rounded-full bg-gray-100 px-2 py-0.5 text-xs font-medium">{{ $order->status }}</span>
                                    </td>
                                    <td class="py-2">
                                        <a href="{{ route('pedidos.show', $order) }}" wire:navigate class="text-indigo-600 hover:underline">Ver detalle</a>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>

                    <div class="mt-4">{{ $orders->links() }}</div>
                @endif
            </div>
        </div>
    </div>
</div>
