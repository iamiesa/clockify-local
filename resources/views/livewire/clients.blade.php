<div>
    <h1 class="page-title mb-5">Clients</h1>

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
        @if ($this->isAdmin)
            <form wire:submit="create" class="card flex flex-wrap items-start gap-2 p-2 lg:w-[26rem]">
                <div class="flex-1 min-w-40">
                    <input wire:model="newName" class="input" placeholder="Add new Client">
                    @error('newName')<p class="error">{{ $message }}</p>@enderror
                </div>
                <button class="btn-primary">Add</button>
            </form>
        @endif
    </div>

    <div class="card overflow-x-auto">
        <div class="card-header">Clients</div>
        <table class="w-full text-sm">
            <thead><tr class="table-head border-b border-line-soft text-left"><th class="px-4 py-2">Name</th><th class="hidden sm:table-cell px-4 py-2">Email</th><th class="px-4 py-2">Projects</th><th class="w-28"></th></tr></thead>
            <tbody class="divide-y divide-line-soft">
                @forelse ($clients as $client)
                    <tr wire:key="client-{{ $client->id }}" class="hover:bg-canvas/60">
                        <td class="w-full max-w-0 truncate px-4 py-2.5 sm:w-auto sm:max-w-none">{{ $client->name }} @if ($client->archived)<span class="chip ml-1 bg-canvas text-muted">Archived</span>@endif</td>
                        <td class="hidden sm:table-cell px-4 py-2.5 text-muted">{{ $client->email ?? '—' }}</td>
                        <td class="px-4 py-2.5"><a href="{{ route('projects.index', ['clientId' => $client->id, 'status' => 'all']) }}" wire:navigate class="btn-link">{{ $client->projects_count }}</a></td>
                        <td class="px-2 py-2 text-right whitespace-nowrap">
                            @if ($this->isAdmin)
                                <button type="button" class="btn-icon" wire:click="edit({{ $client->id }})" title="Edit"><x-icon name="pencil" class="size-4" /></button>
                                @if ($client->archived)
                                    <button type="button" class="btn-icon" wire:click="archive({{ $client->id }}, false)" title="Restore"><x-icon name="restore" class="size-4" /></button>
                                    <button type="button" class="btn-icon hover:text-danger" wire:click="delete({{ $client->id }})" wire:confirm="Delete this client? Its projects will be kept without a client." title="Delete"><x-icon name="trash" class="size-4" /></button>
                                @else
                                    <button type="button" class="btn-icon" wire:click="archive({{ $client->id }})" title="Archive"><x-icon name="archive" class="size-4" /></button>
                                @endif
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="4"><x-empty-state icon="clients" title="No clients" text="Add clients to group projects and filter reports." /></td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <x-modal name="client-form" title="Edit client">
        <form wire:submit="update" id="client-form" class="space-y-4">
            <div><label class="label">Name</label><input wire:model="name" class="input">@error('name')<p class="error">{{ $message }}</p>@enderror</div>
            <div><label class="label">Email</label><input type="email" wire:model="email" class="input">@error('email')<p class="error">{{ $message }}</p>@enderror</div>
            <div><label class="label">Address</label><input wire:model="address" class="input">@error('address')<p class="error">{{ $message }}</p>@enderror</div>
            <div><label class="label">Note</label><textarea wire:model="note" rows="3" class="input"></textarea>@error('note')<p class="error">{{ $message }}</p>@enderror</div>
        </form>
        <x-slot:footer>
            <button type="button" class="btn-secondary" x-on:click="$dispatch('close-modal', 'client-form')">Cancel</button>
            <button type="submit" form="client-form" class="btn-primary">Save</button>
        </x-slot:footer>
    </x-modal>
</div>
