<?php

use App\Models\ActivityLog;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;
use Livewire\WithPagination;

new #[Layout('layouts.app')] class extends Component
{
    use WithPagination;

    public string $search = '';

    public function mount(): void
    {
        abort_unless(auth()->user()->can('activity.view'), 403);
    }

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function with(): array
    {
        return [
            'logs' => ActivityLog::query()
                ->with('user:id,name')
                ->when($this->search, fn ($q) => $q->where('action', 'like', "%{$this->search}%")
                    ->orWhere('description', 'like', "%{$this->search}%"))
                ->latest('created_at')
                ->paginate(15),
        ];
    }
}; ?>

<div>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">{{ __('Bitácora') }}</h2>
    </x-slot>

    <div class="py-12">
        <div class="mx-auto max-w-7xl sm:px-6 lg:px-8">
            <p class="mb-4 text-sm text-gray-500">
                Movimientos de administradores: creación/edición/eliminación de productos, categorías, imágenes,
                usuarios y roles.
            </p>

            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6">
                <input type="search" wire:model.live.debounce.300ms="search" placeholder="Buscar por acción o descripción..." class="mb-4 w-full max-w-xs rounded border-gray-300 text-sm" />

                <table class="w-full text-left text-sm">
                    <thead>
                        <tr class="border-b text-gray-500">
                            <th class="py-2 pr-4">Fecha</th>
                            <th class="py-2 pr-4">Usuario</th>
                            <th class="py-2 pr-4">Acción</th>
                            <th class="py-2 pr-4">Descripción</th>
                            <th class="py-2">Cambios</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($logs as $log)
                            <tr class="border-b align-top" wire:key="log-{{ $log->id }}">
                                <td class="py-2 pr-4 whitespace-nowrap">{{ $log->created_at->format('d/m/Y H:i') }}</td>
                                <td class="py-2 pr-4">{{ $log->user?->name ?? 'Usuario eliminado' }}</td>
                                <td class="py-2 pr-4"><code class="rounded bg-gray-100 px-1.5 py-0.5 text-xs">{{ $log->action }}</code></td>
                                <td class="py-2 pr-4">{{ $log->description }}</td>
                                <td class="py-2">
                                    @if (!$log->old_values && !$log->new_values)
                                        <span class="text-gray-400">—</span>
                                    @else
                                        @php
                                            $keys = collect(array_keys($log->old_values ?? []))
                                                ->merge(array_keys($log->new_values ?? []))
                                                ->unique()
                                                ->sort()
                                                ->values();

                                            $rows = $keys->filter(function ($key) use ($log) {
                                                if (!$log->old_values || !$log->new_values) return true;
                                                return json_encode($log->old_values[$key] ?? null) !== json_encode($log->new_values[$key] ?? null);
                                            });
                                        @endphp

                                        @if ($rows->isEmpty())
                                            <span class="text-gray-400">Sin cambios en los valores.</span>
                                        @else
                                            <details>
                                                <summary class="cursor-pointer text-indigo-600 hover:underline">Ver cambios</summary>
                                                <table class="mt-2 text-xs">
                                                    <thead>
                                                        <tr class="text-gray-500">
                                                            <th class="pb-1 pr-3 text-left">Campo</th>
                                                            @if ($log->old_values)<th class="pb-1 pr-3 text-left">Antes</th>@endif
                                                            @if ($log->new_values)<th class="pb-1 text-left">Después</th>@endif
                                                        </tr>
                                                    </thead>
                                                    <tbody>
                                                        @foreach ($rows as $key)
                                                            <tr class="border-t border-gray-100">
                                                                <td class="py-1 pr-3 font-medium text-gray-500">{{ $key }}</td>
                                                                @if ($log->old_values)
                                                                    <td class="py-1 pr-3">{{ is_array($log->old_values[$key] ?? null) ? implode(', ', $log->old_values[$key]) : (($log->old_values[$key] ?? '') !== '' ? $log->old_values[$key] : '—') }}</td>
                                                                @endif
                                                                @if ($log->new_values)
                                                                    <td class="py-1">{{ is_array($log->new_values[$key] ?? null) ? implode(', ', $log->new_values[$key]) : (($log->new_values[$key] ?? '') !== '' ? $log->new_values[$key] : '—') }}</td>
                                                                @endif
                                                            </tr>
                                                        @endforeach
                                                    </tbody>
                                                </table>
                                            </details>
                                        @endif
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="py-6 text-center text-gray-500">Todavía no hay movimientos registrados.</td></tr>
                        @endforelse
                    </tbody>
                </table>

                <div class="mt-4">{{ $logs->links() }}</div>
            </div>
        </div>
    </div>
</div>
