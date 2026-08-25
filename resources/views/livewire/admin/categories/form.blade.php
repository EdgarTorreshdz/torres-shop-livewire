<?php

use App\Models\ActivityLog;
use App\Models\Category;
use Illuminate\Support\Str;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('layouts.app')] class extends Component
{
    public ?Category $category = null;

    public string $name = '';
    public string $description = '';
    public string $banner_image_url = '';
    public string $mobile_image_url = '';
    public string $meta_title = '';
    public string $meta_description = '';

    public function mount(?Category $category = null): void
    {
        abort_unless(auth()->user()->can('categories.manage'), 403);

        if ($category?->exists) {
            $this->category = $category;
            $this->name = $category->name;
            $this->description = $category->description ?? '';
            $this->banner_image_url = $category->banner_image_url ?? '';
            $this->mobile_image_url = $category->mobile_image_url ?? '';
            $this->meta_title = $category->meta_title ?? '';
            $this->meta_description = $category->meta_description ?? '';
        }
    }

    public function save(): void
    {
        $validated = $this->validate([
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'banner_image_url' => ['nullable', 'string', 'max:2048'],
            'mobile_image_url' => ['nullable', 'string', 'max:2048'],
            'meta_title' => ['nullable', 'string', 'max:255'],
            'meta_description' => ['nullable', 'string', 'max:320'],
        ]);

        if ($this->category) {
            $before = ActivityLog::snapshot($this->category);

            if ($validated['name'] !== $this->category->name) {
                $validated['slug'] = $this->uniqueSlug($validated['name'], $this->category->id);
            }

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

            ActivityLog::record(
                auth()->user(),
                'category.created',
                "Creó la categoría \"{$newCategory->name}\"",
                $newCategory,
                newValues: ActivityLog::snapshot($newCategory),
            );
        }

        $this->redirect(route('admin.categorias'), navigate: true);
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
                    <input type="text" wire:model="name" required class="rounded border-gray-300" />
                    @error('name') <span class="text-xs text-red-600">{{ $message }}</span> @enderror
                </label>
                <label class="flex flex-col gap-1 text-sm text-gray-700">
                    Descripción
                    <input type="text" wire:model="description" class="rounded border-gray-300" />
                </label>
                <label class="flex flex-col gap-1 text-sm text-gray-700">
                    Imagen banner (escritorio)
                    <input type="url" wire:model="banner_image_url" placeholder="https://..." class="rounded border-gray-300" />
                </label>
                <label class="flex flex-col gap-1 text-sm text-gray-700">
                    Imagen banner (móvil)
                    <input type="url" wire:model="mobile_image_url" placeholder="https://..." class="rounded border-gray-300" />
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
