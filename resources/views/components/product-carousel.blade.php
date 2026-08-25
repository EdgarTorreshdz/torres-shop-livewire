@props(['title', 'products'])
@if ($products->isNotEmpty())
    <section class="mx-auto max-w-7xl px-4 py-10 sm:px-6 lg:px-8">
        <h2 class="text-xl font-bold text-gray-900">{{ $title }}</h2>

        <div class="mt-6 flex snap-x snap-mandatory gap-4 overflow-x-auto pb-4">
            @foreach ($products as $product)
                <a
                    href="{{ route('product.show', $product->slug) }}"
                    wire:navigate
                    class="block w-48 shrink-0 snap-start rounded-lg border border-gray-200 p-4 hover:border-gray-400"
                >
                    <x-responsive-image
                        :src="$product->display_image?->url"
                        :srcset="$product->display_image?->srcset"
                        sizes="192px"
                        :alt="$product->name"
                        class="aspect-square w-full rounded object-cover bg-gray-100"
                    />
                    <h3 class="mt-3 truncate font-medium text-gray-900">{{ $product->name }}</h3>
                    <p class="mt-1 font-semibold text-gray-900">${{ number_format($product->price, 2) }}</p>
                </a>
            @endforeach
        </div>
    </section>
@endif
