<?php

use App\Concerns\Notifies;
use App\Models\ActivityLog;
use App\Models\Size;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

/**
 * The global size catalog. Gated by 'products.manage' rather than a
 * permission of its own: a size is catalog plumbing for products, not a
 * section someone would ever be granted on its own, and adding a
 * permission means touching the seeder, every role and every test that
 * enumerates them for no real access-control gain.
 *
 * One screen, no separate form page — a size is two fields, so a create
 * row on top plus inline editing beats a second route.
 */
new #[Layout('layouts.app')] class extends Component
{
    use Notifies;

    public string $newName = '';
    public string $newSortOrder = '0';

    /** @var array<int, array{name: string, sort_order: string}> keyed by size id */
    public array $editing = [];

    public function mount(): void
    {
        abort_unless(auth()->user()->can('products.manage'), 403);
    }

    public function create(): void
    {
        $validated = $this->validate([
            'newName' => ['required', 'string', 'max:50', Rule::unique('sizes', 'name')],
            'newSortOrder' => ['required', 'integer', 'min:0'],
        ], [
            'newName.unique' => 'Ya existe una talla con ese nombre.',
            'newName.required' => 'Escribe el nombre de la talla.',
        ]);

        $size = Size::create([
            'name' => $validated['newName'],
            'sort_order' => $validated['newSortOrder'],
        ]);

        ActivityLog::record(auth()->user(), 'size.created', "Creó la talla \"{$size->name}\"", $size, newValues: ActivityLog::snapshot($size));

        $this->reset('newName', 'newSortOrder');
        $this->newSortOrder = '0';
        $this->notifySuccess("Se creó la talla \"{$size->name}\".");
    }

    public function save(int $sizeId): void
    {
        $size = Size::findOrFail($sizeId);

        $validated = $this->validate([
            "editing.{$sizeId}.name" => ['required', 'string', 'max:50', Rule::unique('sizes', 'name')->ignore($sizeId)],
            "editing.{$sizeId}.sort_order" => ['required', 'integer', 'min:0'],
        ], [
            "editing.{$sizeId}.name.unique" => 'Ya existe una talla con ese nombre.',
            "editing.{$sizeId}.name.required" => 'La talla necesita un nombre.',
        ]);

        $before = ActivityLog::snapshot($size);
        $size->update([
            'name' => $validated['editing'][$sizeId]['name'],
            'sort_order' => $validated['editing'][$sizeId]['sort_order'],
        ]);

        ActivityLog::record(
            auth()->user(),
            'size.updated',
            "Actualizó la talla \"{$size->name}\"",
            $size,
            oldValues: $before,
            newValues: ActivityLog::snapshot($size),
        );

        $this->notifySuccess('Cambios guardados correctamente.');
    }

    /**
     * Deleting a size takes every product variant that used it with it —
     * product_variants has no ON DELETE CASCADE from sizes (SQL Server
     * refuses a second cascade path into that table, see the migration),
     * so it happens here explicitly. The confirm message says so, since
     * this silently removes inventory rows from products the admin isn't
     * looking at.
     */
    public function delete(int $sizeId): void
    {
        $size = Size::withCount('variants')->findOrFail($sizeId);
        $name = $size->name;
        $variantCount = $size->variants_count;

        $size->variants()->delete();
        $size->delete();

        ActivityLog::record(auth()->user(), 'size.deleted', "Eliminó la talla \"{$name}\" y {$variantCount} variante(s) que la usaban");

        $this->notifySuccess("Se eliminó la talla \"{$name}\".");
    }

    public function with(): array
    {
        $sizes = Size::withCount('variants')->orderBy('sort_order')->orderBy('name')->get();

        // Seed the inline edit state once per render for any row that
        // doesn't have it yet — keeps freshly created sizes editable
        // without a page reload.
        foreach ($sizes as $size) {
            $this->editing[$size->id] ??= [
                'name' => $size->name,
                'sort_order' => (string) $size->sort_order,
            ];
        }

        return ['sizes' => $sizes];
    }
}; ?>

<div>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">{{ __('Tallas') }}</h2>
    </x-slot>

    <div class="py-12">
        <div class="mx-auto max-w-3xl sm:px-6 lg:px-8">
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6">
                <p class="mb-4 text-sm text-gray-500">
                    Catálogo compartido por todos los productos. En cada producto eliges cuáles de
                    estas tallas le aplican y cuánto stock hay de cada combinación color/talla.
                </p>

                <form wire:submit="create" class="mb-6 flex flex-wrap items-end gap-3 rounded-lg border border-gray-200 bg-gray-50 p-4">
                    <label class="flex flex-col gap-1 text-sm text-gray-700">
                        Nueva talla
                        <input type="text" wire:model="newName" placeholder="Ej. M, 38" class="rounded @error('newName') border-red-500 ring-1 ring-red-500 @else border-gray-300 @enderror" />
                    </label>
                    <label class="flex flex-col gap-1 text-sm text-gray-700">
                        Orden
                        <input type="number" wire:model="newSortOrder" min="0" class="w-24 rounded border-gray-300" />
                    </label>
                    <button type="submit" class="rounded-full bg-gray-900 px-4 py-2 text-sm font-medium text-white hover:bg-gray-700">
                        Agregar
                    </button>
                    @error('newName') <span class="w-full text-xs text-red-600">{{ $message }}</span> @enderror
                </form>

                <table class="w-full text-left text-sm">
                    <thead>
                        <tr class="border-b text-gray-500">
                            <th class="py-2 pr-4">Nombre</th>
                            <th class="py-2 pr-4">Orden</th>
                            <th class="py-2 pr-4">En uso</th>
                            <th class="py-2">Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($sizes as $size)
                            <tr class="border-b" wire:key="size-{{ $size->id }}">
                                <td class="py-2 pr-4">
                                    <input type="text" wire:model="editing.{{ $size->id }}.name" class="w-32 rounded border-gray-300 text-sm" />
                                    @error("editing.{$size->id}.name") <span class="block text-xs text-red-600">{{ $message }}</span> @enderror
                                </td>
                                <td class="py-2 pr-4">
                                    <input type="number" wire:model="editing.{{ $size->id }}.sort_order" min="0" class="w-20 rounded border-gray-300 text-sm" />
                                </td>
                                <td class="py-2 pr-4 text-gray-500">
                                    {{ $size->variants_count }} {{ $size->variants_count === 1 ? 'variante' : 'variantes' }}
                                </td>
                                <td class="py-2">
                                    <button type="button" wire:click="save({{ $size->id }})" class="text-indigo-600 hover:underline">Guardar</button>
                                    <button
                                        type="button"
                                        x-on:click="confirmAction('¿Eliminar la talla \'{{ $size->name }}\'? Se borrarán también las {{ $size->variants_count }} variante(s) de producto que la usan, con su stock.', () => $wire.delete({{ $size->id }}))"
                                        class="ml-3 text-red-600 hover:underline"
                                    >
                                        Eliminar
                                    </button>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="4" class="py-6 text-center text-gray-500">Todavía no hay tallas en el catálogo.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
