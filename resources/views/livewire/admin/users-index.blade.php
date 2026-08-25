<?php

use App\Models\User;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;
use Livewire\WithPagination;

// Minimal reference implementation of the admin-panel pattern this
// template exists to demonstrate: a role-gated page (see the `role:admin`
// middleware on the route in routes/web.php — the real boundary, this
// component only decides what's rendered) with search + pagination
// handled entirely by Livewire itself. No separate JSON API, no
// hand-rolled DataTable JS class, no CORS/token plumbing — just a
// component, a Blade view, and the same session auth as the rest of the
// app. Concrete projects generated from this template replace this one
// example with real admin sections (products, orders, whatever the
// project needs), following the same shape.
new #[Layout('layouts.app')] class extends Component
{
    use WithPagination;

    public string $search = '';

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function with(): array
    {
        return [
            'users' => User::query()
                ->when($this->search, fn ($query) => $query->where('name', 'like', "%{$this->search}%")
                    ->orWhere('email', 'like', "%{$this->search}%"))
                ->with('roles:id,name')
                ->orderBy('name')
                ->paginate(10),
        ];
    }
}; ?>

<div>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">{{ __('Usuarios') }}</h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6">
                <input
                    type="search"
                    wire:model.live.debounce.300ms="search"
                    placeholder="{{ __('Buscar por nombre o email...') }}"
                    class="mb-4 w-full max-w-xs rounded border-gray-300 text-sm"
                />

                <table class="w-full text-left text-sm">
                    <thead>
                        <tr class="border-b text-gray-500">
                            <th class="py-2 pr-4">{{ __('Nombre') }}</th>
                            <th class="py-2 pr-4">{{ __('Email') }}</th>
                            <th class="py-2">{{ __('Roles') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($users as $user)
                            <tr class="border-b" wire:key="user-{{ $user->id }}">
                                <td class="py-2 pr-4">{{ $user->name }}</td>
                                <td class="py-2 pr-4">{{ $user->email }}</td>
                                <td class="py-2">{{ $user->roles->pluck('name')->join(', ') ?: '—' }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="3" class="py-6 text-center text-gray-500">{{ __('No hay usuarios.') }}</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>

                <div class="mt-4">
                    {{ $users->links() }}
                </div>
            </div>
        </div>
    </div>
</div>
