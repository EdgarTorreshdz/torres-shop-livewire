<?php

use App\Concerns\Notifies;
use App\Models\ActivityLog;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('layouts.app')] class extends Component
{
    use Notifies;

    public User $user;

    public string $name = '';
    public string $password = '';
    public string $password_confirmation = '';

    public function mount(User $user): void
    {
        abort_unless(auth()->user()->can('users.manage'), 403);
        $this->user = $user;
        $this->name = $user->name;
    }

    public function save(): void
    {
        $validated = $this->validate([
            'name' => ['required', 'string', 'max:255'],
            'password' => ['nullable', 'confirmed', Password::defaults()],
        ]);

        $previousName = $this->user->name;

        $this->user->name = $validated['name'];

        if (! empty($validated['password'])) {
            $this->user->password = Hash::make($validated['password']);
        }

        $this->user->save();

        // Name only — password is never captured here, hashed or not, even
        // though a password change did happen (the description below still
        // notes that it changed).
        ActivityLog::record(
            auth()->user(),
            'user.updated',
            "Actualizó los datos de {$this->user->name} ({$this->user->email})".(! empty($validated['password']) ? ', incluyendo contraseña' : ''),
            $this->user,
            oldValues: ['name' => $previousName],
            newValues: ['name' => $this->user->name],
        );

        $this->notifySuccess('Cambios guardados correctamente.');
        $this->redirect(route('admin.usuarios'), navigate: true);
    }
}; ?>

<div>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">{{ __('Editar usuario') }}</h2>
    </x-slot>

    <div class="py-12">
        <div class="mx-auto max-w-lg sm:px-6 lg:px-8">
            <a href="{{ route('admin.usuarios') }}" wire:navigate class="mb-4 inline-block text-sm text-gray-500 hover:text-gray-700">&larr; Volver a usuarios</a>

            <form wire:submit="save" class="flex flex-col gap-4 rounded-lg border border-gray-200 bg-white p-6">
                <p class="text-sm text-gray-500">{{ $user->email }}</p>

                <label class="flex flex-col gap-1 text-sm text-gray-700">
                    Nombre
                    <input type="text" wire:model="name" required class="rounded @error('name') border-red-500 ring-1 ring-red-500 @else border-gray-300 @enderror" />
                    @error('name') <span class="text-xs text-red-600">{{ $message }}</span> @enderror
                </label>

                <label class="flex flex-col gap-1 text-sm text-gray-700">
                    Nueva contraseña (opcional)
                    <input type="password" wire:model="password" class="rounded @error('password') border-red-500 ring-1 ring-red-500 @else border-gray-300 @enderror" />
                    @error('password') <span class="text-xs text-red-600">{{ $message }}</span> @enderror
                </label>

                <label class="flex flex-col gap-1 text-sm text-gray-700">
                    Confirmar contraseña
                    <input type="password" wire:model="password_confirmation" class="rounded border-gray-300" />
                </label>

                <button type="submit" class="w-fit rounded-full bg-gray-900 px-6 py-2.5 text-sm font-semibold text-white hover:bg-gray-700">
                    Guardar cambios
                </button>
            </form>
        </div>
    </div>
</div>
