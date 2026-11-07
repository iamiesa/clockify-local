@props(['label', 'preset' => null])
{{-- Expects the Livewire component to expose: $preset, $from, $to, applyPreset(), shiftRange(dir). --}}
<div class="flex items-center gap-1">
    <div x-data="dropdown" class="relative" x-on:click.outside="close()">
        <button type="button" x-on:click="toggle()" class="flex items-center gap-2 h-9 px-3 border border-line bg-surface rounded-sm text-sm text-ink cursor-pointer">
            <x-icon name="calendar" class="size-4 text-muted" />
            <span class="whitespace-nowrap">{{ $label }}</span>
        </button>
        <div x-show="open" x-cloak x-transition.origin.top.right class="dropdown-panel max-sm:fixed max-sm:inset-x-3 max-sm:w-auto sm:right-0 sm:w-[28rem] flex flex-col sm:flex-row">
            <div class="sm:w-40 py-1 border-b sm:border-b-0 sm:border-r border-line-soft grid grid-cols-2 sm:block">
                @foreach (\App\Support\DateRange::PRESETS as $key => $text)
                    <button type="button" wire:click="applyPreset('{{ $key }}')" x-on:click="close()" @class(['dropdown-item', 'text-primary font-medium' => $preset === $key])>{{ $text }}</button>
                @endforeach
            </div>
            <div class="flex-1 p-3 space-y-3">
                <div><label class="label">From</label><input type="date" class="input" wire:model="from"></div>
                <div><label class="label">To</label><input type="date" class="input" wire:model="to"></div>
                <button type="button" class="btn-primary w-full" wire:click="applyCustomRange" x-on:click="close()">Apply</button>
            </div>
        </div>
    </div>
    <button type="button" class="btn-icon border border-line bg-surface h-9" wire:click="shiftRange(-1)" aria-label="Previous period"><x-icon name="chevron-left" class="size-4" /></button>
    <button type="button" class="btn-icon border border-line bg-surface h-9" wire:click="shiftRange(1)" aria-label="Next period"><x-icon name="chevron-right" class="size-4" /></button>
</div>
