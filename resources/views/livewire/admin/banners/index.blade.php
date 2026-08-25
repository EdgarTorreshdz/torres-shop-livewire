<?php

use App\Concerns\Notifies;
use App\Models\ActivityLog;
use App\Models\Banner;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;
use Livewire\WithPagination;

new #[Layout('layouts.app')] class extends Component
{
    use WithPagination, Notifies;

    public string $search = '';

    public function mount(): void
    {
        abort_unless(auth()->user()->can('banners.manage'), 403);
    }

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    /**
     * One-click on/off from the list — no need to open the full form just
     * to pause a promo. Logged like any other write so it shows up in the
     * bitácora with what it flipped to.
     */
    public function toggleActive(int $bannerId): void
    {
        $banner = Banner::findOrFail($bannerId);
        $before = ActivityLog::snapshot($banner);
        $banner->update(['is_active' => ! $banner->is_active]);

        ActivityLog::record(
            auth()->user(),
            'banner.updated',
            $banner->is_active ? "Activó el banner \"{$banner->title}\"" : "Desactivó el banner \"{$banner->title}\"",
            $banner,
            oldValues: $before,
            newValues: ActivityLog::snapshot($banner),
        );
    }

    public function delete(int $bannerId): void
    {
        $banner = Banner::findOrFail($bannerId);
        $title = $banner->title;
        $before = ActivityLog::snapshot($banner);

        \App\Services\ResponsiveImage::delete($banner->desktop_image_path);
        \App\Services\ResponsiveImage::delete($banner->tablet_image_path);
        \App\Services\ResponsiveImage::delete($banner->mobile_image_path);
        $banner->delete();

        ActivityLog::record(auth()->user(), 'banner.deleted', "Eliminó el banner \"{$title}\"", oldValues: $before);

        $this->notifySuccess("Se eliminó el banner \"{$title}\".");
    }

    public function with(): array
    {
        return [
            'banners' => Banner::query()
                ->when($this->search, fn ($q) => $q->where('title', 'like', "%{$this->search}%"))
                ->orderBy('sort_order')
                ->orderBy('id')
                ->paginate(10),
        ];
    }
}; ?>

<div>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">{{ __('Banners') }}</h2>
    </x-slot>

    <div class="py-12">
        <div class="mx-auto max-w-7xl sm:px-6 lg:px-8">
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6">
                <div class="mb-4 flex items-center justify-between">
                    <input type="search" wire:model.live.debounce.300ms="search" placeholder="Buscar por título..." class="w-full max-w-xs rounded border-gray-300 text-sm" />
                    <a href="{{ route('admin.banners.nuevo') }}" wire:navigate class="rounded-full bg-gray-900 px-4 py-2 text-sm font-medium text-white hover:bg-gray-700">
                        Nuevo banner
                    </a>
                </div>

                <p class="mb-4 max-w-2xl text-sm text-gray-500">
                    Los banners <strong>activos</strong> aparecen en el carrusel del home, ordenados por su número de
                    orden. Cada banner puede tener una imagen distinta para escritorio, tablet y móvil.
                </p>

                <table class="w-full text-left text-sm">
                    <thead>
                        <tr class="border-b text-gray-500">
                            <th class="py-2 pr-4"></th>
                            <th class="py-2 pr-4">Título</th>
                            <th class="py-2 pr-4">URL destino</th>
                            <th class="py-2 pr-4">Orden</th>
                            <th class="py-2 pr-4">Activo</th>
                            <th class="py-2">Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($banners as $banner)
                            <tr class="border-b" wire:key="banner-{{ $banner->id }}">
                                <td class="py-2 pr-4">
                                    @if ($banner->desktop_image_url)
                                        <img src="{{ $banner->desktop_image_url }}" alt="" class="h-10 w-16 rounded object-cover" />
                                    @else
                                        <div class="h-10 w-16 rounded bg-gray-100"></div>
                                    @endif
                                </td>
                                <td class="py-2 pr-4 font-medium text-gray-900">{{ $banner->title }}</td>
                                <td class="py-2 pr-4 max-w-xs truncate text-gray-500">{{ $banner->url }}</td>
                                <td class="py-2 pr-4 text-gray-500">{{ $banner->sort_order }}</td>
                                <td class="py-2 pr-4">
                                    <button
                                        type="button"
                                        wire:click="toggleActive({{ $banner->id }})"
                                        class="rounded-full px-3 py-1 text-xs font-semibold {{ $banner->is_active ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-500' }}"
                                    >
                                        {{ $banner->is_active ? 'Activo' : 'Inactivo' }}
                                    </button>
                                </td>
                                <td class="py-2">
                                    <a href="{{ route('admin.banners.editar', $banner) }}" wire:navigate class="text-indigo-600 hover:underline">Editar</a>
                                    <button
                                        type="button"
                                        x-on:click="confirmAction('¿Eliminar el banner \'{{ $banner->title }}\'? Esta acción no se puede deshacer.', () => $wire.delete({{ $banner->id }}))"
                                        class="ml-3 text-red-600 hover:underline"
                                    >
                                        Eliminar
                                    </button>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="py-6 text-center text-gray-500">No hay banners todavía.</td></tr>
                        @endforelse
                    </tbody>
                </table>

                <div class="mt-4">{{ $banners->links() }}</div>
            </div>
        </div>
    </div>
</div>
