<?php

use Illuminate\Support\Facades\Password;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('layouts.guest')] class extends Component
{
    public string $email = '';

    /**
     * Send a password reset link to the provided email address.
     */
    public function sendPasswordResetLink(): void
    {
        $this->validate([
            'email' => ['required', 'string', 'email'],
        ]);

        // We will send the password reset link to this user. Once we have attempted
        // to send the link, we will examine the response then see the message we
        // need to show to the user. Finally, we'll send out a proper response.
        $status = Password::sendResetLink(
            $this->only('email')
        );

        // Hardcoded Spanish, not __($status) — $status is one of the
        // Password broker's own constants (Password::RESET_LINK_SENT etc,
        // e.g. 'passwords.sent'). __() resolves those against the
        // framework's built-in English fallback strings since this app has
        // no lang/ directory of its own, so left as __($status) it would
        // show real but English text on an otherwise all-Spanish page.
        if ($status != Password::RESET_LINK_SENT) {
            $this->addError('email', $status === Password::INVALID_USER
                ? 'No encontramos ningún usuario con ese correo electrónico.'
                : 'Espera un momento antes de volver a intentarlo.');

            return;
        }

        $this->reset('email');

        session()->flash('status', 'Te enviamos por correo el enlace para restablecer tu contraseña.');
    }
}; ?>

<div>
    <h1 class="mb-6 text-xl font-bold text-gray-900">Recupera tu contraseña</h1>

    <div class="mb-4 text-sm text-gray-600">
        ¿Olvidaste tu contraseña? No hay problema. Solo indícanos tu correo electrónico y te
        enviaremos un enlace para que elijas una nueva.
    </div>

    <!-- Session Status -->
    <x-auth-session-status class="mb-4" :status="session('status')" />

    <form wire:submit="sendPasswordResetLink">
        <!-- Email Address -->
        <div>
            <x-input-label for="email" value="Correo electrónico" />
            <x-text-input wire:model="email" id="email" class="block mt-1 w-full" type="email" name="email" required autofocus />
            <x-input-error :messages="$errors->get('email')" class="mt-2" />
        </div>

        <div class="flex items-center justify-end mt-4">
            <x-primary-button>
                Enviar enlace de restablecimiento
            </x-primary-button>
        </div>
    </form>

    <p class="mt-6 text-center text-sm text-gray-600">
        <a href="{{ route('login') }}" wire:navigate class="font-medium text-indigo-600 hover:text-indigo-500">&larr; Volver a iniciar sesión</a>
    </p>
</div>
