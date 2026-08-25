<div>
    @if ($added)
        <p class="mb-3 text-sm font-medium text-green-700">{{ $added }}</p>
    @endif

    @if ($product->stock > 0)
        <div class="flex items-center gap-3">
            <input
                type="number"
                wire:model="quantity"
                min="1"
                max="{{ $product->stock }}"
                class="w-20 rounded border-gray-300 text-sm"
            />
            <button
                type="button"
                wire:click="add"
                class="rounded-full bg-gray-900 px-6 py-2.5 text-sm font-semibold text-white hover:bg-gray-700"
            >
                Agregar al carrito
            </button>
        </div>
        @error('quantity')
            <p class="mt-2 text-sm text-red-600">{{ $message }}</p>
        @enderror
    @else
        <p class="rounded bg-gray-100 px-4 py-2 text-sm font-medium text-gray-600">Agotado</p>
    @endif
</div>
