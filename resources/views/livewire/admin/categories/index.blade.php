<?php

use App\Concerns\Notifies;
use App\Models\ActivityLog;
use App\Models\Category;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;
use Livewire\WithPagination;

new #[Layout('layouts.app')] class extends Component
{
    use WithPagination, Notifies;

    public string $search = '';

    public function mount(): void
    {
        abort_unless(auth()->user()->can('categories.manage'), 403);
    }

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    /**
     * Soft delete (Category uses SoftDeletes) — the row stays in the
     * database with deleted_at set, excluded from every normal query by
     * Eloquent's default scope, and recoverable from
     * /admin/categorias/papelera.
     */
    public function delete(int $categoryId): void
    {
        $category = Category::findOrFail($categoryId);
        $name = $category->name;
        $before = ActivityLog::snapshot($category);
        $category->delete();

        ActivityLog::record(auth()->user(), 'category.deleted', "Eliminó la categoría \"{$name}\"", oldValues: $before);

        $this->notifySuccess("Se eliminó la categoría \"{$name}\". Puedes restaurarla desde la papelera.");
    }

    public function with(): array
    {
        return [
            'categories' => Category::query()
                ->when($this->search, fn ($q) => $q->where('name', 'like', "%{$this->search}%"))
                ->orderBy('name')
                ->paginate(10),
        ];
    }
}; ?>

<div>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">{{ __('Categorías') }}</h2>
    </x-slot>

    <div class="py-12">
        <div class="mx-auto max-w-7xl sm:px-6 lg:px-8">
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6">
                <div class="mb-4 flex items-center justify-between">
                    <input type="search" wire:model.live.debounce.300ms="search" placeholder="Buscar por nombre..." class="w-full max-w-xs rounded border-gray-300 text-sm" />
                    <div class="flex gap-3">
                        <a href="{{ route('admin.categorias.papelera') }}" wire:navigate class="rounded-full border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">
                            Papelera
                        </a>
                        <a href="{{ route('admin.categorias.destacadas') }}" wire:navigate class="rounded-full border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">
                            Categorías destacadas
                        </a>
                        <a href="{{ route('admin.categorias.nueva') }}" wire:navigate class="rounded-full bg-gray-900 px-4 py-2 text-sm font-medium text-white hover:bg-gray-700">
                            Nueva categoría
                        </a>
                    </div>
                </div>

                <table class="w-full text-left text-sm">
                    <thead>
                        <tr class="border-b text-gray-500">
                            <th class="py-2 pr-4"></th>
                            <th class="py-2 pr-4">Nombre</th>
                            <th class="py-2 pr-4">Slug</th>
                            <th class="py-2 pr-4">Destacada</th>
                            <th class="py-2">Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($categories as $category)
                            <tr class="border-b" wire:key="category-{{ $category->id }}">
                                <td class="py-2 pr-4">
                                    @if ($category->banner_image_url)
                                        <img src="{{ $category->banner_image_url }}" alt="" class="h-10 w-16 rounded object-cover" />
                                    @else
                                        <div class="h-10 w-16 rounded bg-gray-100"></div>
                                    @endif
                                </td>
                                <td class="py-2 pr-4">{{ $category->name }}</td>
                                <td class="py-2 pr-4 text-gray-500">{{ $category->slug }}</td>
                                <td class="py-2 pr-4 text-gray-500">{{ $category->featured_order !== null ? "#{$category->featured_order}" : '—' }}</td>
                                <td class="py-2">
                                    <a href="{{ route('admin.categorias.editar', $category) }}" wire:navigate class="text-indigo-600 hover:underline">Editar</a>
                                    <button
                                        type="button"
                                        x-on:click="confirmAction('¿Eliminar esta categoría? Los productos que la usan quedarán sin categoría hasta que la restaures desde la papelera.', () => $wire.delete({{ $category->id }}))"
                                        class="ml-3 text-red-600 hover:underline"
                                    >
                                        Eliminar
                                    </button>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="py-6 text-center text-gray-500">No hay categorías todavía.</td></tr>
                        @endforelse
                    </tbody>
                </table>

                <div class="mt-4">{{ $categories->links() }}</div>
            </div>
        </div>
    </div>
</div>
