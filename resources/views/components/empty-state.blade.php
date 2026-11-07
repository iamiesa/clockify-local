@props(['icon' => 'clock', 'title', 'text' => null])
<div class="flex flex-col items-center justify-center py-16 text-center">
    <div class="mb-4 flex size-16 items-center justify-center rounded-full bg-primary-soft text-primary"><x-icon :name="$icon" class="size-8" /></div>
    <p class="text-lg text-ink">{{ $title }}</p>
    @if ($text)<p class="mt-1 max-w-sm text-sm text-muted">{{ $text }}</p>@endif
    {{ $slot }}
</div>
