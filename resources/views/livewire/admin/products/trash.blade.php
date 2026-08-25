<?php

use App\Concerns\Notifies;
use App\Models\ActivityLog;
use App\Models\Product;
use Illuminate\Support\Facades\Storage;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;
use Livewire\WithPagination;

new #[Layout('layouts.app')] class extends Component
{
    use WithPagination, Notifies;

    public function mount(): void
    {
        abort_unless(auth()->user()->can('products.manage'), 403);
    }

    public function restore(int $productId): void
    {
        $product = Product::onlyTrashed()->findOrFail($productId);
        $product->restore();

        ActivityLog::record(auth()->user(), 'product.restored', "Restauró el producto \"{$product->name}\"", $product);

        $this->notifySuccess("Se restauró el producto \"{$product->name}\".");
    }

    /**
     * Permanent — also has to clean up the actual image files on disk
     * first: the product_images rows themselves cascade-delete at the
     * database level once the product row is really gone, but that FK
     * cascade never touches the filesystem, so skipping this would leave
     * orphaned files behind forever.
     */
    public function forceDelete(int $productId): void
    {
        $product = Product::onlyTrashed()->with('images')->findOrFail($productId);
        $name = $product->name;

        foreach ($product->images as $image) {
            Storage::disk('public')->delete($image->path);
        }

        $product->forceDelete();

        ActivityLog::record(auth()->user(), 'product.force_deleted', "Eliminó permanentemente el producto \"{$name}\"");

        $this->notifySuccess("Se eliminó permanentemente el producto \"{$name}\".");
    }

    public function with(): array
    {
        return ['products' => Product::onlyTrashed()->orderBy('deleted_at', 'desc')->paginate(10)];
    }
}; ?>

<div>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">{{ __('Papelera de productos') }}</h2>
    </x-slot>

    <div class="py-12">
        <div class="mx-auto max-w-5xl sm:px-6 lg:px-8">
            <a href="{{ route('admin.productos') }}" wire:navigate class="mb-4 inline-block text-sm text-gray-500 hover:text-gray-700">&larr; Volver a productos</a>

            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6">
                <table class="w-full text-left text-sm">
                    <thead>
                        <tr class="border-b text-gray-500">
                            <th class="py-2 pr-4">Nombre</th>
                            <th class="py-2 pr-4">Precio</th>
                            <th class="py-2 pr-4">Eliminado</th>
                            <th class="py-2">Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($products as $product)
                            <tr class="border-b" wire:key="trashed-product-{{ $product->id }}">
                                <td class="py-2 pr-4">{{ $product->name }}</td>
                                <td class="py-2 pr-4">${{ number_format($product->price, 2) }}</td>
                                <td class="py-2 pr-4 text-gray-500">{{ $product->deleted_at->format('d/m/Y H:i') }}</td>
                                <td class="py-2">
                                    <button type="button" wire:click="restore({{ $product->id }})" class="text-indigo-600 hover:underline">
                                        Restaurar
                                    </button>
                                    <button
                                        type="button"
                                        x-on:click="confirmAction('¿Eliminar permanentemente este producto? Esta acción no se puede deshacer y borra también sus imágenes.', () => $wire.forceDelete({{ $product->id }}))"
                                        class="ml-3 text-red-600 hover:underline"
                                    >
                                        Eliminar permanentemente
                                    </button>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="4" class="py-6 text-center text-gray-500">La papelera está vacía.</td></tr>
                        @endforelse
                    </tbody>
                </table>

                <div class="mt-4">{{ $products->links() }}</div>
            </div>
        </div>
    </div>
</div>
