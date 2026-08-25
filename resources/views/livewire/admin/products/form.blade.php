<?php

use App\Concerns\Notifies;
use App\Models\ActivityLog;
use App\Models\Category;
use App\Models\Product;
use App\Services\ResponsiveImage;
use Illuminate\Support\Str;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;
use Livewire\WithFileUploads;

new #[Layout('layouts.app')] class extends Component
{
    use WithFileUploads, Notifies;

    public ?Product $product = null;

    public ?int $category_id = null;
    public string $name = '';
    public string $description = '';
    public string $meta_title = '';
    public string $meta_description = '';
    public string $price = '';
    public string $stock = '';
    public bool $is_active = true;

    /** @var \Illuminate\Http\UploadedFile[] */
    public array $newImages = [];

    public function mount(?Product $product = null): void
    {
        abort_unless(auth()->user()->can('products.manage'), 403);

        if ($product?->exists) {
            $this->product = $product->load('images');
            $this->category_id = $product->category_id;
            $this->name = $product->name;
            $this->description = $product->description ?? '';
            $this->meta_title = $product->meta_title ?? '';
            $this->meta_description = $product->meta_description ?? '';
            $this->price = (string) $product->price;
            $this->stock = (string) $product->stock;
            $this->is_active = $product->is_active;
        }
    }

    public function save(): void
    {
        $validated = $this->validate([
            'category_id' => ['nullable', 'integer', 'exists:categories,id'],
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'meta_title' => ['nullable', 'string', 'max:255'],
            'meta_description' => ['nullable', 'string', 'max:320'],
            'price' => ['required', 'numeric', 'min:0'],
            'stock' => ['required', 'integer', 'min:0'],
            'is_active' => ['boolean'],
        ]);

        $isNew = ! $this->product;

        if ($this->product) {
            $before = ActivityLog::snapshot($this->product);

            if ($validated['name'] !== $this->product->name) {
                $validated['slug'] = $this->uniqueSlug($validated['name'], $this->product->id);
            }

            $this->product->update($validated);

            ActivityLog::record(
                auth()->user(),
                'product.updated',
                "Actualizó el producto \"{$this->product->name}\"",
                $this->product,
                oldValues: $before,
                newValues: ActivityLog::snapshot($this->product),
            );
        } else {
            $validated['slug'] = $this->uniqueSlug($validated['name']);
            $this->product = Product::create($validated);

            ActivityLog::record(
                auth()->user(),
                'product.created',
                "Creó el producto \"{$this->product->name}\"",
                $this->product,
                newValues: ActivityLog::snapshot($this->product),
            );
        }

        if (! empty($this->newImages)) {
            $this->uploadImages();
        }

        $this->notifySuccess($isNew ? 'Producto creado correctamente.' : 'Cambios guardados correctamente.');
        $this->redirect(route('admin.productos'), navigate: true);
    }

    /**
     * Stored on the 'public' disk under products/{id}/ — local disk today
     * (storage/app/public, served via the public/storage symlink), which
     * is fine for a small/medium project on a single server. Swapping to
     * S3-compatible storage later is config-only (config/filesystems.php),
     * nothing here would need to change. ResponsiveImage::store() also
     * generates a WebP copy at each breakpoint width next to the original
     * — see the class docblock for why that's not a separate DB column.
     */
    private function uploadImages(): void
    {
        $nextOrder = (int) $this->product->images()->max('sort_order') + 1;

        foreach ($this->newImages as $i => $file) {
            $path = ResponsiveImage::store($file, "products/{$this->product->id}");

            $this->product->images()->create([
                'path' => $path,
                'sort_order' => $nextOrder + $i,
            ]);
        }

        ActivityLog::record(
            auth()->user(),
            'product.images_uploaded',
            'Subió '.count($this->newImages)." imagen(es) a \"{$this->product->name}\"",
            $this->product,
            newValues: ['images' => count($this->newImages)],
        );

        $this->newImages = [];
    }

    public function deleteImage(int $imageId): void
    {
        $image = $this->product->images()->findOrFail($imageId);
        $path = $image->path;
        ResponsiveImage::delete($path);
        $image->delete();

        ActivityLog::record(
            auth()->user(),
            'product.image_deleted',
            "Eliminó una imagen de \"{$this->product->name}\"",
            $this->product,
            oldValues: ['path' => $path],
        );

        $this->product->refresh();
        $this->notifySuccess('Imagen eliminada.');
    }

    private function uniqueSlug(string $name, ?int $ignoreId = null): string
    {
        $base = Str::slug($name);
        $slug = $base;
        $i = 1;

        while (Product::where('slug', $slug)->when($ignoreId, fn ($q) => $q->where('id', '!=', $ignoreId))->exists()) {
            $slug = "{$base}-{$i}";
            $i++;
        }

        return $slug;
    }

    public function with(): array
    {
        return ['categories' => Category::orderBy('name')->get()];
    }
}; ?>

