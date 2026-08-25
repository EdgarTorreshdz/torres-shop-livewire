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
        <header class="border-b border-gray-100 bg-white">
            <div class="mx-auto flex max-w-7xl items-center justify-between px-4 py-4 sm:px-6 lg:px-8">
                <a href="{{ route('home') }}" wire:navigate class="text-lg font-bold tracking-tight text-gray-900">
                    Torres <span class="text-indigo-600">Shop</span>
                </a>

                <nav class="flex items-center gap-6">
                    @php($featuredCategories = \App\Models\Category::whereNotNull('featured_order')->orderBy('featured_order')->get())
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
                    @endauth
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
