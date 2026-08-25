<?php

use App\Models\Order;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;
use Livewire\WithPagination;

new #[Layout('layouts.app')] class extends Component
{
    use WithPagination;

    public string $search = '';

    public function mount(): void
    {
        abort_unless(auth()->user()->can('orders.manage'), 403);
    }

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function with(): array
    {
        return [
            'orders' => Order::query()
                ->when($this->search, fn ($q) => $q->where('customer_name', 'like', "%{$this->search}%")
                    ->orWhere('customer_email', 'like', "%{$this->search}%"))
                ->latest()
                ->paginate(10),
        ];
    }
}; ?>

<div>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">{{ __('Pedidos') }}</h2>
    </x-slot>

    <div class="py-12">
        <div class="mx-auto max-w-7xl sm:px-6 lg:px-8">
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6">
                <input type="search" wire:model.live.debounce.300ms="search" placeholder="Buscar por nombre o email..." class="mb-4 w-full max-w-xs rounded border-gray-300 text-sm" />

                <table class="w-full text-left text-sm">
                    <thead>
                        <tr class="border-b text-gray-500">
                            <th class="py-2 pr-4">Fecha</th>
                            <th class="py-2 pr-4">Cliente</th>
                            <th class="py-2 pr-4">Total</th>
                            <th class="py-2 pr-4">Estado</th>
                            <th class="py-2">Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($orders as $order)
                            <tr class="border-b" wire:key="order-{{ $order->id }}">
                                <td class="py-2 pr-4">{{ $order->created_at->format('d/m/Y H:i') }}</td>
                                <td class="py-2 pr-4">{{ $order->customer_name }}<br><span class="text-xs text-gray-500">{{ $order->customer_email }}</span></td>
                                <td class="py-2 pr-4">${{ number_format($order->total, 2) }}</td>
                                <td class="py-2 pr-4">
                                    <span class="rounded-full bg-gray-100 px-2 py-0.5 text-xs font-medium">{{ $order->status }}</span>
                                </td>
                                <td class="py-2">
                                    <a href="{{ route('admin.pedidos.show', $order) }}" wire:navigate class="text-indigo-600 hover:underline">Ver detalle</a>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="py-6 text-center text-gray-500">No hay pedidos todavía.</td></tr>
                        @endforelse
                    </tbody>
                </table>

                <div class="mt-4">{{ $orders->links() }}</div>
            </div>
        </div>
    </div>
</div>
