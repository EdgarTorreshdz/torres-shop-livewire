<?php

use App\Models\ActivityLog;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;
use Livewire\WithPagination;
use Spatie\Permission\Models\Role;

new #[Layout('layouts.app')] class extends Component
{
    use WithPagination;

    // 'admin' and 'customer' are load-bearing: self-registration assigns
    // 'customer', every admin-only check ultimately traces back to
    // hasRole('admin'). Renaming or deleting either would silently break
    // the app, so both are protected here rather than just documented and
    // hoped for.
    private const PROTECTED_ROLES = ['admin', 'customer'];

    public function mount(): void
    {
        abort_unless(auth()->user()->can('roles.manage'), 403);
    }

    public function delete(int $roleId): void
    {
        $role = Role::findOrFail($roleId);

        if (in_array($role->name, self::PROTECTED_ROLES, true)) {
            throw ValidationException::withMessages(['name' => "El rol \"{$role->name}\" no se puede eliminar."]);
        }

        $name = $role->name;
        $before = ['name' => $name, 'permissions' => $role->permissions()->pluck('name')->all()];
        $role->delete();

        ActivityLog::record(auth()->user(), 'role.deleted', "Eliminó el rol \"{$name}\"", oldValues: $before);
    }

    public function with(): array
    {
        return [
            'roles' => Role::with('permissions:id,name')->orderBy('name')->paginate(10),
            'protectedRoles' => self::PROTECTED_ROLES,
        ];
    }
}; ?>

<div>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">{{ __('Roles y permisos') }}</h2>
    </x-slot>

    <div class="py-12">
        <div class="mx-auto max-w-7xl sm:px-6 lg:px-8">
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6">
                <div class="mb-4 flex items-center justify-between">
                    <p class="max-w-2xl text-sm text-gray-500">
                        Los roles <code class="rounded bg-gray-100 px-1.5 py-0.5 text-xs">admin</code> y
                        <code class="rounded bg-gray-100 px-1.5 py-0.5 text-xs">customer</code> no se pueden renombrar
                        ni eliminar. <code class="rounded bg-gray-100 px-1.5 py-0.5 text-xs">admin</code> siempre
                        conserva todos los permisos.
                    </p>
                    <a href="{{ route('admin.roles.nuevo') }}" wire:navigate class="shrink-0 rounded-full bg-gray-900 px-4 py-2 text-sm font-medium text-white hover:bg-gray-700">
                        Nuevo rol
                    </a>
                </div>

                <table class="w-full text-left text-sm">
                    <thead>
                        <tr class="border-b text-gray-500">
                            <th class="py-2 pr-4">Rol</th>
                            <th class="py-2 pr-4">Permisos</th>
                            <th class="py-2">Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($roles as $role)
                            <tr class="border-b align-top" wire:key="role-{{ $role->id }}">
                                <td class="py-2 pr-4 font-medium">
                                    {{ $role->name }}
                                    @if (in_array($role->name, $protectedRoles, true))
                                        <span class="text-xs text-gray-400">(protegido)</span>
                                    @endif
                                </td>
                                <td class="py-2 pr-4">
                                    @if ($role->name === 'admin')
                                        <span class="text-gray-500">Todos los permisos</span>
                                    @elseif ($role->permissions->isEmpty())
                                        <span class="text-gray-500">Sin permisos asignados</span>
                                    @else
                                        @foreach ($role->permissions as $permission)
                                            <code class="mb-1 mr-1 inline-block rounded bg-gray-100 px-1.5 py-0.5 text-xs">{{ $permission->name }}</code>
                                        @endforeach
                                    @endif
                                </td>
                                <td class="py-2">
                                    <a href="{{ route('admin.roles.editar', $role) }}" wire:navigate class="text-indigo-600 hover:underline">Editar</a>
                                    @unless (in_array($role->name, $protectedRoles, true))
                                        <button
                                            type="button"
                                            wire:click="delete({{ $role->id }})"
                                            wire:confirm="¿Eliminar el rol &quot;{{ $role->name }}&quot;? Los usuarios con este rol se quedarán sin rol asignado."
                                            class="ml-3 text-red-600 hover:underline"
                                        >
                                            Eliminar
                                        </button>
                                    @endunless
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>

                <div class="mt-4">{{ $roles->links() }}</div>
            </div>
        </div>
    </div>
</div>
