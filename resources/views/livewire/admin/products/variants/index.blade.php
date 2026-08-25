<?php

use App\Concerns\Notifies;
use App\Models\ActivityLog;
use App\Models\Product;
use App\Models\Size;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

/**
 * The color x size inventory matrix for one product — the only screen
 * where stock is edited once a product has variants (products.stock and
 * the old per-color stock both stopped being the source of truth; see the
 * migration that introduced product_variants).
 *
 * A product's sizes aren't stored in a product/size pivot: they *are* the
 * distinct sizes among its variants. Checking a size here materializes one
 * variant per color; unchecking deletes those rows. That keeps a single
 * place for the fact instead of a pivot that could disagree with the
 * matrix it's supposed to describe.
 */
new #[Layout('layouts.app')] class extends Component
{
    use Notifies;

    public Product $product;

    /** @var array<int> size ids checked in the "tallas de este producto" picker */
    public array $selectedSizeIds = [];

    /** @var array<int, string> variant id => stock, bound to the matrix inputs */
    public array $stocks = [];

    public function mount(Product $product): void
    {
        abort_unless(auth()->user()->can('products.manage'), 403);

        $this->product = $product;
        $this->selectedSizeIds = $this->currentSizeIds();
    }

    private function currentSizeIds(): array
    {
        return $this->product->variants()->whereNotNull('size_id')->distinct()->pluck('size_id')->all();
    }

    /**
     * Rebuild the matrix so it holds exactly one variant per
     * (color x selected size) pair. Rows for sizes that were unchecked are
     * deleted — that's real inventory disappearing, which is why the
     * button asks for confirmation when anything would be removed.
     */
    public function syncSizes(): void
    {
        $this->validate([
            'selectedSizeIds' => ['array'],
            'selectedSizeIds.*' => ['integer', 'exists:sizes,id'],
        ]);

        $sizeIds = array_map('intval', $this->selectedSizeIds);
        $colorIds = $this->product->colors()->pluck('id')->all();

        // A product with neither dimension has no variants at all and goes
        // back to using products.stock, exactly as before this feature.
        $colorSlots = $colorIds ?: [null];
        $sizeSlots = $sizeIds ?: [null];

        $removed = $this->product->variants()
            ->when($sizeIds, fn ($q) => $q->whereNotIn('size_id', $sizeIds)->orWhereNull('size_id'))
            ->when(! $sizeIds, fn ($q) => $q->whereNotNull('size_id'))
            ->count();

        // Drop anything outside the new grid first, then fill the gaps.
        $this->product->variants()
            ->when($sizeIds, fn ($q) => $q->where(fn ($q2) => $q2->whereNotIn('size_id', $sizeIds)->orWhereNull('size_id')))
            ->when(! $sizeIds, fn ($q) => $q->whereNotNull('size_id'))
            ->delete();

        if (! $colorIds && ! $sizeIds) {
            $this->product->variants()->delete();
        } else {
            foreach ($colorSlots as $colorId) {
                foreach ($sizeSlots as $sizeId) {
                    $this->product->variants()->firstOrCreate(
                        ['product_color_id' => $colorId, 'size_id' => $sizeId],
                        ['stock' => 0],
                    );
                }
            }
        }

        $this->product->refresh();
        $this->stocks = [];

        ActivityLog::record(
            auth()->user(),
            'product.variants_synced',
            "Actualizó las tallas de \"{$this->product->name}\"",
            $this->product,
            newValues: ['tallas' => Size::whereIn('id', $sizeIds)->pluck('name')->all()],
        );

        $this->notifySuccess($removed > 0
            ? "Tallas actualizadas. Se eliminaron {$removed} combinación(es) con su stock."
            : 'Tallas actualizadas.');
    }

    public function saveStock(): void
    {
        $this->validate([
            'stocks' => ['array'],
            'stocks.*' => ['required', 'integer', 'min:0'],
        ], [
            'stocks.*.required' => 'El stock no puede quedar vacío.',
            'stocks.*.integer' => 'El stock debe ser un número entero.',
            'stocks.*.min' => 'El stock no puede ser negativo.',
        ]);

        $before = $this->product->variants()->pluck('stock', 'id')->all();

        foreach ($this->stocks as $variantId => $stock) {
            $this->product->variants()->whereKey($variantId)->update(['stock' => (int) $stock]);
        }

        $this->product->refresh();

        ActivityLog::record(
            auth()->user(),
            'product.variants_stock_updated',
            "Actualizó el stock por color/talla de \"{$this->product->name}\"",
            $this->product,
            oldValues: ['stock_por_variante' => $before],
            newValues: ['stock_por_variante' => $this->product->variants()->pluck('stock', 'id')->all()],
        );

        $this->notifySuccess('Stock actualizado correctamente.');
    }

    public function with(): array
    {
        $product = $this->product->load(['colors', 'variants.color', 'variants.size']);

        foreach ($product->variants as $variant) {
            $this->stocks[$variant->id] ??= (string) $variant->stock;
        }

        return [
            'allSizes' => Size::orderBy('sort_order')->orderBy('name')->get(),
            // A single null entry stands in for "this dimension doesn't
            // apply", so the matrix renders the same way whether the
            // product has colors, sizes, both or neither.
            'colorRows' => $product->colors->isNotEmpty() ? $product->colors : collect([null]),
            'sizeCols' => $product->available_sizes->isNotEmpty() ? $product->available_sizes : collect([null]),
            'variants' => $product->variants,
        ];
    }
}; ?>

