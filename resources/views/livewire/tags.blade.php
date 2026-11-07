<div>
    <h1 class="page-title mb-5">Tags</h1>

    <div class="mb-4 flex flex-col gap-3 lg:flex-row lg:items-start">
        <div class="card flex flex-1 flex-wrap items-center gap-2 p-2">
            <select wire:model.live="status" class="input w-auto" aria-label="Status">
                <option value="active">Show active</option>
                <option value="archived">Show archived</option>
                <option value="all">Show all</option>
            </select>
            <div class="relative min-w-48 flex-1">
                <x-icon name="search" class="absolute left-2.5 top-2.5 size-4 text-muted" />
                <input type="text" wire:model.live.debounce.300ms="search" class="input pl-8" placeholder="Search by name">
            </div>
        </div>
        @if ($canCreate)
            <form wire:submit="create" class="card flex flex-wrap items-start gap-2 p-2 lg:w-[26rem]">
                <div class="flex-1 min-w-40">
                    <input wire:model="newName" class="input" placeholder="Add new tag">
                    @error('newName')<p class="error">{{ $message }}</p>@enderror
                </div>
                <button class="btn-primary">Add</button>
            </form>
        @endif
    </div>

    <div class="card overflow-x-auto">
        <div class="card-header">Tags</div>
        <table class="w-full text-sm">
            <thead><tr class="table-head border-b border-line-soft text-left"><th class="px-4 py-2">Name</th><th class="hidden sm:table-cell px-4 py-2">Used in</th><th class="w-28"></th></tr></thead>
            <tbody class="divide-y divide-line-soft">
                @forelse ($tags as $tag)
                    <tr wire:key="tag-{{ $tag->id }}-{{ $tag->updated_at?->getTimestamp() }}" class="hover:bg-canvas/60">
                        <td class="px-4 py-2">
                            @if ($this->isAdmin)
                                <input value="{{ $tag->name }}" x-on:change="$wire.rename({{ $tag->id }}, $event.target.value)" x-on:keydown.enter="$event.target.blur()"
                                       class="h-8 w-full max-w-sm rounded-sm border border-transparent px-2 hover:border-line focus:border-primary focus:outline-none">
                            @else
                                {{ $tag->name }}
                            @endif
                            @if ($tag->archived)<span class="chip bg-canvas text-muted">Archived</span>@endif
                        </td>
                        <td class="hidden sm:table-cell px-4 py-2 text-muted">{{ $tag->time_entries_count }} {{ Str::plural('entry', $tag->time_entries_count) }}</td>
                        <td class="px-2 py-2 text-right whitespace-nowrap">
                            @if ($this->isAdmin)
                                @if ($tag->archived)
                                    <button type="button" class="btn-icon" wire:click="archive({{ $tag->id }}, false)" title="Restore"><x-icon name="restore" class="size-4" /></button>
                                    <button type="button" class="btn-icon hover:text-danger" wire:click="delete({{ $tag->id }})" wire:confirm="Delete this tag? It will be removed from all time entries." title="Delete"><x-icon name="trash" class="size-4" /></button>
                                @else
                                    <button type="button" class="btn-icon" wire:click="archive({{ $tag->id }})" title="Archive"><x-icon name="archive" class="size-4" /></button>
                                @endif
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="3"><x-empty-state icon="tag" title="No tags" text="Tags help you categorise time entries across projects." /></td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
