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

    /** @var array<int, string> product_id => order (string so an empty input is easy to detect) */
    public array $orders = [];

    public function mount(): void
    {
        abort_unless(auth()->user()->can('products.manage'), 403);

        foreach (Product::whereNotNull('featured_order')->get() as $product) {
            $this->orders[$product->id] = (string) $product->featured_order;
        }
    }

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    /**
     * Only touches products the admin actually saw/changed on this page —
     * unlike the featured-categories screen (a handful of rows shown all
     * at once), the product catalog is paginated, so a blanket "set
     * everything from $this->orders" would silently unfeature products
     * that were never rendered in the current page/search.
     */
    public function save(): void
    {
        $before = Product::whereNotNull('featured_order')
            ->orderBy('featured_order')
            ->pluck('name', 'id')
            ->all();

        foreach ($this->orders as $productId => $order) {
            Product::whereKey($productId)->update([
                'featured_order' => $order === '' ? null : (int) $order,
            ]);
        }

        $after = Product::whereNotNull('featured_order')
            ->orderBy('featured_order')
            ->pluck('name', 'id')
            ->all();

        ActivityLog::record(
            auth()->user(),
            'products.featured_updated',
            'Actualizó los productos seleccionados',
            oldValues: ['featured' => array_values($before)],
            newValues: ['featured' => array_values($after)],
        );

        $this->redirect(route('admin.productos.destacados'), navigate: true);
    }

    public function with(): array
    {
        $products = Product::query()
            ->when($this->search, fn ($q) => $q->where('name', 'like', "%{$this->search}%"))
            ->orderBy('name')
            ->paginate(15);

        foreach ($products as $product) {
            $this->orders[$product->id] ??= $product->featured_order !== null ? (string) $product->featured_order : '';
        }

        return ['products' => $products];
    }
}; ?>

<div>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">{{ __('Productos seleccionados') }}</h2>
    </x-slot>

    <div class="py-12">
        <div class="mx-auto max-w-4xl sm:px-6 lg:px-8">
            <a href="{{ route('admin.productos') }}" wire:navigate class="mb-4 inline-block text-sm text-gray-500 hover:text-gray-700">&larr; Volver a productos</a>

            <p class="mb-4 text-sm text-gray-500">
                Elige qué productos aparecen en el home y en el bloque "productos seleccionados" de
                cada ficha de producto, y en qué orden. Deja el número vacío para quitar un producto
                de esa lista.
            </p>

            <form wire:submit="save" class="rounded-lg border border-gray-200 bg-white p-6">
                <input type="search" wire:model.live.debounce.300ms="search" placeholder="Buscar por nombre..." class="mb-4 w-full max-w-xs rounded border-gray-300 text-sm" />

                <table class="w-full text-left text-sm">
                    <thead>
                        <tr class="border-b text-gray-500">
                            <th class="py-2 pr-4">Producto</th>
                            <th class="py-2">Orden</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($products as $product)
                            <tr class="border-b" wire:key="featured-product-{{ $product->id }}">
                                <td class="py-2 pr-4">{{ $product->name }}</td>
                                <td class="py-2">
                                    <input
                                        type="number"
                                        min="1"
                                        wire:model="orders.{{ $product->id }}"
                                        placeholder="—"
                                        class="w-24 rounded border-gray-300 text-sm"
                                    />
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>

                <div class="mt-4">{{ $products->links() }}</div>

                <button type="submit" class="mt-4 w-fit rounded-full bg-gray-900 px-6 py-2.5 text-sm font-semibold text-white hover:bg-gray-700">
                    Guardar
                </button>
            </form>
        </div>
    </div>
</div>
