@props(['title' => null, 'description' => null, 'noindex' => false])
<!DOCTYPE html>
<html lang="es">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ $title ? "{$title} — " . config('app.name') : config('app.name') }}</title>
        @if ($description)
            <meta name="description" content="{{ $description }}">
        @endif
        @if ($noindex)
            <meta name="robots" content="noindex, nofollow">
        @endif

        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=figtree:400,500,600,700&display=swap" rel="stylesheet" />

        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="font-sans text-gray-900 antialiased">
        @php($featuredCategories = \App\Models\Category::whereNotNull('featured_order')->orderBy('featured_order')->get())

        <header
            x-data="{ mobileOpen: false }"
            x-effect="document.body.classList.toggle('overflow-hidden', mobileOpen)"
            @keydown.escape.window="mobileOpen = false"
            class="relative border-b border-gray-100 bg-white"
        >
            <div class="mx-auto flex max-w-7xl items-center justify-between px-4 py-4 sm:px-6 lg:px-8">
                <a href="{{ route('home') }}" wire:navigate class="text-lg font-bold tracking-tight text-gray-900">
                    Torres <span class="text-indigo-600">Shop</span>
                </a>

                <!-- Desktop nav -->
                <nav class="hidden items-center gap-6 lg:flex">
                    @if ($featuredCategories->isNotEmpty())
                        <div x-data="{ open: false }" @click.outside="open = false" class="relative">
                            <button type="button" @click="open = ! open" class="flex items-center gap-1 text-sm font-medium text-gray-700 hover:text-gray-900">
                                {{ __('Categorías') }}
                                <svg class="h-3 w-3" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd" /></svg>
                            </button>
                            <div x-show="open" x-cloak class="absolute left-0 z-10 mt-2 w-56 rounded-lg border border-gray-200 bg-white py-2 shadow-lg">
                                @foreach ($featuredCategories as $category)
                                    <a href="{{ route('category.show', $category->slug) }}" wire:navigate class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-50">
                                        {{ $category->name }}
                                    </a>
                                @endforeach
                            </div>
                        </div>
                    @endif
                    <a href="{{ route('shop') }}" wire:navigate class="text-sm font-medium text-gray-700 hover:text-gray-900">{{ __('Tienda') }}</a>
                    <livewire:cart-count />

                    @auth
                        @if (auth()->user()->hasRole('admin') || auth()->user()->getAllPermissions()->isNotEmpty())
                            <a href="{{ route('admin.dashboard') }}" wire:navigate class="text-sm font-medium text-gray-700 hover:text-gray-900">{{ __('Admin') }}</a>
                        @endif
                        <a href="{{ route('dashboard') }}" wire:navigate class="text-sm font-medium text-gray-700 hover:text-gray-900">{{ auth()->user()->name }}</a>
                    @else
                        <a href="{{ route('login') }}" wire:navigate class="text-sm font-medium text-gray-700 hover:text-gray-900">{{ __('Iniciar sesión') }}</a>
                        <a href="{{ route('register') }}" wire:navigate class="rounded-full bg-gray-900 px-4 py-2 text-sm font-medium text-white hover:bg-gray-700">{{ __('Registrarse') }}</a>
                    @endauth
                </nav>

                <!-- Mobile controls -->
                <div class="flex items-center gap-4 lg:hidden">
                    <livewire:cart-count />
                    <button
                        type="button"
                        @click="mobileOpen = true"
                        class="text-gray-700"
                        aria-label="{{ __('Abrir menú') }}"
                        aria-expanded="false"
                        :aria-expanded="mobileOpen.toString()"
                    >
                        <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.75">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6.75h16.5M3.75 12h16.5M3.75 17.25h16.5" />
                        </svg>
                    </button>
                </div>
            </div>

            <!-- Mobile full-screen menu -->
            <div
                x-show="mobileOpen"
                x-cloak
                x-transition:enter="transition ease-out duration-150"
                x-transition:enter-start="opacity-0"
                x-transition:enter-end="opacity-100"
                x-transition:leave="transition ease-in duration-100"
                x-transition:leave-start="opacity-100"
                x-transition:leave-end="opacity-0"
                class="fixed inset-0 z-50 flex h-dvh w-full flex-col overflow-y-auto bg-white lg:hidden"
                role="dialog"
                aria-modal="true"
            >
                <div class="flex items-center justify-between border-b border-gray-100 px-4 py-4">
                    <a href="{{ route('home') }}" wire:navigate @click="mobileOpen = false" class="text-lg font-bold tracking-tight text-gray-900">
                        Torres <span class="text-indigo-600">Shop</span>
                    </a>
                    <button type="button" @click="mobileOpen = false" class="text-gray-700" aria-label="{{ __('Cerrar menú') }}">
                        <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.75">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>

                <nav class="flex flex-1 flex-col gap-1 px-4 py-6">
                    <a href="{{ route('shop') }}" wire:navigate @click="mobileOpen = false" class="rounded-lg px-3 py-3 text-base font-medium text-gray-900 hover:bg-gray-50">
                        {{ __('Tienda') }}
                    </a>

                    @if ($featuredCategories->isNotEmpty())
                        <p class="px-3 pt-6 pb-1 text-xs font-semibold uppercase tracking-wide text-gray-400">{{ __('Categorías') }}</p>
                        @foreach ($featuredCategories as $category)
                            <a href="{{ route('category.show', $category->slug) }}" wire:navigate @click="mobileOpen = false" class="rounded-lg px-3 py-3 text-base font-medium text-gray-700 hover:bg-gray-50">
                                {{ $category->name }}
                            </a>
                        @endforeach
                    @endif

                    <div class="mt-6 border-t border-gray-100 pt-6">
                        @auth
                            @if (auth()->user()->hasRole('admin') || auth()->user()->getAllPermissions()->isNotEmpty())
                                <a href="{{ route('admin.dashboard') }}" wire:navigate @click="mobileOpen = false" class="block rounded-lg px-3 py-3 text-base font-medium text-gray-700 hover:bg-gray-50">
                                    {{ __('Admin') }}
                                </a>
                            @endif
                            <a href="{{ route('dashboard') }}" wire:navigate @click="mobileOpen = false" class="block rounded-lg px-3 py-3 text-base font-medium text-gray-700 hover:bg-gray-50">
                                {{ auth()->user()->name }}
                            </a>
                        @else
                            <a href="{{ route('login') }}" wire:navigate @click="mobileOpen = false" class="block rounded-lg px-3 py-3 text-base font-medium text-gray-700 hover:bg-gray-50">
                                {{ __('Iniciar sesión') }}
                            </a>
                            <a href="{{ route('register') }}" wire:navigate @click="mobileOpen = false" class="mt-2 block rounded-lg bg-gray-900 px-3 py-3 text-center text-base font-medium text-white hover:bg-gray-700">
                                {{ __('Registrarse') }}
                            </a>
                        @endauth
                    </div>
                </nav>
            </div>
        </header>

        <main>
            {{ $slot }}
        </main>

        <footer class="mt-16 border-t border-gray-100 bg-white py-8">
            <div class="mx-auto max-w-7xl px-4 text-center text-sm text-gray-500 sm:px-6 lg:px-8">
                &copy; {{ now()->year }} Torres Shop — pieza de portafolio, sin cobro real.
            </div>
        </footer>
    </body>
</html>
