// Global confirm-modal trigger, used by the admin panel instead of
// wire:confirm (which is just the native, unstyled browser confirm()).
// Usage from any Blade template inside the admin layout:
//
//   <button x-on:click="confirmAction('¿Eliminar?', () => $wire.delete(1))">
//
// Dispatches a plain window CustomEvent that resources/views/components/
// confirm-modal.blade.php listens for — no Livewire round-trip needed just
// to show/hide the modal, the action callback itself is whatever Alpine/
// Livewire call the caller passed in.
window.confirmAction = (message, action) => {
    window.dispatchEvent(new CustomEvent('confirm-action', { detail: { message, action } }));
};
