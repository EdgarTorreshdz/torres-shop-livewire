<x-storefront-shell :title="$seoTitle" :description="$seoDescription">
    <div class="mx-auto max-w-5xl px-4 py-10 sm:px-6 lg:px-8">
        <a href="{{ route('shop') }}" wire:navigate class="text-sm text-gray-500 hover:text-gray-700">&larr; Volver a la tienda</a>

        <div class="mt-6 grid grid-cols-1 gap-10 md:grid-cols-2">
            <div>
                @if ($product->images->isNotEmpty())
                    <div class="aspect-square overflow-hidden rounded-lg bg-gray-100">
                        <x-responsive-image
                            :src="$product->images->first()->url"
                            :srcset="$product->images->first()->srcset"
                            sizes="(min-width: 768px) 50vw, 100vw"
                            :alt="$product->name"
                            :eager="true"
                            class="h-full w-full object-cover"
                        />
                    </div>
                    @if ($product->images->count() > 1)
                        <div class="mt-3 grid grid-cols-4 gap-2">
                            @foreach ($product->images->skip(1) as $image)
                                <div class="aspect-square overflow-hidden rounded bg-gray-100">
                                    <x-responsive-image
                                        :src="$image->url"
                                        :srcset="$image->srcset"
                                        sizes="128px"
                                        alt=""
                                        class="h-full w-full object-cover"
                                    />
                                </div>
                            @endforeach
                        </div>
                    @endif
                @else
                    <div class="aspect-square rounded-lg bg-gray-100"></div>
                @endif
            </div>

            <div>
                @if ($product->category)
                    <a href="{{ route('category.show', $product->category->slug) }}" wire:navigate class="text-sm font-medium text-indigo-600 hover:underline">
                        {{ $product->category->name }}
                    </a>
                @endif

                <h1 class="mt-1 text-3xl font-bold text-gray-900">{{ $product->name }}</h1>
                <p class="mt-3 text-2xl font-semibold text-gray-900">${{ number_format($product->price, 2) }}</p>

                @if ($product->description)
                    <p class="mt-4 text-gray-600">{{ $product->description }}</p>
                @endif

                <div class="mt-8">
                    <livewire:add-to-cart :product="$product" />
                </div>
            </div>
        </div>
    </div>

    <x-product-carousel title="Productos de la misma categoría" :products="$relatedProducts" />
    <x-product-carousel title="Productos seleccionados" :products="$featuredProducts" />
</x-storefront-shell>
