<?php

use App\Concerns\Notifies;
use App\Models\ActivityLog;
use App\Models\Product;
use App\Models\ProductColor;
use App\Services\ResponsiveImage;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('layouts.app')] class extends Component
{
    use Notifies;

    public Product $product;

    public function mount(Product $product): void
    {
        abort_unless(auth()->user()->can('products.manage'), 403);

        $this->product = $product;
    }

    /**
     * Real delete, no papelera — a color is an owned part of a product,
     * same reasoning as banners (see /admin/banners): nothing else
     * references it, so a soft-delete/trash screen would be
     * infrastructure for something this small. Its images' *rows* don't
     * cascade at the database level (see the migration's comment on
     * product_images.product_color_id for why — SQL Server refuses a
     * second cascade path to the same table), so both the files and the
     * rows are cleaned up here explicitly, before the color itself goes.
     */
    public function delete(int $colorId): void
    {
        $color = $this->product->colors()->with('images')->findOrFail($colorId);
        $name = $color->name;

        foreach ($color->images as $image) {
            ResponsiveImage::delete($image->path);
            $image->delete();
        }

        // Its rows in the inventory matrix go too, for the same reason as
        // the images: product_variants has no cascade from product_colors
        // (SQL Server allows only one cascade path into that table, which
        // product_id already uses).
        $color->variants()->delete();

        $color->delete();

        ActivityLog::record(
            auth()->user(),
            'product.color_deleted',
            "Eliminó el color \"{$name}\" de \"{$this->product->name}\"",
        );

        $this->notifySuccess("Se eliminó el color \"{$name}\".");
    }

    public function with(): array
    {
        return [
            'colors' => $this->product->colors()->with(['images', 'variants'])->get(),
        ];
    }
}; ?>

<div>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Colores de') }} "{{ $product->name }}"
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="mx-auto max-w-4xl sm:px-6 lg:px-8">
            <a href="{{ route('admin.productos.editar', $product) }}" wire:navigate class="mb-4 inline-block text-sm text-gray-500 hover:text-gray-700">&larr; Volver al producto</a>

            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6">
                <div class="mb-4 flex items-center justify-between">
                    <p class="max-w-xl text-sm text-gray-500">
                        Cada color tiene su propia galería de fotos y precio (opcional — si se deja
                        vacío usa el precio del producto). El stock depende de la combinación
                        color/talla y se edita en
                        <a href="{{ route('admin.productos.variantes', $product) }}" wire:navigate class="font-medium text-indigo-600 hover:underline">Inventario</a>.
                    </p>
                    <a href="{{ route('admin.productos.colores.nuevo', $product) }}" wire:navigate class="shrink-0 rounded-full bg-gray-900 px-4 py-2 text-sm font-medium text-white hover:bg-gray-700">
                        Nuevo color
                    </a>
                </div>

                <table class="w-full text-left text-sm">
                    <thead>
                        <tr class="border-b text-gray-500">
                            <th class="py-2 pr-4"></th>
                            <th class="py-2 pr-4">Nombre</th>
                            <th class="py-2 pr-4">Precio</th>
                            <th class="py-2 pr-4">Stock</th>
                            <th class="py-2 pr-4">Fotos</th>
                            <th class="py-2">Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($colors as $color)
                            <tr class="border-b" wire:key="color-{{ $color->id }}">
                                <td class="py-2 pr-4">
                                    @if ($color->images->first())
                                        <img src="{{ $color->images->first()->url }}" alt="" class="h-10 w-10 rounded-full object-cover" />
                                    @elseif ($color->hex)
                                        <span class="block h-10 w-10 rounded-full" style="background-color: {{ $color->hex }}"></span>
                                    @else
                                        <div class="h-10 w-10 rounded-full bg-gray-100"></div>
                                    @endif
                                </td>
                                <td class="py-2 pr-4 font-medium text-gray-900">{{ $color->name }}</td>
                                <td class="py-2 pr-4 text-gray-500">
                                    ${{ number_format($color->effective_price, 2) }}
                                    @if ($color->price === null)
                                        <span class="text-xs text-gray-400">(del producto)</span>
                                    @endif
                                </td>
                                <td class="py-2 pr-4 text-gray-500">{{ $color->total_stock }}</td>
                                <td class="py-2 pr-4">
                                    {{-- Called out explicitly: a color with no photo still renders
                                         on the storefront (as a named chip), but it's almost always
                                         an oversight rather than a choice, and nothing else on this
                                         screen made it obvious. --}}
                                    @if ($color->images->isEmpty())
                                        <span class="rounded-full bg-amber-100 px-2 py-0.5 text-xs font-medium text-amber-800">Sin fotos</span>
                                    @else
                                        <span class="text-gray-500">{{ $color->images->count() }}</span>
                                    @endif
                                </td>
                                <td class="py-2">
                                    <a href="{{ route('admin.productos.colores.editar', [$product, $color]) }}" wire:navigate class="text-indigo-600 hover:underline">Editar</a>
                                    <button
                                        type="button"
                                        x-on:click="confirmAction('¿Eliminar el color \'{{ $color->name }}\'? Esto borra también sus fotos y su stock por talla.', () => $wire.delete({{ $color->id }}))"
                                        class="ml-3 text-red-600 hover:underline"
                                    >
                                        Eliminar
                                    </button>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="py-6 text-center text-gray-500">Este producto todavía no tiene colores.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
