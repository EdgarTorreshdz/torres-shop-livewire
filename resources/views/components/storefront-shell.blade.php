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