<div>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Inventario de') }} "{{ $product->name }}"
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="mx-auto max-w-5xl space-y-6 sm:px-6 lg:px-8">
            <a href="{{ route('admin.productos.editar', $product) }}" wire:navigate class="inline-block text-sm text-gray-500 hover:text-gray-700">&larr; Volver al producto</a>

            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6">
                <div class="flex items-start justify-between gap-4">
                    <div>
                        <h3 class="text-sm font-medium uppercase tracking-wide text-gray-500">Tallas de este producto</h3>
                        <p class="mt-1 max-w-2xl text-sm text-gray-500">
                            Elige del <a href="{{ route('admin.tallas') }}" wire:navigate class="text-indigo-600 hover:underline">catálogo de tallas</a>
                            cuáles se venden en este producto. Si no eliges ninguna, el producto se vende sin talla.
                        </p>
                    </div>
                </div>

                @if ($allSizes->isEmpty())
                    <p class="mt-4 rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800">
                        El catálogo de tallas está vacío.
                        <a href="{{ route('admin.tallas') }}" wire:navigate class="font-medium underline">Crea las tallas primero</a>
                        para poder asignarlas aquí.
                    </p>
                @else
                    <div class="mt-4 flex flex-wrap gap-3">
                        @foreach ($allSizes as $size)
                            <label class="flex items-center gap-2 rounded-lg border border-gray-200 px-3 py-2 text-sm" wire:key="size-option-{{ $size->id }}">
                                <input type="checkbox" value="{{ $size->id }}" wire:model="selectedSizeIds" class="rounded border-gray-300" />
                                {{ $size->name }}
                            </label>
                        @endforeach
                    </div>
                    <button
                        type="button"
                        x-on:click="confirmAction('¿Aplicar estas tallas? Las combinaciones de tallas que quites se eliminan junto con su stock.', () => $wire.syncSizes())"
                        class="mt-4 rounded-full bg-gray-900 px-4 py-2 text-sm font-medium text-white hover:bg-gray-700"
                    >
                        Aplicar tallas
                    </button>
                @endif
            </div>

            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6">
                <div class="flex items-center justify-between">
                    <h3 class="text-sm font-medium uppercase tracking-wide text-gray-500">Stock por color y talla</h3>
                    <a href="{{ route('admin.productos.colores', $product) }}" wire:navigate class="text-sm text-indigo-600 hover:underline">
                        Gestionar colores &rarr;
                    </a>
                </div>

                @if ($variants->isEmpty())
                    <p class="mt-4 text-sm text-gray-500">
                        Este producto no tiene colores ni tallas, así que su stock se controla con el campo
                        <strong>Stock</strong> del formulario del producto.
                    </p>
                @else
                    <form wire:submit="saveStock" class="mt-4">
                        <div class="overflow-x-auto">
                            <table class="w-full text-left text-sm">
                                <thead>
                                    <tr class="border-b text-gray-500">
                                        <th class="py-2 pr-4">Color</th>
                                        @foreach ($sizeCols as $size)
                                            <th class="py-2 pr-4">{{ $size?->name ?? 'Talla única' }}</th>
                                        @endforeach
                                        <th class="py-2">Total</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($colorRows as $color)
                                        <tr class="border-b" wire:key="variant-row-{{ $color?->id ?? 0 }}">
                                            <td class="py-2 pr-4 font-medium text-gray-900">
                                                {{ $color?->name ?? 'Sin color' }}
                                            </td>
                                            @foreach ($sizeCols as $size)
                                                @php($variant = $variants->first(fn ($v) => $v->product_color_id === $color?->id && $v->size_id === $size?->id))
                                                <td class="py-2 pr-4">
                                                    @if ($variant)
                                                        <input
                                                            type="number"
                                                            min="0"
                                                            wire:model="stocks.{{ $variant->id }}"
                                                            class="w-20 rounded text-sm @error('stocks.'.$variant->id) border-red-500 ring-1 ring-red-500 @else border-gray-300 @enderror"
                                                        />
                                                    @else
                                                        <span class="text-gray-300">—</span>
                                                    @endif
                                                </td>
                                            @endforeach
                                            <td class="py-2 font-medium text-gray-700">
                                                {{ $variants->where('product_color_id', $color?->id)->sum('stock') }}
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>

                        @error('stocks.*') <p class="mt-2 text-xs text-red-600">{{ $message }}</p> @enderror

                        <div class="mt-4 flex items-center gap-4">
                            <button type="submit" class="rounded-full bg-gray-900 px-6 py-2.5 text-sm font-semibold text-white hover:bg-gray-700">
                                Guardar stock
                            </button>
                            <span class="text-sm text-gray-500">Stock total del producto: <strong>{{ $variants->sum('stock') }}</strong></span>
                        </div>
                    </form>
                @endif
            </div>
        </div>
    </div>
</div>
