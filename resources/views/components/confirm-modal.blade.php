{{--
    Single global confirm modal for the whole admin side, replacing the
    native browser confirm() that `wire:confirm` shows. Triggered from
    anywhere via window.confirmAction(message, action) — see
    resources/js/app.js. `action` runs only if the user clicks "Confirmar",
    and is whatever the caller passed (typically a `$wire.method(...)` call),
    so this component itself has no idea what it's confirming.
--}}
<div
    x-data="{
        open: false,
        message: '',
        pendingAction: null,
        confirmDanger: true,
        onConfirmAction(event) {
            this.message = event.detail.message;
            this.pendingAction = event.detail.action;
            this.confirmDanger = event.detail.danger ?? true;
            this.open = true;
        },
        proceed() {
            if (this.pendingAction) this.pendingAction();
            this.open = false;
        },
    }"
    x-on:confirm-action.window="onConfirmAction($event)"
    x-on:keydown.escape.window="open = false"
    x-cloak
>
    <div
        x-show="open"
        x-transition.opacity
        class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4"
        role="alertdialog"
        aria-modal="true"
    >
        <div
            x-show="open"
            x-transition
            @click.outside="open = false"
            class="w-full max-w-sm rounded-lg bg-white p-6 shadow-xl"
        >
            <p class="text-sm text-gray-700" x-text="message"></p>

            <div class="mt-6 flex justify-end gap-3">
                <button
                    type="button"
                    @click="open = false"
                    class="rounded-full border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50"
                >
                    Cancelar
                </button>
                <button
                    type="button"
                    @click="proceed()"
                    :class="confirmDanger ? 'bg-red-600 hover:bg-red-700' : 'bg-gray-900 hover:bg-gray-700'"
                    class="rounded-full px-4 py-2 text-sm font-medium text-white"
                >
                    Confirmar
                </button>
            </div>
        </div>
    </div>
</div>
