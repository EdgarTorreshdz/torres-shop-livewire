<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ config('app.name', 'Laravel') }}</title>

        <!-- Fonts -->
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=figtree:400,500,600,700&display=swap" rel="stylesheet" />

        <!-- Scripts -->
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="font-sans text-gray-900 antialiased">
        <div class="flex min-h-screen flex-col items-center justify-center bg-gray-50 px-4 py-10">
            <a href="{{ route('home') }}" wire:navigate class="text-xl font-bold tracking-tight text-gray-900">
                Torres <span class="text-indigo-600">Shop</span>
            </a>

            <div class="mt-8 w-full max-w-md rounded-lg border border-gray-200 bg-white p-8 shadow-sm">
                {{ $slot }}
            </div>

            <a href="{{ route('home') }}" wire:navigate class="mt-6 text-sm text-gray-500 hover:text-gray-700">
                &larr; Volver a la tienda
            </a>
        </div>
    </body>
</html>
