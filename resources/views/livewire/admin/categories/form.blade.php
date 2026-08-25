<?php

use App\Concerns\Notifies;
use App\Models\ActivityLog;
use App\Models\Category;
use App\Services\ResponsiveImage;
use Illuminate\Support\Str;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;
use Livewire\WithFileUploads;

new #[Layout('layouts.app')] class extends Component
{
    use WithFileUploads, Notifies;

    public ?Category $category = null;

    public string $name = '';
    public string $description = '';
    public string $meta_title = '';
    public string $meta_description = '';

    /** @var \Illuminate\Http\UploadedFile|null newly-picked banner, replacing the stored one on save */
    public $bannerImage = null;

    /** @var \Illuminate\Http\UploadedFile|null */
    public $mobileImage = null;

    public function mount(?Category $category = null): void
    {
        abort_unless(auth()->user()->can('categories.manage'), 403);

        if ($category?->exists) {
            $this->category = $category;
            $this->name = $category->name;
            $this->description = $category->description ?? '';
            $this->meta_title = $category->meta_title ?? '';
            $this->meta_description = $category->meta_description ?? '';
        }
    }

    public function save(): void
    {
        $validated = $this->validate([
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'meta_title' => ['nullable', 'string', 'max:255'],
            'meta_description' => ['nullable', 'string', 'max:320'],
            'bannerImage' => ['nullable', 'image', 'max:4096'],
            'mobileImage' => ['nullable', 'image', 'max:4096'],
        ]);
        unset($validated['bannerImage'], $validated['mobileImage']);

        if ($this->category) {
            $before = ActivityLog::snapshot($this->category);

            if ($validated['name'] !== $this->category->name) {
                $validated['slug'] = $this->uniqueSlug($validated['name'], $this->category->id);
            }

            $this->storeImages($validated);
            $this->category->update($validated);

            ActivityLog::record(
                auth()->user(),
                'category.updated',
                "Actualizó la categoría \"{$this->category->name}\"",
                $this->category,
                oldValues: $before,
                newValues: ActivityLog::snapshot($this->category),
            );
        } else {
            $validated['slug'] = $this->uniqueSlug($validated['name']);
            $newCategory = Category::create($validated);
            $this->category = $newCategory;

            $imageUpdates = [];
            $this->storeImages($imageUpdates);
            if ($imageUpdates) {
                $newCategory->update($imageUpdates);
            }

            ActivityLog::record(
                auth()->user(),
                'category.created',
                "Creó la categoría \"{$newCategory->name}\"",
                $newCategory,
                newValues: ActivityLog::snapshot($newCategory),
            );
        }

        $this->notifySuccess($this->category->wasRecentlyCreated ? 'Categoría creada correctamente.' : 'Cambios guardados correctamente.');
        $this->redirect(route('admin.categorias'), navigate: true);
    }

    /**
     * Stores whichever of bannerImage/mobileImage were actually picked on
     * the 'public' disk under categories/{id}/ (plus a WebP copy at each
     * responsive breakpoint, via ResponsiveImage — see its docblock),
     * deleting the previous file (and its variants) first so replacing a
     * banner doesn't leave the old ones orphaned on disk. Mutates
     * $updates by reference so the caller can fold the new paths into the
     * same update()/create() call as the rest of the form.
     */
    private function storeImages(array &$updates): void
    {
        if ($this->bannerImage) {
            ResponsiveImage::delete($this->category?->banner_image_path);
            $updates['banner_image_path'] = ResponsiveImage::store($this->bannerImage, "categories/{$this->category->id}");
        }

        if ($this->mobileImage) {
            ResponsiveImage::delete($this->category?->mobile_image_path);
            $updates['mobile_image_path'] = ResponsiveImage::store($this->mobileImage, "categories/{$this->category->id}");
        }
    }

    private function uniqueSlug(string $name, ?int $ignoreId = null): string
    {
        $base = Str::slug($name);
        $slug = $base;
        $i = 1;

        while (Category::where('slug', $slug)->when($ignoreId, fn ($q) => $q->where('id', '!=', $ignoreId))->exists()) {
            $slug = "{$base}-{$i}";
            $i++;
        }

        return $slug;
    }
}; ?>

<div>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ $category ? 'Editar categoría' : 'Nueva categoría' }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="mx-auto max-w-2xl sm:px-6 lg:px-8">
            <a href="{{ route('admin.categorias') }}" wire:navigate class="mb-4 inline-block text-sm text-gray-500 hover:text-gray-700">&larr; Volver a categorías</a>

            <form wire:submit="save" class="grid grid-cols-1 gap-4 rounded-lg border border-gray-200 bg-white p-6 sm:grid-cols-2">
                <label class="flex flex-col gap-1 text-sm text-gray-700">
                    Nombre
                    <input type="text" wire:model="name" required class="rounded @error('name') border-red-500 ring-1 ring-red-500 @else border-gray-300 @enderror" />
                    @error('name') <span class="text-xs text-red-600">{{ $message }}</span> @enderror
                </label>
                <label class="flex flex-col gap-1 text-sm text-gray-700">
                    Descripción
                    <input type="text" wire:model="description" class="rounded border-gray-300" />
                </label>

                <label class="flex flex-col gap-1 text-sm text-gray-700">
                    Imagen banner (escritorio)
                    @if ($category?->banner_image_url && ! $bannerImage)
                        <img src="{{ $category->banner_image_url }}" alt="" class="mb-1 h-20 w-full rounded object-cover" />
                    @endif
                    @if ($bannerImage)
                        <img src="{{ $bannerImage->temporaryUrl() }}" alt="" class="mb-1 h-20 w-full rounded object-cover" />
                    @endif
                    <input type="file" wire:model="bannerImage" accept="image/*" class="text-sm @error('bannerImage') rounded ring-1 ring-red-500 @enderror" />
                    <span class="text-xs text-gray-500">Recomendado: ancha (ej. 1600×500px).</span>
                    @error('bannerImage') <span class="text-xs text-red-600">{{ $message }}</span> @enderror
                </label>
                <label class="flex flex-col gap-1 text-sm text-gray-700">
                    Imagen banner (móvil)
                    @if ($category?->mobile_image_url && ! $mobileImage)
                        <img src="{{ $category->mobile_image_url }}" alt="" class="mb-1 h-20 w-full rounded object-cover" />
                    @endif
                    @if ($mobileImage)
                        <img src="{{ $mobileImage->temporaryUrl() }}" alt="" class="mb-1 h-20 w-full rounded object-cover" />
                    @endif
                    <input type="file" wire:model="mobileImage" accept="image/*" class="text-sm @error('mobileImage') rounded ring-1 ring-red-500 @enderror" />
                    <span class="text-xs text-gray-500">Recomendado: vertical/cuadrada (ej. 800×800px).</span>
                    @error('mobileImage') <span class="text-xs text-red-600">{{ $message }}</span> @enderror
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

                <button type="submit" class="col-span-full w-fit rounded-full bg-gray-900 px-6 py-2.5 text-sm font-semibold text-white hover:bg-gray-700">
                    {{ $category ? 'Guardar cambios' : 'Crear categoría' }}
                </button>
            </form>
        </div>
    </div>
</div>
