{{--
    Global toast container for the admin side. Two delivery paths, because
    a Livewire action either stays on the same page or redirects away:

    1. Same-page actions (e.g. deleting a row from a list) dispatch a
       'toast' browser event — Livewire's $this->dispatch() becomes a real
       window CustomEvent, so Alpine picks it up immediately, no reload.
    2. Actions that redirect (create/update forms) flash the message to
       the session instead (see App\Concerns\Notifies). That flash is read
       into the data-flash-toast attribute below on every render — but
       triggering it from Alpine's x-init would only work for a plain
       first load: this container is structurally identical across admin
       pages, so Livewire's wire:navigate morph patches this div's
       attributes in place instead of replacing the node, and x-init only
       ever fires once per node's lifetime. livewire:navigated fires on
       every render instead (first load AND every subsequent SPA nav), so
       reading the attribute fresh there is what actually works across
       navigations.

    Auto-dismisses after 4s either way.

    The <script> below guards its own registration with a flag on
    `window`: unlike the div above (patched in place, never re-executed),
    Livewire's morph deliberately re-runs <script> tags on every
    wire:navigate transition — that's the only way an inline script could
    ever run again after the first page load. Without the guard, every
    navigation added one more 'livewire:navigated' listener, so after N
    navigations a single save would fire the same toast N times.
--}}
<div
    x-data="{
        toasts: [],
        nextId: 1,
        push(type, message) {
            const id = this.nextId++;
            this.toasts.push({ id, type, message });
            setTimeout(() => this.dismiss(id), 4000);
        },
        dismiss(id) {
            this.toasts = this.toasts.filter(t => t.id !== id);
        },
    }"
    x-on:toast.window="push($event.detail.type, $event.detail.message)"
    data-flash-toast="{{ session('toast') ? json_encode(session('toast')) : '' }}"
    class="pointer-events-none fixed inset-x-0 top-4 z-[60] flex flex-col items-center gap-2 px-4"
>
    <template x-for="toast in toasts" :key="toast.id">
        <div
            x-show="true"
            x-transition
            class="pointer-events-auto flex w-full max-w-sm items-start gap-3 rounded-lg border px-4 py-3 text-sm shadow-lg"
            :class="toast.type === 'error' ? 'border-red-200 bg-red-50 text-red-700' : 'border-green-200 bg-green-50 text-green-700'"
        >
            <span class="flex-1" x-text="toast.message"></span>
            <button type="button" @click="dismiss(toast.id)" class="shrink-0 text-lg leading-none opacity-60 hover:opacity-100">&times;</button>
        </div>
    </template>
</div>

<script>
    if (!window.__adminToastListenerAttached) {
        window.__adminToastListenerAttached = true;

        document.addEventListener('livewire:navigated', () => {
            const raw = document.querySelector('[data-flash-toast]')?.dataset.flashToast;
            if (!raw) return;

            const { type, message } = JSON.parse(raw);
            window.dispatchEvent(new CustomEvent('toast', { detail: { type, message } }));
        });
    }
</script>
