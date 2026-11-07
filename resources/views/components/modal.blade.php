@props(['name', 'title', 'maxWidth' => 'max-w-lg'])
{{-- Opened with $dispatch('open-modal', '{{ $name }}') / $this->dispatch('open-modal', name) and closed via 'close-modal'. --}}
<div x-data="{ show: false }"
     x-on:open-modal.window="if ($event.detail === '{{ $name }}' || $event.detail?.[0] === '{{ $name }}' || $event.detail?.name === '{{ $name }}') show = true"
     x-on:close-modal.window="if (!$event.detail || $event.detail === '{{ $name }}' || $event.detail?.[0] === '{{ $name }}' || $event.detail?.name === '{{ $name }}') show = false"
     x-on:keydown.escape.window="show = false"
     x-show="show" x-cloak
     class="fixed inset-0 z-50 flex items-start justify-center overflow-y-auto px-4 py-10 sm:py-20" role="dialog" aria-modal="true">
    <div x-show="show" x-transition.opacity class="fixed inset-0 bg-black/40" x-on:click="show = false"></div>
    <div x-show="show" x-transition class="relative w-full {{ $maxWidth }} bg-surface rounded-sm shadow-xl">
        <div class="flex items-center justify-between border-b border-line-soft px-5 py-4">
            <h2 class="text-lg text-ink">{{ $title }}</h2>
            <button type="button" class="btn-icon" x-on:click="show = false" aria-label="Close"><x-icon name="x" /></button>
        </div>
        <div class="px-5 py-5">{{ $slot }}</div>
        @isset($footer)
            <div class="flex justify-end gap-3 border-t border-line-soft px-5 py-3 bg-canvas/60">{{ $footer }}</div>
        @endisset
    </div>
</div>
