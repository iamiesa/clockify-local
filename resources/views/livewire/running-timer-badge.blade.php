<div>
    @if ($running && ! $onTrackerPage)
        <a href="{{ route('tracker') }}" wire:navigate wire:key="badge-{{ $running->id }}"
           x-data="timer({{ $running->start->getTimestampMs() }}, {{ now()->getTimestampMs() }})"
           class="hidden sm:flex items-center gap-2 rounded-full border border-line px-3 h-8 text-sm hover:bg-canvas">
            <span class="size-2 rounded-full bg-danger animate-pulse"></span>
            <span class="max-w-40 truncate text-muted">{{ $running->description ?: ($running->project?->name ?? 'Timer running') }}</span>
            <span class="font-medium tabular-nums" x-text="elapsed"></span>
        </a>
    @endif
</div>
