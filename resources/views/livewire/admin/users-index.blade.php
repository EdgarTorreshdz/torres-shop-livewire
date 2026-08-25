<?php

use App\Models\ActivityLog;
use App\Models\User;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;
use Livewire\WithPagination;
use Spatie\Permission\Models\Role;

new #[Layout('layouts.app')] class extends Component
{
    use WithPagination;

    public string $search = '';

    public function mount(): void
    {
        abort_unless(auth()->user()->can('users.manage'), 403);
    }

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function updateRole(int $userId, string $role): void
    {
        $user = User::findOrFail($userId);

        if ($user->id === auth()->id() && $role !== 'admin') {
            throw ValidationException::withMessages(['role' => 'No puedes quitarte el rol de administrador a ti mismo.']);
        }

        $previousRole = $user->roles->first()?->name ?? 'sin rol';
        $user->syncRoles([$role]);

        ActivityLog::record(
            auth()->user(),
            'user.role_updated',
            "Cambió el rol de {$user->name} ({$user->email}) de \"{$previousRole}\" a \"{$role}\"",
            $user,
            oldValues: ['role' => $previousRole],
            newValues: ['role' => $role],
        );
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
            'roles' => Role::orderBy('name')->pluck('name'),
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
                            <th class="py-2 pr-4">{{ __('Rol') }}</th>
                            <th class="py-2">{{ __('Acciones') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($users as $user)
                            <tr class="border-b" wire:key="user-{{ $user->id }}">
                                <td class="py-2 pr-4">
                                    {{ $user->name }}
                                    @if ($user->id === auth()->id())
                                        <span class="text-xs text-gray-400">(tú)</span>
                                    @endif
                                </td>
                                <td class="py-2 pr-4">{{ $user->email }}</td>
                                <td class="py-2 pr-4">
                                    <select
                                        wire:change="updateRole({{ $user->id }}, $event.target.value)"
                                        @if ($user->id === auth()->id()) disabled title="No puedes cambiar tu propio rol" @endif
                                        class="rounded border-gray-300 text-sm disabled:bg-gray-100"
                                    >
                                        @foreach ($roles as $roleName)
                                            <option value="{{ $roleName }}" @selected($user->roles->first()?->name === $roleName)>{{ $roleName }}</option>
                                        @endforeach
                                    </select>
                                </td>
                                <td class="py-2">
                                    <a href="{{ route('admin.usuarios.editar', $user) }}" wire:navigate class="text-indigo-600 hover:underline">Editar nombre/contraseña</a>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4" class="py-6 text-center text-gray-500">{{ __('No hay usuarios.') }}</td>
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
