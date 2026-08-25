{{--
    The color swatches/gallery below are plain Alpine, not Livewire — a
    photo swap on swatch click is purely visual and doesn't need a server
    round trip, and all the data it needs (every color's images) is
    already on the page from ProductController::show()'s eager load. The
    embedded <livewire:add-to-cart> component still needs to know which
    color is selected (to validate stock/price server-side), so
    selectColor() below also calls the global Livewire.dispatch() JS
    helper — see App\Livewire\AddToCart's #[On('color-selected')] listener
    for the other half of that handshake.
--}}
<x-storefront-shell :title="$seoTitle" :description="$seoDescription">
    <div
        class="mx-auto max-w-5xl px-4 py-10 sm:px-6 lg:px-8"
        x-data="{
            colors: @js($product->colors->map(fn ($c) => [
                'id' => $c->id,
                'name' => $c->name,
                'images' => $c->images->map(fn ($i) => ['url' => $i->url, 'srcset' => $i->srcset])->values(),
            ])->values()),
            defaultImages: @js($product->images->map(fn ($i) => ['url' => $i->url, 'srcset' => $i->srcset])->values()),
            selectedColorId: {{ $product->colors->first()?->id ?? 'null' }},
            activeIndex: 0,
            get activeImages() {
                const color = this.colors.find(c => c.id === this.selectedColorId);
                const images = (color && color.images.length) ? color.images : this.defaultImages;
                return images.length ? images : [null];
            },
            selectColor(id) {
                this.selectedColorId = id;
                this.activeIndex = 0;
                if (window.Livewire) { window.Livewire.dispatch('color-selected', { colorId: id }); }
            },
        }"
    >
        <a href="{{ route('shop') }}" wire:navigate class="text-sm text-gray-500 hover:text-gray-700">&larr; Volver a la tienda</a>

        <div class="mt-6 grid grid-cols-1 gap-10 md:grid-cols-2">
            <div>
                <template x-if="activeImages[activeIndex]">
                    <div class="aspect-square overflow-hidden rounded-lg bg-gray-100">
                        <img
                            :src="activeImages[activeIndex].url"
                            :srcset="activeImages[activeIndex].srcset"
                            sizes="(min-width: 768px) 50vw, 100vw"
                            class="h-full w-full object-cover"
                        />
                    </div>
                </template>
                <template x-if="!activeImages[activeIndex]">
                    <div class="aspect-square rounded-lg bg-gray-100"></div>
                </template>

                <template x-if="activeImages.length > 1">
                    <div class="mt-3 grid grid-cols-4 gap-2">
                        <template x-for="(image, index) in activeImages" :key="index">
                            <button
                                type="button"
                                @click="activeIndex = index"
                                class="aspect-square overflow-hidden rounded bg-gray-100"
                                :class="activeIndex === index ? 'ring-2 ring-gray-900' : ''"
                            >
                                <img :src="image.url" :srcset="image.srcset" sizes="128px" class="h-full w-full object-cover" />
                            </button>
                        </template>
                    </div>
                </template>
            </div>

            <div>
                @if ($product->category)
                    <a href="{{ route('category.show', $product->category->slug) }}" wire:navigate class="text-sm font-medium text-indigo-600 hover:underline">
                        {{ $product->category->name }}
                    </a>
                @endif

                <h1 class="mt-1 text-3xl font-bold text-gray-900">{{ $product->name }}</h1>
                @if ($product->sku)
                    <p class="mt-1 text-xs text-gray-400">Código: {{ $product->sku }}</p>
                @endif

                @if ($product->description)
                    <p class="mt-4 text-gray-600">{{ $product->description }}</p>
                @endif

                @if ($product->material)
                    <p class="mt-2 text-sm">
                        <span class="font-medium text-gray-700">Material:</span>
                        <span class="text-gray-600">{{ $product->material }}</span>
                    </p>
                @endif

                @if ($product->colors->isNotEmpty())
                    <div class="mt-6">
                        <p class="text-sm font-medium text-gray-700">
                            Color: <span x-text="colors.find(c => c.id === selectedColorId)?.name ?? ''"></span>
                        </p>
                        <div class="mt-2 flex flex-wrap gap-2">
                            @foreach ($product->colors as $color)
                                <button
                                    type="button"
                                    @click="selectColor({{ $color->id }})"
                                    :class="selectedColorId === {{ $color->id }} ? 'ring-2 ring-gray-900 ring-offset-2' : 'ring-1 ring-gray-200'"
                                    class="h-14 w-14 shrink-0 overflow-hidden rounded-full {{ $color->stock <= 0 ? 'opacity-40' : '' }}"
                                    title="{{ $color->name }}{{ $color->stock <= 0 ? ' (agotado)' : '' }}"
                                >
                                    @if ($color->images->first())
                                        <img src="{{ $color->images->first()->url }}" alt="{{ $color->name }}" class="h-full w-full object-cover" />
                                    @elseif ($color->hex)
                                        <span class="block h-full w-full" style="background-color: {{ $color->hex }}"></span>
                                    @else
                                        <span class="flex h-full w-full items-center justify-center bg-gray-100 text-[10px] font-medium uppercase text-gray-500">{{ mb_substr($color->name, 0, 3) }}</span>
                                    @endif
                                </button>
                            @endforeach
                        </div>
                    </div>
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
