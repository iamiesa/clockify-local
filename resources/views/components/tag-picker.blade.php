@props(['selected' => [], 'names' => []])
{{-- Emits `tags-picked` ({ ids }) when the panel closes with changes. --}}
<div x-data="tagPicker(@js(array_values($selected)))" class="relative" x-on:click.outside="close()" x-on:keydown.escape="close()">
    <button type="button" x-on:click="toggle()" class="flex items-center gap-1 rounded-sm px-2 h-9 text-sm hover:bg-canvas cursor-pointer max-w-48" title="Tags">
        @if (count($names))
            <span class="truncate text-primary">{{ implode(', ', $names) }}</span>
        @else
            <x-icon name="tag" class="size-5 text-muted" />
        @endif
    </button>
    <div x-show="open" x-cloak x-transition.origin.top class="dropdown-panel max-sm:fixed max-sm:inset-x-3 max-sm:w-auto sm:right-0 sm:w-64">
        <template x-if="open">
            <div>
                <div class="p-2 border-b border-line-soft">
                    <input x-ref="search" x-model="search" x-on:keydown.enter.prevent="canCreate && create()" type="text" class="input h-8" placeholder="Add/Find tags…">
                </div>
                <div class="max-h-64 overflow-y-auto py-1">
                    <template x-for="tag in filtered" :key="tag.id">
                        <label class="dropdown-item">
                            <input type="checkbox" class="accent-primary" :checked="isSelected(tag.id)" x-on:change="flip(tag.id)">
                            <span class="truncate" x-text="tag.name"></span>
                        </label>
                    </template>
                    <p x-show="!filtered.length && !canCreate" class="px-3 py-2 text-sm text-muted">No tags.</p>
                </div>
                <button type="button" x-show="canCreate" x-on:click="create()" class="dropdown-item border-t border-line-soft text-primary">
                    <x-icon name="plus" class="size-4" /> <span>Create "<span x-text="search.trim()"></span>"</span>
                </button>
            </div>
        </template>
    </div>
</div>
