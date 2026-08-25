<a href="{{ route('cart') }}" wire:navigate class="relative flex items-center gap-1.5 text-sm font-medium text-gray-700 hover:text-gray-900">
    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
        <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 3h1.386c.51 0 .955.343 1.087.835l.383 1.437M7.5 14.25a3 3 0 0 0-3 3h15.75m-12.75-3h11.218c1.121-2.3 1.816-4.798 2.011-7.442.023-.301-.214-.558-.516-.558H5.106M7.5 14.25L5.106 5.272M6 20.25a.75.75 0 1 1-1.5 0 .75.75 0 0 1 1.5 0Zm12.75 0a.75.75 0 1 1-1.5 0 .75.75 0 0 1 1.5 0Z" />
    </svg>
    {{ __('Carrito') }}
    @if ($count > 0)
        <span class="flex h-5 min-w-5 items-center justify-center rounded-full bg-gray-800 px-1 text-xs font-semibold text-white">
            {{ $count }}
        </span>
    @endif
</a>
