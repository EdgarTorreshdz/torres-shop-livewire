<?php

use App\Models\Order;
use App\Models\Product;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('layouts.app')] class extends Component
{
    public function mount(): void
    {
        // Reachable by 'admin' or by anyone holding at least one admin
        // permission — the actual boundary for each section is its own
        // mount() check; this page just needs "some" access to be useful.
        abort_unless(auth()->user()->hasRole('admin') || auth()->user()->getAllPermissions()->isNotEmpty(), 403);
    }

    public function links(): array
    {
        $user = auth()->user();

        return collect([
            ['route' => 'admin.productos', 'label' => 'Productos', 'permission' => 'products.manage'],
            ['route' => 'admin.categorias', 'label' => 'Categorías', 'permission' => 'categories.manage'],
            ['route' => 'admin.banners', 'label' => 'Banners', 'permission' => 'banners.manage'],
            ['route' => 'admin.usuarios', 'label' => 'Usuarios', 'permission' => 'users.manage'],
            ['route' => 'admin.pedidos', 'label' => 'Pedidos', 'permission' => 'orders.manage'],
            ['route' => 'admin.bitacora', 'label' => 'Bitácora', 'permission' => 'activity.view'],
            ['route' => 'admin.roles', 'label' => 'Roles y permisos', 'permission' => 'roles.manage'],
        ])->filter(fn ($item) => $user->can($item['permission']))->all();
    }

    public function with(): array
    {
        return [
            'productCount' => Product::count(),
            'pendingOrders' => Order::where('status', 'pending')->count(),
        ];
    }
}; ?>

<div>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">{{ __('Panel de administración') }}</h2>
    </x-slot>

    <div class="py-12">
        <div class="mx-auto max-w-7xl sm:px-6 lg:px-8">
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
                <div class="rounded-lg border border-gray-200 bg-white p-6">
                    <p class="text-sm text-gray-500">Productos</p>
                    <p class="mt-1 text-2xl font-bold text-gray-900">{{ $productCount }}</p>
                </div>
                <div class="rounded-lg border border-gray-200 bg-white p-6">
                    <p class="text-sm text-gray-500">Pedidos pendientes</p>
                    <p class="mt-1 text-2xl font-bold text-gray-900">{{ $pendingOrders }}</p>
                </div>
            </div>

            <div class="mt-8 flex flex-wrap gap-3">
                @foreach ($this->links() as $link)
                    <a href="{{ route($link['route']) }}" wire:navigate class="rounded-full bg-gray-900 px-4 py-2 text-sm font-medium text-white hover:bg-gray-700">
                        {{ $link['label'] }}
                    </a>
                @endforeach
            </div>
        </div>
    </div>
</div>
