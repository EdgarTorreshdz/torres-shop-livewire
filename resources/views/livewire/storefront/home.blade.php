<?php

use App\Models\Category;
use App\Models\Product;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('components.storefront-shell', ['description' => 'Productos que simplifican tu día a día — electrónica, hogar, deporte y accesorios, con envío a todo México.'])] class extends Component
{
    public function with(): array
    {
        // Curated list from /admin/productos/destacados. Falls back to the
        // 6 most recent active products so the home page isn't empty before
        // an admin has picked anything — once at least one product is
        // curated, the fallback stops applying (the curated list is
        // authoritative, even with just one item in it).
        $featured = Product::with('images')->where('is_active', true)->whereNotNull('featured_order')->orderBy('featured_order')->get();

        if ($featured->isEmpty()) {
            $featured = Product::with('images')->where('is_active', true)->latest()->limit(6)->get();
        }

        return [
            'categories' => Category::has('products')->orderBy('name')->get(),
            'featured' => $featured,
        ];
    }
}; ?>

<div>
    <section class="bg-gray-900 py-20 text-center text-white">
        <div class="mx-auto max-w-3xl px-4">
            <h1 class="text-4xl font-bold tracking-tight sm:text-5xl">Torres Shop</h1>
            <p class="mt-4 text-lg text-gray-300">
                Productos que simplifican tu día a día — electrónica, hogar, deporte y accesorios.
            </p>
            <a href="{{ route('shop') }}" wire:navigate class="mt-8 inline-block rounded-full bg-white px-6 py-3 text-sm font-semibold text-gray-900 hover:bg-gray-100">
                Explorar tienda
            </a>
        </div>
    </section>

    <section class="mx-auto max-w-7xl px-4 py-12 sm:px-6 lg:px-8">
        <h2 class="text-xl font-bold text-gray-900">Categorías</h2>
        <div class="mt-6 grid grid-cols-2 gap-4 sm:grid-cols-4">
            @foreach ($categories as $category)
                <a href="{{ route('category.show', $category->slug) }}" wire:navigate class="rounded-lg border border-gray-200 p-6 text-center font-medium text-gray-800 hover:border-gray-400">
                    {{ $category->name }}
                </a>
            @endforeach
        </div>
    </section>

    <section class="mx-auto max-w-7xl px-4 pb-16 sm:px-6 lg:px-8">
        <h2 class="text-xl font-bold text-gray-900">Productos destacados</h2>
        <div class="mt-6 grid grid-cols-1 gap-6 sm:grid-cols-2 lg:grid-cols-3">
            @foreach ($featured as $product)
                <a href="{{ route('product.show', $product->slug) }}" wire:navigate class="block rounded-lg border border-gray-200 p-4 hover:border-gray-400">
                    <x-responsive-image
                        :src="$product->images->first()?->url"
                        :srcset="$product->images->first()?->srcset"
                        sizes="(min-width: 1024px) 33vw, (min-width: 640px) 50vw, 100vw"
                        :alt="$product->name"
                        class="aspect-square w-full rounded object-cover bg-gray-100"
                    />
                    <h3 class="mt-3 font-medium text-gray-900">{{ $product->name }}</h3>
                    <p class="mt-1 font-semibold text-gray-900">${{ number_format($product->price, 2) }}</p>
                </a>
            @endforeach
        </div>
    </section>
</div>
