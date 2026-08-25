<?php

use App\Models\Category;
use App\Models\Product;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Volt\Component;
use Livewire\WithPagination;

new #[Layout('components.storefront-shell', ['title' => 'Tienda', 'description' => 'Explora el catálogo completo de Torres Shop, filtrado por categoría, precio y disponibilidad.'])] class extends Component
{
    use WithPagination;

    // #[Url] keeps filters shareable/bookmarkable (e.g. a link straight to
    // "categoría=electronica&stock=1") and survives a page refresh — a
    // plain public property would reset the moment the URL changes.
    #[Url]
    public string $category = '';

    #[Url]
    public ?float $minPrice = null;

    #[Url]
    public ?float $maxPrice = null;

    #[Url]
    public bool $inStock = false;

    public function updating(): void
    {
        $this->resetPage();
    }

    public function clearFilters(): void
    {
        $this->reset(['category', 'minPrice', 'maxPrice', 'inStock']);
    }

    public function with(): array
    {
        $products = Product::query()
            ->where('is_active', true)
            ->when($this->category, fn ($q) => $q->whereHas('category', fn ($q) => $q->where('slug', $this->category)))
            ->when($this->minPrice !== null, fn ($q) => $q->where('price', '>=', $this->minPrice))
            ->when($this->maxPrice !== null, fn ($q) => $q->where('price', '<=', $this->maxPrice))
            ->when($this->inStock, fn ($q) => $q->where('stock', '>', 0))
            ->orderBy('name')
            ->paginate(9);

        return [
            'products' => $products,
            'categories' => Category::has('products')->orderBy('name')->get(),
        ];
    }
}; ?>

<div class="mx-auto max-w-7xl px-4 py-10 sm:px-6 lg:px-8">
    <h1 class="text-2xl font-bold text-gray-900">Tienda</h1>

    <div class="mt-8 flex flex-col gap-8 lg:flex-row">
        <aside class="w-full shrink-0 lg:w-64">
            <div class="rounded-lg border border-gray-200 p-4">
                <h2 class="text-sm font-semibold uppercase tracking-wide text-gray-500">Categoría</h2>
                <div class="mt-3 flex flex-col gap-2">
                    <label class="flex items-center gap-2 text-sm">
                        <input type="radio" wire:model.live="category" value="" class="border-gray-300" />
                        Todas
                    </label>
                    @foreach ($categories as $cat)
                        <label class="flex items-center gap-2 text-sm">
                            <input type="radio" wire:model.live="category" value="{{ $cat->slug }}" class="border-gray-300" />
                            {{ $cat->name }}
                        </label>
                    @endforeach
                </div>

                <h2 class="mt-6 text-sm font-semibold uppercase tracking-wide text-gray-500">Precio</h2>
                <div class="mt-3 flex items-center gap-2">
                    <input type="number" wire:model.live.debounce.400ms="minPrice" placeholder="Mín" class="w-full rounded border-gray-300 text-sm" />
                    <span class="text-gray-400">–</span>
                    <input type="number" wire:model.live.debounce.400ms="maxPrice" placeholder="Máx" class="w-full rounded border-gray-300 text-sm" />
                </div>

                <label class="mt-6 flex items-center gap-2 text-sm">
                    <input type="checkbox" wire:model.live="inStock" class="rounded border-gray-300" />
                    Solo en stock
                </label>

                <button type="button" wire:click="clearFilters" class="mt-6 text-sm text-indigo-600 hover:underline">
                    Limpiar filtros
                </button>
            </div>
        </aside>

        <div class="flex-1">
            <div class="grid grid-cols-1 gap-6 sm:grid-cols-2 lg:grid-cols-3">
                @forelse ($products as $product)
                    <a href="{{ route('product.show', $product->slug) }}" wire:navigate class="block rounded-lg border border-gray-200 p-4 hover:border-gray-400">
                        <div class="aspect-square rounded bg-gray-100"></div>
                        <h3 class="mt-3 font-medium text-gray-900">{{ $product->name }}</h3>
                        <p class="mt-1 font-semibold text-gray-900">${{ number_format($product->price, 2) }}</p>
                        @if ($product->stock <= 0)
                            <p class="mt-1 text-xs text-red-600">Agotado</p>
                        @endif
                    </a>
                @empty
                    <p class="col-span-full py-12 text-center text-gray-500">No hay productos con estos filtros.</p>
                @endforelse
            </div>

            <div class="mt-8">
                {{ $products->links() }}
            </div>
        </div>
    </div>
</div>
