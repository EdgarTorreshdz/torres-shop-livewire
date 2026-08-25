<?php

namespace App\Concerns;

/**
 * One consistent way for every admin Volt component to show a toast —
 * see resources/views/components/admin-toast.blade.php for how it's
 * actually displayed. Both a live browser event (for actions that stay on
 * the same page) AND a session flash (for actions that redirect) are sent
 * on every call, since a component calling this has no reliable way to
 * know in advance whether it's about to redirect.
 */
trait Notifies
{
    protected function notifySuccess(string $message): void
    {
        $this->notify('success', $message);
    }

    protected function notifyError(string $message): void
    {
        $this->notify('error', $message);
    }

    private function notify(string $type, string $message): void
    {
        session()->flash('toast', ['type' => $type, 'message' => $message]);
        $this->dispatch('toast', type: $type, message: $message);
    }
}
