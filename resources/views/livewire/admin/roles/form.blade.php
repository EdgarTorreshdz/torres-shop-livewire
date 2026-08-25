<?php

use App\Concerns\Notifies;
use App\Models\ActivityLog;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

new #[Layout('layouts.app')] class extends Component
{
    use Notifies;

    private const PROTECTED_ROLES = ['admin', 'customer'];

    public ?Role $role = null;

    public string $name = '';

    /** @var string[] */
    public array $permissions = [];

    public function mount(?Role $role = null): void
    {
        abort_unless(auth()->user()->can('roles.manage'), 403);

        if ($role?->exists) {
            $this->role = $role;
            $this->name = $role->name;
            $this->permissions = $role->permissions()->pluck('name')->all();
        }
    }

    public function isProtected(): bool
    {
        return $this->role && in_array($this->role->name, self::PROTECTED_ROLES, true);
    }

    public function isAdminRole(): bool
    {
        return $this->role?->name === 'admin';
    }

    public function save(): void
    {
        $validated = $this->validate([
            'name' => ['required', 'string', 'max:255', $this->role
                ? 'unique:roles,name,'.$this->role->id
                : 'unique:roles,name'],
        ]);

        $isNew = ! $this->role;

        if ($this->role) {
            if ($this->isProtected() && $validated['name'] !== $this->role->name) {
                throw ValidationException::withMessages([
                    'name' => "El rol \"{$this->role->name}\" no se puede renombrar — varias partes de la app dependen de ese nombre exacto.",
                ]);
            }

            $before = ['name' => $this->role->name, 'permissions' => $this->role->permissions()->pluck('name')->all()];

            // 'admin' always keeps every permission that exists, regardless
            // of what was submitted — otherwise it's possible to lock every
            // admin (including yourself) out of part of the panel with no
            // way back in through the UI itself.
            $permissions = $this->isAdminRole() ? Permission::pluck('name')->all() : $this->permissions;

            $this->role->update(['name' => $validated['name']]);
            $this->role->syncPermissions($permissions);

            ActivityLog::record(
                auth()->user(),
                'role.updated',
                "Actualizó los permisos del rol \"{$this->role->name}\"",
                $this->role,
                oldValues: $before,
                newValues: ['name' => $this->role->name, 'permissions' => $permissions],
            );
        } else {
            $newRole = Role::create(['name' => $validated['name']]);
            $newRole->syncPermissions($this->permissions);

            ActivityLog::record(
                auth()->user(),
                'role.created',
                "Creó el rol \"{$newRole->name}\"",
                $newRole,
                newValues: ['name' => $newRole->name, 'permissions' => $this->permissions],
            );
        }

        $this->notifySuccess($isNew ? 'Rol creado correctamente.' : 'Cambios guardados correctamente.');
        $this->redirect(route('admin.roles'), navigate: true);
    }

    public function with(): array
    {
        return ['allPermissions' => Permission::orderBy('name')->pluck('name')];
    }
}; ?>

<div>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ $role ? 'Editar rol' : 'Nuevo rol' }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="mx-auto max-w-2xl sm:px-6 lg:px-8">
            <a href="{{ route('admin.roles') }}" wire:navigate class="mb-4 inline-block text-sm text-gray-500 hover:text-gray-700">&larr; Volver a roles</a>

            <form wire:submit="save" class="flex flex-col gap-4 rounded-lg border border-gray-200 bg-white p-6">
                <label class="flex flex-col gap-1 text-sm text-gray-700">
                    Nombre del rol
                    <input
                        type="text"
                        wire:model="name"
                        required
                        @if ($this->isProtected()) disabled title="Este nombre no se puede cambiar" @endif
                        class="rounded disabled:bg-gray-100 disabled:text-gray-500 @error('name') border-red-500 ring-1 ring-red-500 @else border-gray-300 @enderror"
                    />
                    @error('name') <span class="text-xs text-red-600">{{ $message }}</span> @enderror
                </label>

                <fieldset class="flex flex-col gap-2">
                    <legend class="mb-1 text-sm font-medium uppercase tracking-wide text-gray-500">
                        Permisos
                        @if ($this->isAdminRole())
                            <span class="normal-case text-gray-400">(admin siempre tiene todos)</span>
                        @endif
                    </legend>
                    <div class="grid grid-cols-2 gap-2">
                        @foreach ($allPermissions as $permission)
                            <label class="flex items-center gap-2 text-sm text-gray-700">
                                <input
                                    type="checkbox"
                                    wire:model="permissions"
                                    value="{{ $permission }}"
                                    @if ($this->isAdminRole()) checked disabled @endif
                                    class="rounded border-gray-300"
                                />
                                <code class="text-xs">{{ $permission }}</code>
                            </label>
                        @endforeach
                    </div>
                </fieldset>

                <div class="flex items-center gap-4">
                    <button type="submit" class="w-fit rounded-full bg-gray-900 px-6 py-2.5 text-sm font-semibold text-white hover:bg-gray-700">
                        {{ $role ? 'Guardar cambios' : 'Crear rol' }}
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
