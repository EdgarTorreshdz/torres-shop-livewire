<?php

use App\Models\ActivityLog;
use App\Models\Category;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('layouts.app')] class extends Component
{
    /** @var array<int, string> category_id => order (string so an empty input is easy to detect) */
    public array $orders = [];

    public function mount(): void
    {
        abort_unless(auth()->user()->can('categories.manage'), 403);

        foreach (Category::orderBy('name')->get() as $category) {
            $this->orders[$category->id] = $category->featured_order !== null ? (string) $category->featured_order : '';
        }
    }

    /**
     * One save for every category at once — simpler than a toggle button
     * per row, and the "order" number doubles as the on/off flag (blank =
     * not shown in the customer nav menu).
     */
    public function save(): void
    {
        $before = Category::whereNotNull('featured_order')
            ->orderBy('featured_order')
            ->pluck('name', 'id')
            ->all();

        foreach ($this->orders as $categoryId => $order) {
            Category::whereKey($categoryId)->update([
                'featured_order' => $order === '' ? null : (int) $order,
            ]);
        }

        $after = Category::whereNotNull('featured_order')
            ->orderBy('featured_order')
            ->pluck('name', 'id')
            ->all();

        ActivityLog::record(
            auth()->user(),
            'categories.featured_updated',
            'Actualizó las categorías destacadas del menú',
            oldValues: ['featured' => array_values($before)],
            newValues: ['featured' => array_values($after)],
        );

        $this->redirect(route('admin.categorias'), navigate: true);
    }

    public function with(): array
    {
        return ['categories' => Category::orderBy('name')->get()];
    }
}; ?>

<div>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">{{ __('Categorías destacadas') }}</h2>
    </x-slot>

    <div class="py-12">
        <div class="mx-auto max-w-2xl sm:px-6 lg:px-8">
            <a href="{{ route('admin.categorias') }}" wire:navigate class="mb-4 inline-block text-sm text-gray-500 hover:text-gray-700">&larr; Volver a categorías</a>

            <p class="mb-4 text-sm text-gray-500">
                Elige qué categorías aparecen en el menú de navegación de la tienda y en qué orden.
                Deja el número vacío para que una categoría no aparezca ahí.
            </p>

            <form wire:submit="save" class="rounded-lg border border-gray-200 bg-white p-6">
                <table class="w-full text-left text-sm">
                    <thead>
                        <tr class="border-b text-gray-500">
                            <th class="py-2 pr-4">Categoría</th>
                            <th class="py-2">Orden en el menú</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($categories as $category)
                            <tr class="border-b" wire:key="featured-category-{{ $category->id }}">
                                <td class="py-2 pr-4">{{ $category->name }}</td>
                                <td class="py-2">
                                    <input
                                        type="number"
                                        min="1"
                                        wire:model="orders.{{ $category->id }}"
                                        placeholder="—"
                                        class="w-24 rounded border-gray-300 text-sm"
                                    />
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>

                <button type="submit" class="mt-4 w-fit rounded-full bg-gray-900 px-6 py-2.5 text-sm font-semibold text-white hover:bg-gray-700">
                    Guardar
                </button>
            </form>
        </div>
    </div>
</div>
