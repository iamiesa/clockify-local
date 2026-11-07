@props(['label', 'model', 'options' => [], 'selected' => [], 'withNone' => null])
{{-- Multi-select checkbox dropdown bound to an array property. $options: [id => label] or [id => ['label'=>..,'color'=>..]] --}}
<div x-data="dropdown" class="relative" x-on:click.outside="close()">
    <button type="button" x-on:click="toggle()" @class(['flex items-center gap-1 px-3 h-9 text-sm uppercase tracking-wide hover:text-ink cursor-pointer', 'text-primary' => count($selected) > 0, 'text-muted' => count($selected) === 0])>
        {{ $label }}@if (count($selected)) <span class="ml-1 rounded-full bg-primary px-1.5 text-[10px] text-white">{{ count($selected) }}</span>@endif
        <x-icon name="chevron-down" class="size-3.5" />
    </button>
    <div x-show="open" x-cloak x-transition.origin.top.left class="dropdown-panel w-64 left-0">
        <div class="p-2 border-b border-line-soft"><input x-ref="search" x-model="search" type="text" class="input h-8" placeholder="Search {{ strtolower($label) }}"></div>
        <div class="max-h-64 overflow-y-auto py-1">
            @if ($withNone)
                <label class="dropdown-item" x-show="matches(@js($withNone))">
                    <input type="checkbox" value="0" wire:model.live="{{ $model }}" class="accent-primary"> <span class="text-muted">{{ $withNone }}</span>
                </label>
            @endif
            @forelse ($options as $id => $option)
                @php $text = is_array($option) ? $option['label'] : $option; @endphp
                <label class="dropdown-item" wire:key="{{ $model }}-{{ $id }}" x-show="matches(@js($text))">
                    <input type="checkbox" value="{{ $id }}" wire:model.live="{{ $model }}" class="accent-primary">
                    @if (is_array($option) && isset($option['color']))<span class="size-2.5 rounded-full shrink-0" style="background: {{ $option['color'] }}"></span>@endif
                    <span class="truncate">{{ $text }}</span>
                </label>
            @empty
                <p class="px-3 py-2 text-sm text-muted">Nothing to filter by.</p>
            @endforelse
        </div>
        @if (count($selected))
            <div class="border-t border-line-soft p-2 text-right"><button type="button" class="btn-link" wire:click="$set('{{ $model }}', [])">Clear</button></div>
        @endif
    </div>
</div>
