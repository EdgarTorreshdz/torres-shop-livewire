<div>
    <p class="text-2xl font-semibold text-gray-900">${{ number_format($this->currentPrice, 2) }}</p>

    @error('colorId')
        <p class="mt-3 text-sm text-red-600">{{ $message }}</p>
    @enderror

    @if (! empty($this->sizeOptions))
        <div class="mt-6">
            <p class="text-sm font-medium text-gray-700">Talla</p>
            <div class="mt-2 flex flex-wrap gap-2">
                @foreach ($this->sizeOptions as $option)
                    <button
                        type="button"
                        wire:click="selectSize({{ $option['id'] }})"
                        @disabled($option['stock'] <= 0)
                        @class([
                            'min-w-14 rounded-lg border px-4 py-2 text-sm font-medium transition',
                            'border-gray-900 bg-gray-900 text-white' => $sizeId === $option['id'],
                            'border-gray-300 text-gray-700 hover:border-gray-900' => $sizeId !== $option['id'] && $option['stock'] > 0,
                            // Sold out in this color: still listed (the size
                            // exists, it's just unavailable here) but clearly
                            // not pickable, rather than silently missing.
                            'cursor-not-allowed border-gray-200 text-gray-300 line-through' => $option['stock'] <= 0,
                        ])
                        title="{{ $option['stock'] <= 0 ? 'Agotado en este color' : $option['stock'].' disponibles' }}"
                    >
                        {{ $option['name'] }}
                    </button>
                @endforeach
            </div>
            @error('sizeId')
                <p class="mt-2 text-sm text-red-600">{{ $message }}</p>
            @enderror
        </div>
    @endif

    @if ($added)
        <p class="mt-4 text-sm font-medium text-green-700">{{ $added }}</p>
    @endif

    @if ($this->currentStock > 0)
        <div class="mt-6 flex items-center gap-3">
            <input
                type="number"
                wire:model="quantity"
                min="1"
                max="{{ $this->currentStock }}"
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
        <p class="mt-6 rounded bg-gray-100 px-4 py-2 text-sm font-medium text-gray-600">
            Agotado{{ $this->selectedVariant?->label ? " en \"{$this->selectedVariant->label}\"" : ($this->selectedColor ? " en \"{$this->selectedColor->name}\"" : '') }}
        </p>
    @endif
</div>