<div>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ $product ? 'Editar producto' : 'Nuevo producto' }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="mx-auto max-w-3xl sm:px-6 lg:px-8">
            <a href="{{ route('admin.productos') }}" wire:navigate class="mb-4 inline-block text-sm text-gray-500 hover:text-gray-700">&larr; Volver a productos</a>

            <form wire:submit="save" class="grid grid-cols-1 gap-4 rounded-lg border border-gray-200 bg-white p-6 sm:grid-cols-2">
                <label class="flex flex-col gap-1 text-sm text-gray-700">
                    Categoría
                    <select wire:model="category_id" class="rounded border-gray-300">
                        <option value="">Sin categoría</option>
                        @foreach ($categories as $cat)
                            <option value="{{ $cat->id }}">{{ $cat->name }}</option>
                        @endforeach
                    </select>
                </label>
                <label class="flex flex-col gap-1 text-sm text-gray-700">
                    Nombre
                    <input type="text" wire:model="name" required class="rounded @error('name') border-red-500 ring-1 ring-red-500 @else border-gray-300 @enderror" />
                    @error('name') <span class="text-xs text-red-600">{{ $message }}</span> @enderror
                </label>
                <label class="col-span-full flex flex-col gap-1 text-sm text-gray-700">
                    Descripción
                    <textarea wire:model="description" rows="3" class="rounded border-gray-300"></textarea>
                </label>
                <label class="flex flex-col gap-1 text-sm text-gray-700">
                    Precio
                    <input type="number" step="0.01" wire:model="price" required class="rounded @error('price') border-red-500 ring-1 ring-red-500 @else border-gray-300 @enderror" />
                    @error('price') <span class="text-xs text-red-600">{{ $message }}</span> @enderror
                </label>
                <label class="flex flex-col gap-1 text-sm text-gray-700">
                    Stock
                    <input type="number" wire:model="stock" required class="rounded @error('stock') border-red-500 ring-1 ring-red-500 @else border-gray-300 @enderror" />
                    @error('stock') <span class="text-xs text-red-600">{{ $message }}</span> @enderror
                </label>
                <label class="flex items-center gap-2 text-sm text-gray-700">
                    <input type="checkbox" wire:model="is_active" class="rounded border-gray-300" />
                    Activo (visible en la tienda)
                </label>

                <div class="col-span-full border-t border-gray-200 pt-4">
                    <h3 class="text-sm font-medium uppercase tracking-wide text-gray-500">SEO</h3>
                </div>
                <label class="flex flex-col gap-1 text-sm text-gray-700">
                    Meta title
                    <input type="text" wire:model="meta_title" placeholder="Se usa el nombre si se deja vacío" class="rounded border-gray-300" />
                </label>
                <label class="flex flex-col gap-1 text-sm text-gray-700">
                    Meta description
                    <input type="text" wire:model="meta_description" placeholder="Se usa la descripción si se deja vacío" class="rounded border-gray-300" />
                </label>

                @if ($product)
                    <div class="col-span-full border-t border-gray-200 pt-4">
                        <h3 class="text-sm font-medium uppercase tracking-wide text-gray-500">Imágenes</h3>
                        <div class="mt-3 flex flex-wrap gap-3">
                            @foreach ($product->images as $image)
                                <div class="relative" wire:key="image-{{ $image->id }}">
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
                        Agregar imágenes
                        <input type="file" wire:model="newImages" multiple accept="image/*" class="rounded text-sm @error('newImages.*') ring-1 ring-red-500 @else border-gray-300 @enderror" />
                        @error('newImages.*') <span class="text-xs text-red-600">{{ $message }}</span> @enderror
                    </label>
                @else
                    <p class="col-span-full text-sm text-gray-500">Guarda el producto primero para poder subir imágenes.</p>
                @endif

                <button type="submit" class="col-span-full w-fit rounded-full bg-gray-900 px-6 py-2.5 text-sm font-semibold text-white hover:bg-gray-700">
                    {{ $product ? 'Guardar cambios' : 'Crear producto' }}
                </button>
            </form>
        </div>
    </div>
</div>
