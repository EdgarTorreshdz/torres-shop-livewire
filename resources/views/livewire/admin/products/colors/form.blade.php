<?php

use App\Concerns\Notifies;
use App\Models\ActivityLog;
use App\Models\Product;
use App\Models\ProductColor;
use App\Services\ResponsiveImage;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;
use Livewire\WithFileUploads;

new #[Layout('layouts.app')] class extends Component
{
    use WithFileUploads, Notifies;

    public Product $product;
    public ?ProductColor $color = null;

    public string $name = '';
    public ?string $hex = '';
    public ?string $price = '';
    public string $sort_order = '0';

    /** @var \Illuminate\Http\UploadedFile[] */
    public array $newImages = [];

    public function mount(Product $product, ?ProductColor $color = null): void
    {
        abort_unless(auth()->user()->can('products.manage'), 403);

        $this->product = $product;

        if ($color?->exists) {
            // Route-model-binding only checks that {color} resolves to *a*
            // row — this catches someone editing
            // /productos/5/colores/9 where color 9 really belongs to
            // product 3, which would otherwise silently let them edit
            // another product's color. 403, not 404, matching how
            // ownership mismatches are handled elsewhere in this app (see
            // account.order-show's "not your order" check).
            abort_unless($color->product_id === $product->id, 403);

            $this->color = $color->load('images');
            $this->name = $color->name;
            $this->hex = $color->hex ?? '';
            $this->price = $color->price !== null ? (string) $color->price : '';
            $this->sort_order = (string) $color->sort_order;
        }
    }

    public function save(): void
    {
        // Same '' -> null normalization as admin.products.form — see that
        // component's save() for why this has to happen before validate().
        if ($this->hex === '') {
            $this->hex = null;
        }
        if ($this->price === '') {
            $this->price = null;
        }

        $validated = $this->validate([
            'name' => ['required', 'string', 'max:255'],
            'hex' => ['nullable', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'price' => ['nullable', 'numeric', 'min:0'],
            'sort_order' => ['required', 'integer', 'min:0'],
        ], [
            'hex.regex' => 'El color debe ser un código hexadecimal válido, ej. #DC2626.',
        ]);

        $isNew = ! $this->color;

        if ($this->color) {
            $before = ActivityLog::snapshot($this->color);
            $this->color->update($validated);

            ActivityLog::record(
                auth()->user(),
                'product.color_updated',
                "Actualizó el color \"{$this->color->name}\" de \"{$this->product->name}\"",
                oldValues: $before,
                newValues: ActivityLog::snapshot($this->color),
            );
        } else {
            $this->color = $this->product->colors()->create($validated);

            // Give the new color a row in the inventory matrix for every
            // size the product already sells (or a single sizeless one) —
            // otherwise it would exist as a swatch with nowhere to record
            // its stock until someone re-applied the sizes by hand.
            //
            // For the *first* color, the product's existing colorless rows
            // are handed over to it instead of being duplicated: their
            // stock was already "all of this product", so adopting them
            // keeps that number instead of stranding it under a "Sin
            // color" row the matrix would no longer render.
            $adopted = $this->product->colors()->count() === 1
                ? $this->product->variants()->whereNull('product_color_id')->update(['product_color_id' => $this->color->id])
                : 0;

            if ($adopted === 0) {
                $sizeIds = $this->product->variants()->whereNotNull('size_id')->distinct()->pluck('size_id');

                foreach ($sizeIds->isNotEmpty() ? $sizeIds : collect([null]) as $sizeId) {
                    $this->color->variants()->create([
                        'product_id' => $this->product->id,
                        'size_id' => $sizeId,
                        'stock' => 0,
                    ]);
                }
            }

            ActivityLog::record(
                auth()->user(),
                'product.color_created',
                "Creó el color \"{$this->color->name}\" en \"{$this->product->name}\"",
                newValues: ActivityLog::snapshot($this->color),
            );
        }

        if (! empty($this->newImages)) {
            $this->uploadImages();
        }

        $this->notifySuccess($isNew ? 'Color creado correctamente.' : 'Cambios guardados correctamente.');
        $this->redirect(route('admin.productos.colores', $this->product), navigate: true);
    }

    /**
     * Same pattern as admin.products.form's uploadImages(), scoped to this
     * color instead of the product directly — product_id still gets set
     * too (every product_images row keeps it, color-specific or not; see
     * the product_colors migration), stored under
     * products/{id}/colors/{colorId}/ so a color's files don't mix with
     * the product's own base gallery on disk.
     */
    private function uploadImages(): void
    {
        $nextOrder = (int) $this->color->images()->max('sort_order') + 1;

        foreach ($this->newImages as $i => $file) {
            $path = ResponsiveImage::store($file, "products/{$this->product->id}/colors/{$this->color->id}");

            $this->color->images()->create([
                'product_id' => $this->product->id,
                'path' => $path,
                'sort_order' => $nextOrder + $i,
            ]);
        }

        ActivityLog::record(
            auth()->user(),
            'product.color_images_uploaded',
            'Subió '.count($this->newImages)." imagen(es) al color \"{$this->color->name}\"",
        );

        $this->newImages = [];
    }

    public function deleteImage(int $imageId): void
    {
        $image = $this->color->images()->findOrFail($imageId);
        $path = $image->path;
        ResponsiveImage::delete($path);
        $image->delete();

        ActivityLog::record(
            auth()->user(),
            'product.color_image_deleted',
            "Eliminó una imagen del color \"{$this->color->name}\"",
        );

        $this->color->refresh();
        $this->notifySuccess('Imagen eliminada.');
    }
}; ?>

<div>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ $color ? 'Editar color' : 'Nuevo color' }} — {{ $product->name }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="mx-auto max-w-2xl sm:px-6 lg:px-8">
            <a href="{{ route('admin.productos.colores', $product) }}" wire:navigate class="mb-4 inline-block text-sm text-gray-500 hover:text-gray-700">&larr; Volver a colores</a>

            <form wire:submit="save" class="grid grid-cols-1 gap-4 rounded-lg border border-gray-200 bg-white p-6 sm:grid-cols-2">
                <label class="flex flex-col gap-1 text-sm text-gray-700">
                    Nombre
                    <input type="text" wire:model="name" required placeholder="Ej. Rojo" class="rounded @error('name') border-red-500 ring-1 ring-red-500 @else border-gray-300 @enderror" />
                    @error('name') <span class="text-xs text-red-600">{{ $message }}</span> @enderror
                </label>
                <label class="flex flex-col gap-1 text-sm text-gray-700">
                    Color (hex, opcional)
                    <div class="flex items-center gap-2">
                        @if ($hex !== '' && preg_match('/^#[0-9a-fA-F]{6}$/', $hex))
                            <span class="h-8 w-8 shrink-0 rounded-full border border-gray-200" style="background-color: {{ $hex }}"></span>
                        @endif
                        <input type="text" wire:model.live="hex" placeholder="#DC2626" maxlength="7" class="w-full rounded @error('hex') border-red-500 ring-1 ring-red-500 @else border-gray-300 @enderror" />
                    </div>
                    <span class="text-xs text-gray-500">Se usa como respaldo del swatch si el color todavía no tiene fotos.</span>
                    @error('hex') <span class="text-xs text-red-600">{{ $message }}</span> @enderror
                </label>
                <label class="flex flex-col gap-1 text-sm text-gray-700">
                    Precio propio (opcional)
                    <input type="number" step="0.01" wire:model="price" placeholder="Usa el precio del producto si se deja vacío" class="rounded @error('price') border-red-500 ring-1 ring-red-500 @else border-gray-300 @enderror" />
                    @error('price') <span class="text-xs text-red-600">{{ $message }}</span> @enderror
                </label>
                <label class="flex flex-col gap-1 text-sm text-gray-700">
                    Orden
                    <input type="number" wire:model="sort_order" min="0" class="rounded border-gray-300" />
                    <span class="text-xs text-gray-500">Los colores se muestran de menor a mayor en la ficha del producto.</span>
                </label>

                <p class="col-span-full rounded-lg border border-gray-200 bg-gray-50 px-4 py-3 text-sm text-gray-600">
                    El stock ya no se captura aquí: depende de la combinación color/talla.
                    Se edita en <a href="{{ route('admin.productos.variantes', $product) }}" wire:navigate class="font-medium text-indigo-600 hover:underline">Inventario del producto</a>.
                </p>

                @if ($color)
                    <div class="col-span-full border-t border-gray-200 pt-4">
                        <h3 class="text-sm font-medium uppercase tracking-wide text-gray-500">Fotos de este color</h3>
                        <div class="mt-3 flex flex-wrap gap-3">
                            @foreach ($color->images as $image)
                                <div class="relative" wire:key="color-image-{{ $image->id }}">
                                    <img src="{{ $image->url }}" alt="" class="h-20 w-20 rounded object-cover" />
                                    <button
                                        type="button"
                                        x-on:click="confirmAction('¿Eliminar esta imagen?', () => $wire.deleteImage({{ $image->id }}))"
                                        class="absolute -right-2 -top-2 flex h-5 w-5 items-center justify-center rounded-full bg-red-600 text-xs text-white"
                                    >
                                        &times;
                                    </button>
                                </div>
                            @endforeach
                        </div>
                    </div>

                    <label class="col-span-full flex flex-col gap-1 text-sm text-gray-700">
                        Agregar fotos
                        <input type="file" wire:model="newImages" multiple accept="image/*" class="rounded text-sm @error('newImages.*') ring-1 ring-red-500 @else border-gray-300 @enderror" />
                        @error('newImages.*') <span class="text-xs text-red-600">{{ $message }}</span> @enderror
                    </label>
                @else
                    <p class="col-span-full text-sm text-gray-500">Guarda el color primero para poder subirle fotos.</p>
                @endif

                <button type="submit" class="col-span-full w-fit rounded-full bg-gray-900 px-6 py-2.5 text-sm font-semibold text-white hover:bg-gray-700">
                    {{ $color ? 'Guardar cambios' : 'Crear color' }}
                </button>
            </form>
        </div>
    </div>
</div>
