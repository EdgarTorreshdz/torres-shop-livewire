<?php

use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('layouts.guest')] class extends Component
{
    public string $password = '';

    /**
     * Confirm the current user's password.
     */
    public function confirmPassword(): void
    {
        $this->validate([
            'password' => ['required', 'string'],
        ]);

        if (! Auth::guard('web')->validate([
            'email' => Auth::user()->email,
            'password' => $this->password,
        ])) {
            // Hardcoded Spanish, not __('auth.password') — see
            // LoginForm::authenticate() for why.
            throw ValidationException::withMessages([
                'password' => 'La contraseña ingresada es incorrecta.',
            ]);
        }

        session(['auth.password_confirmed_at' => time()]);

        $this->redirectIntended(default: route('dashboard', absolute: false), navigate: true);
    }
}; ?>

<div>
    <h1 class="mb-6 text-xl font-bold text-gray-900">Confirma tu contraseña</h1>

    <div class="mb-4 text-sm text-gray-600">
        Esta es un área segura de la aplicación. Por favor confirma tu contraseña antes de continuar.
    </div>

    <form wire:submit="confirmPassword">
        <!-- Password -->
        <div>
            <x-input-label for="password" value="Contraseña" />

            <x-text-input wire:model="password"
                          id="password"
                          class="block mt-1 w-full"
                          type="password"
                          name="password"
                          required autocomplete="current-password" />

            <x-input-error :messages="$errors->get('password')" class="mt-2" />
        </div>

        <div class="flex justify-end mt-4">
            <x-primary-button>
                Confirmar
            </x-primary-button>
        </div>
    </form>
</div>
