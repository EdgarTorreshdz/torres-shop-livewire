<x-storefront-shell title="Pedido confirmado" :noindex="true">
    <div class="mx-auto max-w-2xl px-4 py-16 text-center sm:px-6 lg:px-8">
        <h1 class="text-2xl font-bold text-gray-900">¡Gracias por tu pedido, {{ $order->customer_name }}!</h1>
        <p class="mt-2 text-gray-600">Pedido #{{ $order->id }} — total ${{ number_format($order->total, 2) }}</p>

        <div class="mt-8 divide-y divide-gray-200 rounded-lg border border-gray-200 text-left">
            @foreach ($order->items as $item)
                <div class="flex items-center justify-between px-4 py-3 text-sm">
                    <span>{{ $item->product_name }}{{ $item->variant_label ? " ({$item->variant_label})" : '' }} &times; {{ $item->quantity }}</span>
                    <span class="font-medium">${{ number_format($item->subtotal, 2) }}</span>
                </div>
            @endforeach
        </div>

        <a href="{{ route('shop') }}" wire:navigate class="mt-8 inline-block rounded-full bg-gray-900 px-6 py-2.5 text-sm font-semibold text-white hover:bg-gray-700">
            Seguir comprando
        </a>
    </div>
</x-storefront-shell>
