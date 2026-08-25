<?php

use App\Concerns\Notifies;
use App\Models\ActivityLog;
use App\Models\Banner;
use App\Services\ResponsiveImage;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;
use Livewire\WithFileUploads;

new #[Layout('layouts.app')] class extends Component
{
    use WithFileUploads, Notifies;

    public ?Banner $banner = null;

    public string $title = '';
    public string $description = '';
    public string $url = '';
    public bool $is_active = false;
    public int $sort_order = 0;

    /** @var \Illuminate\Http\UploadedFile|null newly-picked image, replacing the stored one on save */
    public $desktopImage = null;

    /** @var \Illuminate\Http\UploadedFile|null */
    public $tabletImage = null;

    /** @var \Illuminate\Http\UploadedFile|null */
    public $mobileImage = null;

    public function mount(?Banner $banner = null): void
    {
        abort_unless(auth()->user()->can('banners.manage'), 403);

        if ($banner?->exists) {
            $this->banner = $banner;
            $this->title = $banner->title;
            $this->description = $banner->description ?? '';
            $this->url = $banner->url;
            $this->is_active = $banner->is_active;
            $this->sort_order = $banner->sort_order;
        }
    }

    public function save(): void
    {
        $validated = $this->validate([
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'url' => ['required', 'string', 'max:2048'],
            'is_active' => ['boolean'],
            'sort_order' => ['integer', 'min:0'],
            'desktopImage' => ['nullable', 'image', 'max:4096'],
            'tabletImage' => ['nullable', 'image', 'max:4096'],
            'mobileImage' => ['nullable', 'image', 'max:4096'],
        ]);
        unset($validated['desktopImage'], $validated['tabletImage'], $validated['mobileImage']);

        if ($this->banner) {
            $before = ActivityLog::snapshot($this->banner);

            $this->storeImages($validated);
            $this->banner->update($validated);

            ActivityLog::record(
                auth()->user(),
                'banner.updated',
                "Actualizó el banner \"{$this->banner->title}\"",
                $this->banner,
                oldValues: $before,
                newValues: ActivityLog::snapshot($this->banner),
            );
        } else {
            $newBanner = Banner::create($validated);
            $this->banner = $newBanner;

            $imageUpdates = [];
            $this->storeImages($imageUpdates);
            if ($imageUpdates) {
                $newBanner->update($imageUpdates);
            }

            ActivityLog::record(
                auth()->user(),
                'banner.created',
                "Creó el banner \"{$newBanner->title}\"",
                $newBanner,
                newValues: ActivityLog::snapshot($newBanner),
            );
        }

        $this->notifySuccess($this->banner->wasRecentlyCreated ? 'Banner creado correctamente.' : 'Cambios guardados correctamente.');
        $this->redirect(route('admin.banners'), navigate: true);
    }

    /**
     * Same idea as Category's storeImages(): stores whichever of the three
     * images were actually picked on the 'public' disk under
     * banners/{id}/ (plus WebP variants via ResponsiveImage), deleting the
     * previous file first so replacing an image doesn't orphan the old one
     * on disk. Mutates $updates by reference.
     */
    private function storeImages(array &$updates): void
    {
        if ($this->desktopImage) {
            ResponsiveImage::delete($this->banner?->desktop_image_path);
            $updates['desktop_image_path'] = ResponsiveImage::store($this->desktopImage, "banners/{$this->banner->id}");
        }

        if ($this->tabletImage) {
            ResponsiveImage::delete($this->banner?->tablet_image_path);
            $updates['tablet_image_path'] = ResponsiveImage::store($this->tabletImage, "banners/{$this->banner->id}");
        }

        if ($this->mobileImage) {
            ResponsiveImage::delete($this->banner?->mobile_image_path);
            $updates['mobile_image_path'] = ResponsiveImage::store($this->mobileImage, "banners/{$this->banner->id}");
        }
    }
}; ?>

