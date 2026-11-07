@props(['label' => null, 'description' => null])
<label class="flex items-start gap-3 cursor-pointer">
    <span class="relative mt-0.5 inline-flex shrink-0">
        <input type="checkbox" {{ $attributes }} class="peer sr-only">
        <span class="h-5 w-9 rounded-full bg-line transition-colors peer-checked:bg-primary"></span>
        <span class="absolute left-0.5 top-0.5 size-4 rounded-full bg-white shadow transition-transform peer-checked:translate-x-4"></span>
    </span>
    @if ($label)
        <span><span class="block text-sm text-ink">{{ $label }}</span>@if ($description)<span class="block text-xs text-muted">{{ $description }}</span>@endif</span>
    @endif
</label>
