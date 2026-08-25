<x-storefront-shell :title="$seoTitle" :description="$seoDescription">
    @if ($category->banner_image_url)
        <picture>
            @if ($category->mobile_image_url)
                <source media="(max-width: 640px)" srcset="{{ $category->mobile_srcset }}" sizes="100vw">
            @endif
            <img
                src="{{ $category->banner_image_url }}"
                srcset="{{ $category->banner_srcset }}"
                sizes="100vw"
                alt="{{ $category->name }}"
                loading="eager"
                class="h-48 w-full object-cover sm:h-64"
            />
        </picture>
    @endif

    <div class="mx-auto max-w-7xl px-4 py-10 sm:px-6 lg:px-8">
        <h1 class="text-2xl font-bold text-gray-900">{{ $category->name }}</h1>
        @if ($category->description)
            <p class="mt-2 max-w-2xl text-gray-600">{{ $category->description }}</p>
        @endif

        <div class="mt-8 grid grid-cols-1 gap-6 sm:grid-cols-2 lg:grid-cols-3">
            @forelse ($products as $product)
                <a href="{{ route('product.show', $product->slug) }}" wire:navigate class="block rounded-lg border border-gray-200 p-4 hover:border-gray-400">
                    <x-responsive-image
                        :src="$product->display_image?->url"
                        :srcset="$product->display_image?->srcset"
                        sizes="(min-width: 1024px) 33vw, (min-width: 640px) 50vw, 100vw"
                        :alt="$product->name"
                        class="aspect-square w-full rounded object-cover bg-gray-100"
                    />
                    <h3 class="mt-3 font-medium text-gray-900">{{ $product->name }}</h3>
                    <p class="mt-1 font-semibold text-gray-900">${{ number_format($product->price, 2) }}</p>
                </a>
            @empty
                <p class="col-span-full py-12 text-center text-gray-500">Todavía no hay productos en esta categoría.</p>
            @endforelse
        </div>

        <div class="mt-8">
            {{ $products->links() }}
        </div>

        <a href="{{ route('shop') }}?category={{ $category->slug }}" wire:navigate class="mt-6 inline-block text-sm text-indigo-600 hover:underline">
            Combinar con más filtros en la tienda &rarr;
        </a>
    </div>
</x-storefront-shell>
