{{--
    Home hero carousel driven by /admin/banners. Each Banner has up to
    three independent images (desktop/tablet/mobile — different framing,
    not just resizes of one image) picked via <picture><source media="...">,
    each still served with ResponsiveImage's own srcset so the browser also
    picks the right pixel density within a breakpoint. wire:navigate is
    safe to put on every slide unconditionally — Livewire's navigate.js
    already skips cross-origin/external hrefs and falls back to a normal
    browser navigation for those, so an admin-typed external URL just works.

    Renders nothing if there are no active banners (see Home::with()),
    so the page never shows an empty gray box while nobody has set one up.
--}}
@props(['banners'])
@if ($banners->isNotEmpty())
    <section
        x-data="{
            active: 0,
            total: {{ $banners->count() }},
            timer: null,
            start() {
                this.stop();
                if (this.total > 1) {
                    this.timer = setInterval(() => this.next(), 6000);
                }
            },
            stop() { clearInterval(this.timer); },
            next() { this.active = (this.active + 1) % this.total; },
            prev() { this.active = (this.active - 1 + this.total) % this.total; },
        }"
        x-init="start()"
        @mouseenter="stop()"
        @mouseleave="start()"
        class="relative h-56 w-full overflow-hidden bg-gray-100 sm:h-72 lg:h-[28rem]"
    >
        @foreach ($banners as $index => $banner)
            <a
                href="{{ $banner->url }}"
                wire:navigate
                x-show="active === {{ $index }}"
                x-cloak
                x-transition:enter="transition ease-out duration-500"
                x-transition:enter-start="opacity-0"
                x-transition:enter-end="opacity-100"
                class="absolute inset-0 block"
                aria-label="{{ $banner->title }}"
            >
                <picture>
                    @if ($banner->tablet_image_url)
                        <source media="(min-width: 640px) and (max-width: 1023px)" srcset="{{ $banner->tablet_srcset ?: $banner->tablet_image_url }}" sizes="100vw">
                    @endif
                    @if ($banner->desktop_image_url)
                        <source media="(min-width: 1024px)" srcset="{{ $banner->desktop_srcset ?: $banner->desktop_image_url }}" sizes="100vw">
                    @endif
                    <img
                        src="{{ $banner->mobile_image_url ?? $banner->desktop_image_url ?? $banner->tablet_image_url }}"
                        srcset="{{ $banner->mobile_srcset ?? $banner->desktop_srcset ?? $banner->tablet_srcset }}"
                        sizes="100vw"
                        alt="{{ $banner->title }}"
                        loading="{{ $index === 0 ? 'eager' : 'lazy' }}"
                        class="h-full w-full object-cover"
                    />
                </picture>

                @if ($banner->title || $banner->description)
                    <div class="absolute inset-x-0 bottom-0 bg-gradient-to-t from-black/70 to-transparent px-4 py-6 sm:px-8 sm:py-8">
                        <h2 class="text-lg font-bold text-white sm:text-2xl">{{ $banner->title }}</h2>
                        @if ($banner->description)
                            <p class="mt-1 max-w-xl text-sm text-gray-200 sm:text-base">{{ $banner->description }}</p>
                        @endif
                    </div>
                @endif
            </a>
        @endforeach

        @if ($banners->count() > 1)
            <button type="button" @click="prev()" aria-label="{{ __('Banner anterior') }}" class="absolute left-2 top-1/2 -translate-y-1/2 rounded-full bg-white/80 p-2 text-gray-900 hover:bg-white sm:left-4">
                <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7" /></svg>
            </button>
            <button type="button" @click="next()" aria-label="{{ __('Siguiente banner') }}" class="absolute right-2 top-1/2 -translate-y-1/2 rounded-full bg-white/80 p-2 text-gray-900 hover:bg-white sm:right-4">
                <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7" /></svg>
            </button>

            <div class="absolute inset-x-0 bottom-2 flex justify-center gap-2">
                @foreach ($banners as $index => $banner)
                    <button
                        type="button"
                        @click="active = {{ $index }}"
                        :class="active === {{ $index }} ? 'bg-white' : 'bg-white/50'"
                        class="h-2 w-2 rounded-full"
                        aria-label="{{ __('Ir al banner') }} {{ $index + 1 }}"
                    ></button>
                @endforeach
            </div>
        @endif
    </section>
@endif