<div>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ $banner ? 'Editar banner' : 'Nuevo banner' }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="mx-auto max-w-2xl sm:px-6 lg:px-8">
            <a href="{{ route('admin.banners') }}" wire:navigate class="mb-4 inline-block text-sm text-gray-500 hover:text-gray-700">&larr; Volver a banners</a>

            <form wire:submit="save" class="grid grid-cols-1 gap-4 rounded-lg border border-gray-200 bg-white p-6 sm:grid-cols-2">
                <label class="flex flex-col gap-1 text-sm text-gray-700 sm:col-span-2">
                    Título
                    <input type="text" wire:model="title" required class="rounded @error('title') border-red-500 ring-1 ring-red-500 @else border-gray-300 @enderror" />
                    @error('title') <span class="text-xs text-red-600">{{ $message }}</span> @enderror
                </label>
                <label class="flex flex-col gap-1 text-sm text-gray-700 sm:col-span-2">
                    Descripción
                    <textarea wire:model="description" rows="2" class="rounded border-gray-300"></textarea>
                </label>
                <label class="flex flex-col gap-1 text-sm text-gray-700 sm:col-span-2">
                    URL destino
                    <input type="text" wire:model="url" placeholder="/tienda o https://..." required class="rounded @error('url') border-red-500 ring-1 ring-red-500 @else border-gray-300 @enderror" />
                    <span class="text-xs text-gray-500">Puede ser una ruta interna (ej. /tienda, /categoria/electronica) o una URL completa.</span>
                    @error('url') <span class="text-xs text-red-600">{{ $message }}</span> @enderror
                </label>

                <label class="flex flex-col gap-1 text-sm text-gray-700">
                    Imagen escritorio
                    @if ($banner?->desktop_image_url && ! $desktopImage)
                        <img src="{{ $banner->desktop_image_url }}" alt="" class="mb-1 h-20 w-full rounded object-cover" />
                    @endif
                    @if ($desktopImage)
                        <img src="{{ $desktopImage->temporaryUrl() }}" alt="" class="mb-1 h-20 w-full rounded object-cover" />
                    @endif
                    <input type="file" wire:model="desktopImage" accept="image/*" class="text-sm @error('desktopImage') rounded ring-1 ring-red-500 @enderror" />
                    <span class="text-xs text-gray-500">Recomendado: ancha (ej. 1920×600px).</span>
                    @error('desktopImage') <span class="text-xs text-red-600">{{ $message }}</span> @enderror
                </label>
                <label class="flex flex-col gap-1 text-sm text-gray-700">
                    Imagen tablet
                    @if ($banner?->tablet_image_url && ! $tabletImage)
                        <img src="{{ $banner->tablet_image_url }}" alt="" class="mb-1 h-20 w-full rounded object-cover" />
                    @endif
                    @if ($tabletImage)
                        <img src="{{ $tabletImage->temporaryUrl() }}" alt="" class="mb-1 h-20 w-full rounded object-cover" />
                    @endif
                    <input type="file" wire:model="tabletImage" accept="image/*" class="text-sm @error('tabletImage') rounded ring-1 ring-red-500 @enderror" />
                    <span class="text-xs text-gray-500">Recomendado: cuadrada (ej. 1200×900px).</span>
                    @error('tabletImage') <span class="text-xs text-red-600">{{ $message }}</span> @enderror
                </label>
                <label class="flex flex-col gap-1 text-sm text-gray-700">
                    Imagen móvil
                    @if ($banner?->mobile_image_url && ! $mobileImage)
                        <img src="{{ $banner->mobile_image_url }}" alt="" class="mb-1 h-20 w-full rounded object-cover" />
                    @endif
                    @if ($mobileImage)
                        <img src="{{ $mobileImage->temporaryUrl() }}" alt="" class="mb-1 h-20 w-full rounded object-cover" />
                    @endif
                    <input type="file" wire:model="mobileImage" accept="image/*" class="text-sm @error('mobileImage') rounded ring-1 ring-red-500 @enderror" />
                    <span class="text-xs text-gray-500">Recomendado: vertical (ej. 800×1000px).</span>
                    @error('mobileImage') <span class="text-xs text-red-600">{{ $message }}</span> @enderror
                </label>

                <label class="flex flex-col gap-1 text-sm text-gray-700">
                    Orden
                    <input type="number" wire:model="sort_order" min="0" class="rounded border-gray-300" />
                    <span class="text-xs text-gray-500">Los banners activos se muestran de menor a mayor.</span>
                </label>
                <label class="flex items-center gap-2 self-end pb-2 text-sm text-gray-700">
                    <input type="checkbox" wire:model="is_active" class="rounded border-gray-300" />
                    Activo (se muestra en el home)
                </label>

                <button type="submit" class="col-span-full w-fit rounded-full bg-gray-900 px-6 py-2.5 text-sm font-semibold text-white hover:bg-gray-700">
                    {{ $banner ? 'Guardar cambios' : 'Crear banner' }}
                </button>
            </form>
        </div>
    </div>
</div>
