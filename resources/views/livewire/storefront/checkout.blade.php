<?php

use App\Models\Order;
use App\Models\Product;
use App\Models\ProductColor;
use App\Services\Cart;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('components.storefront-shell', ['title' => 'Checkout', 'noindex' => true])] class extends Component
{
    public string $customer_name = '';
    public string $customer_email = '';
    public string $customer_phone = '';
    public string $shipping_address = '';

    public function mount(): void
    {
        if (Cart::items()->isEmpty()) {
            $this->redirect(route('cart'), navigate: true);
        }
    }

    /**
     * Same guest-checkout transaction as the API version of this project
     * (torres-shop-api's OrderController::store): price/stock are always
     * recomputed from the database, never trusted from the client — here
     * the "client" is the session cart, but the rule is the same. Row
     * locking (lockForUpdate) stops two simultaneous checkouts from both
     * overselling the last unit of stock — now locking whichever row
     * actually owns the stock for that line: the ProductColor when one
     * was chosen, the Product itself otherwise.
     */
    public function placeOrder(): void
    {
        $validated = $this->validate([
            'customer_name' => ['required', 'string', 'max:255'],
            'customer_email' => ['required', 'string', 'email', 'max:255'],
            'customer_phone' => ['nullable', 'string', 'max:50'],
            'shipping_address' => ['required', 'string', 'max:1000'],
        ]);

        $cartLines = Cart::raw();

        if (empty($cartLines)) {
            $this->redirect(route('cart'), navigate: true);

            return;
        }

        try {
            $order = DB::transaction(function () use ($validated, $cartLines) {
                $order = Order::create([
                    'user_id' => auth()->id(),
                    'customer_name' => $validated['customer_name'],
                    'customer_email' => $validated['customer_email'],
                    'customer_phone' => $validated['customer_phone'] ?: null,
                    'shipping_address' => $validated['shipping_address'],
                    'total' => 0,
                    'status' => 'pending',
                ]);

                $total = 0;

                foreach ($cartLines as $line) {
                    $product = Product::where('is_active', true)
                        ->lockForUpdate()
                        ->findOrFail($line['product_id']);

                    $color = $line['color_id']
                        ? ProductColor::where('product_id', $product->id)->lockForUpdate()->findOrFail($line['color_id'])
                        : null;

                    $availableStock = $color ? $color->stock : $product->stock;
                    $quantity = $line['quantity'];
                    $label = $product->name.($color ? " ({$color->name})" : '');

                    if ($availableStock < $quantity) {
                        throw \Illuminate\Validation\ValidationException::withMessages([
                            'shipping_address' => "No hay suficiente inventario de \"{$label}\".",
                        ]);
                    }

                    $unitPrice = $color?->effective_price ?? $product->price;
                    $subtotal = $unitPrice * $quantity;
                    $total += $subtotal;

                    $order->items()->create([
                        'product_id' => $product->id,
                        'product_name' => $product->name,
                        'color_name' => $color?->name,
                        'unit_price' => $unitPrice,
                        'quantity' => $quantity,
                        'subtotal' => $subtotal,
                    ]);

                    if ($color) {
                        $color->decrement('stock', $quantity);
                    } else {
                        $product->decrement('stock', $quantity);
                    }
                }

                $order->update(['total' => $total]);

                return $order;
            });
        } catch (\Illuminate\Validation\ValidationException $e) {
            $this->addError('shipping_address', $e->validator->errors()->first());

            return;
        }

        Cart::clear();
        $this->dispatch('cart-updated');

        $this->redirect(route('checkout.success', $order->id), navigate: true);
    }

    public function with(): array
    {
        return [
            'items' => Cart::items(),
            'total' => Cart::total(),
        ];
    }
}; ?>

<div class="mx-auto max-w-4xl px-4 py-10 sm:px-6 lg:px-8">
    <h1 class="text-2xl font-bold text-gray-900">Checkout</h1>

    <div class="mt-8 grid grid-cols-1 gap-10 md:grid-cols-2">
        <form wire:submit="placeOrder" class="flex flex-col gap-4">
            <label class="flex flex-col gap-1 text-sm text-gray-700">
                Nombre completo
                <input type="text" wire:model="customer_name" required class="rounded border-gray-300" />
                @error('customer_name') <span class="text-xs text-red-600">{{ $message }}</span> @enderror
            </label>

            <label class="flex flex-col gap-1 text-sm text-gray-700">
                Email
                <input type="email" wire:model="customer_email" required class="rounded border-gray-300" />
                @error('customer_email') <span class="text-xs text-red-600">{{ $message }}</span> @enderror
            </label>

            <label class="flex flex-col gap-1 text-sm text-gray-700">
                Teléfono (opcional)
                <input type="text" wire:model="customer_phone" class="rounded border-gray-300" />
            </label>

            <label class="flex flex-col gap-1 text-sm text-gray-700">
                Dirección de envío
                <textarea wire:model="shipping_address" required rows="3" class="rounded border-gray-300"></textarea>
                @error('shipping_address') <span class="text-xs text-red-600">{{ $message }}</span> @enderror
            </label>

            <button type="submit" class="mt-2 w-fit rounded-full bg-gray-900 px-6 py-2.5 text-sm font-semibold text-white hover:bg-gray-700">
                Confirmar pedido
            </button>

            <p class="text-xs text-gray-400">Este es un checkout de demostración — no se realiza ningún cobro real.</p>
        </form>

        <div>
            <h2 class="text-sm font-semibold uppercase tracking-wide text-gray-500">Resumen</h2>
            <div class="mt-3 divide-y divide-gray-200 border-y border-gray-200">
                @foreach ($items as $item)
                    <div class="flex items-center justify-between py-3 text-sm">
                        <span>{{ $item->product->name }}{{ $item->color ? " ({$item->color->name})" : '' }} &times; {{ $item->quantity }}</span>
                        <span class="font-medium">${{ number_format($item->subtotal, 2) }}</span>
                    </div>
                @endforeach
            </div>
            <div class="mt-3 flex items-center justify-between font-semibold text-gray-900">
                <span>Total</span>
                <span>${{ number_format($total, 2) }}</span>
            </div>
        </div>
    </div>
</div>
