<?php

use App\Models\ActivityLog;
use App\Models\Product;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;
use Livewire\WithPagination;

new #[Layout('layouts.app')] class extends Component
{
    use WithPagination;

    public string $search = '';

    public function mount(): void
    {
        abort_unless(auth()->user()->can('products.manage'), 403);
    }

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function delete(int $productId): void
    {
        $product = Product::findOrFail($productId);
        $name = $product->name;
        $before = ActivityLog::snapshot($product);
        $product->delete();

        ActivityLog::record(auth()->user(), 'product.deleted', "Eliminó el producto \"{$name}\"", oldValues: $before);
    }

    public function with(): array
    {
        return [
            'products' => Product::query()
                ->with(['category', 'images'])
                ->when($this->search, fn ($q) => $q->where('name', 'like', "%{$this->search}%"))
                ->orderBy('name')
                ->paginate(10),
        ];
    }
}; ?>

<div>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">{{ __('Productos') }}</h2>
    </x-slot>

    <div class="py-12">
        <div class="mx-auto max-w-7xl sm:px-6 lg:px-8">
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6">
                <div class="mb-4 flex items-center justify-between">
                    <input type="search" wire:model.live.debounce.300ms="search" placeholder="Buscar por nombre..." class="w-full max-w-xs rounded border-gray-300 text-sm" />
                    <div class="flex gap-3">
                        <a href="{{ route('admin.productos.destacados') }}" wire:navigate class="rounded-full border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">
                            Productos seleccionados
                        </a>
                        <a href="{{ route('admin.productos.nuevo') }}" wire:navigate class="rounded-full bg-gray-900 px-4 py-2 text-sm font-medium text-white hover:bg-gray-700">
                            Nuevo producto
                        </a>
                    </div>
                </div>

                <table class="w-full text-left text-sm">
                    <thead>
                        <tr class="border-b text-gray-500">
                            <th class="py-2 pr-4"></th>
                            <th class="py-2 pr-4">Nombre</th>
                            <th class="py-2 pr-4">Categoría</th>
                            <th class="py-2 pr-4">Precio</th>
                            <th class="py-2 pr-4">Stock</th>
                            <th class="py-2 pr-4">Activo</th>
                            <th class="py-2 pr-4">Seleccionado</th>
                            <th class="py-2">Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($products as $product)
                            <tr class="border-b" wire:key="product-{{ $product->id }}">
                                <td class="py-2 pr-4">
                                    @if ($product->images->isNotEmpty())
                                        <img src="{{ $product->images->first()->url }}" alt="" class="h-10 w-10 rounded object-cover" />
                                    @else
                                        <div class="h-10 w-10 rounded bg-gray-100"></div>
                                    @endif
                                </td>
                                <td class="py-2 pr-4">{{ $product->name }}</td>
                                <td class="py-2 pr-4 text-gray-500">{{ $product->category?->name ?? '—' }}</td>
                                <td class="py-2 pr-4">${{ number_format($product->price, 2) }}</td>
                                <td class="py-2 pr-4">{{ $product->stock }}</td>
                                <td class="py-2 pr-4">{{ $product->is_active ? 'Sí' : 'No' }}</td>
                                <td class="py-2 pr-4 text-gray-500">{{ $product->featured_order !== null ? "#{$product->featured_order}" : '—' }}</td>
                                <td class="py-2">
                                    <a href="{{ route('admin.productos.editar', $product) }}" wire:navigate class="text-indigo-600 hover:underline">Editar</a>
                                    <button
                                        type="button"
                                        wire:click="delete({{ $product->id }})"
                                        wire:confirm="¿Eliminar este producto?"
                                        class="ml-3 text-red-600 hover:underline"
                                    >
                                        Eliminar
                                    </button>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="8" class="py-6 text-center text-gray-500">No hay productos todavía.</td></tr>
                        @endforelse
                    </tbody>
                </table>

                <div class="mt-4">{{ $products->links() }}</div>
            </div>
        </div>
    </div>
</div>
