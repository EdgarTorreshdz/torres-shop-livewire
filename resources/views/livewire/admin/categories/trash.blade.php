<?php

use App\Concerns\Notifies;
use App\Models\ActivityLog;
use App\Models\Category;
use App\Services\ResponsiveImage;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;
use Livewire\WithPagination;

new #[Layout('layouts.app')] class extends Component
{
    use WithPagination, Notifies;

    public function mount(): void
    {
        abort_unless(auth()->user()->can('categories.manage'), 403);
    }

    public function restore(int $categoryId): void
    {
        $category = Category::onlyTrashed()->findOrFail($categoryId);
        $category->restore();

        ActivityLog::record(auth()->user(), 'category.restored', "Restauró la categoría \"{$category->name}\"", $category);

        $this->notifySuccess("Se restauró la categoría \"{$category->name}\".");
    }

    /**
     * Unlike the soft delete() on the main list, this is permanent — no
     * activity-log old_values snapshot to recover from afterward, just a
     * record that it happened and with what name. Also cleans up the
     * banner/mobile image files (and their responsive variants) from
     * disk first — forceDelete() only removes the database row, so
     * skipping this would leave them orphaned forever.
     */
    public function forceDelete(int $categoryId): void
    {
        $category = Category::onlyTrashed()->findOrFail($categoryId);
        $name = $category->name;
        ResponsiveImage::delete($category->banner_image_path);
        ResponsiveImage::delete($category->mobile_image_path);
        $category->forceDelete();

        ActivityLog::record(auth()->user(), 'category.force_deleted', "Eliminó permanentemente la categoría \"{$name}\"");

        $this->notifySuccess("Se eliminó permanentemente la categoría \"{$name}\".");
    }

    public function with(): array
    {
        return ['categories' => Category::onlyTrashed()->orderBy('deleted_at', 'desc')->paginate(10)];
    }
}; ?>

<div>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">{{ __('Papelera de categorías') }}</h2>
    </x-slot>

    <div class="py-12">
        <div class="mx-auto max-w-5xl sm:px-6 lg:px-8">
            <a href="{{ route('admin.categorias') }}" wire:navigate class="mb-4 inline-block text-sm text-gray-500 hover:text-gray-700">&larr; Volver a categorías</a>

            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6">
                <table class="w-full text-left text-sm">
                    <thead>
                        <tr class="border-b text-gray-500">
                            <th class="py-2 pr-4">Nombre</th>
                            <th class="py-2 pr-4">Eliminada</th>
                            <th class="py-2">Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($categories as $category)
                            <tr class="border-b" wire:key="trashed-category-{{ $category->id }}">
                                <td class="py-2 pr-4">{{ $category->name }}</td>
                                <td class="py-2 pr-4 text-gray-500">{{ $category->deleted_at->format('d/m/Y H:i') }}</td>
                                <td class="py-2">
                                    <button type="button" wire:click="restore({{ $category->id }})" class="text-indigo-600 hover:underline">
                                        Restaurar
                                    </button>
                                    <button
                                        type="button"
                                        x-on:click="confirmAction('¿Eliminar permanentemente esta categoría? Esta acción no se puede deshacer.', () => $wire.forceDelete({{ $category->id }}))"
                                        class="ml-3 text-red-600 hover:underline"
                                    >
                                        Eliminar permanentemente
                                    </button>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="3" class="py-6 text-center text-gray-500">La papelera está vacía.</td></tr>
                        @endforelse
                    </tbody>
                </table>

                <div class="mt-4">{{ $categories->links() }}</div>
            </div>
        </div>
    </div>
</div>
