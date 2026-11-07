@php use App\Support\Duration; use App\Support\Money; $currency = $this->workspace->currency; @endphp
<div>
    <div class="mb-5 flex flex-wrap items-center gap-3">
        <h1 class="page-title mr-auto">Projects</h1>
        @if ($canCreate)
            <button type="button" class="btn-primary" x-on:click="$dispatch('open-project-form', { origin: 'projects' })"><x-icon name="plus" class="size-4" /> Create new project</button>
        @endif
    </div>

    <div class="card mb-4 flex flex-wrap items-center gap-2 p-2">
        <select wire:model.live="status" class="input w-auto" aria-label="Status">
            <option value="active">Active</option>
            <option value="archived">Archived</option>
            <option value="all">All</option>
        </select>
        <select wire:model.live="clientId" class="input w-auto max-w-48" aria-label="Client">
            <option value="">All clients</option>
            <option value="none">Without client</option>
            @foreach ($clients as $client)<option value="{{ $client->id }}" wire:key="fc-{{ $client->id }}">{{ $client->name }}</option>@endforeach
        </select>
        @unless (config('tracker.shared_workspace'))
        <select wire:model.live="access" class="input w-auto" aria-label="Access">
            <option value="">Any access</option>
            <option value="public">Public</option>
            <option value="private">Private</option>
        </select>
        @endunless
        <select wire:model.live="billing" class="input w-auto" aria-label="Billing">
            <option value="">Any billing</option>
            <option value="billable">Billable</option>
            <option value="non">Non-billable</option>
        </select>
        <div class="relative min-w-48 flex-1">
            <x-icon name="search" class="absolute left-2.5 top-2.5 size-4 text-muted" />
            <input type="text" wire:model.live.debounce.300ms="search" class="input pl-8" placeholder="Search by name">
        </div>
    </div>

    <div class="card overflow-x-auto" wire:loading.class="opacity-60">
        <div class="card-header">Projects</div>
        <table class="w-full text-sm">
            <thead>
                <tr class="table-head border-b border-line-soft text-left">
                    <th class="w-10"></th>
                    <th class="px-3 py-2"><button type="button" wire:click="sortBy('name')" class="uppercase cursor-pointer {{ $sort === 'name' ? 'text-ink' : '' }}">Name</button></th>
                    <th class="hidden sm:table-cell px-3 py-2"><button type="button" wire:click="sortBy('client')" class="uppercase cursor-pointer {{ $sort === 'client' ? 'text-ink' : '' }}">Client</button></th>
                    <th class="px-3 py-2 text-right"><button type="button" wire:click="sortBy('tracked')" class="uppercase cursor-pointer {{ $sort === 'tracked' ? 'text-ink' : '' }}">Tracked</button></th>
                    <th class="hidden px-3 py-2 text-right md:table-cell">Amount</th>
                    <th class="hidden px-3 py-2 lg:table-cell">Progress</th>
                    <th class="hidden px-3 py-2 md:table-cell">Access</th>
                    <th class="w-12"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-line-soft">
                @forelse ($projects as $project)
                    @php $progress = $project->estimate_hours ? min(100, round($project->tracked / ($project->estimate_hours * 3600) * 100)) : null; @endphp
                    <tr wire:key="p-{{ $project->id }}" class="hover:bg-canvas/60">
                        <td class="pl-3">
                            <button type="button" wire:click="toggleFavorite({{ $project->id }})" aria-label="Favorite" @class(['cursor-pointer', 'text-amber-400' => $project->is_favorite, 'text-line hover:text-muted' => ! $project->is_favorite])>
                                <x-icon name="star" class="size-5 {{ $project->is_favorite ? 'fill-current' : '' }}" />
                            </button>
                        </td>
                        <td class="w-full max-w-0 px-3 py-2.5 sm:w-auto sm:max-w-none">
                            <a href="{{ route('projects.show', $project) }}" wire:navigate class="flex min-w-0 items-center gap-2 hover:underline">
                                <span class="size-2.5 shrink-0 rounded-full" style="background: {{ $project->color }}"></span>
                                <span class="truncate" style="color: {{ $project->color }}">{{ $project->name }}</span>
                                @if ($project->archived)<span class="chip bg-canvas text-muted">Archived</span>@endif
                            </a>
                            @if ($project->client)<p class="truncate pl-4.5 text-xs text-muted sm:hidden">{{ $project->client->name }}</p>@endif
                        </td>
                        <td class="hidden sm:table-cell px-3 py-2.5 text-muted">{{ $project->client?->name ?? '—' }}</td>
                        <td class="whitespace-nowrap px-3 py-2.5 text-right tabular-nums">{{ Duration::decimal((int) $project->tracked) }}h</td>
                        <td class="hidden px-3 py-2.5 text-right tabular-nums text-muted md:table-cell">{{ $project->billable || $project->amount > 0 ? Money::format((int) round((float) $project->amount), $currency) : '—' }}</td>
                        <td class="hidden w-36 px-3 py-2.5 lg:table-cell">
                            @if ($progress !== null)
                                <div class="flex items-center gap-2">
                                    <div class="h-1.5 flex-1 rounded-full bg-line-soft"><div class="h-1.5 rounded-full {{ $progress >= 100 ? 'bg-danger' : 'bg-primary' }}" style="width: {{ $progress }}%"></div></div>
                                    <span class="text-xs text-muted">{{ $progress }}%</span>
                                </div>
                            @else
                                <span class="text-muted">—</span>
                            @endif
                        </td>
                        <td class="hidden px-3 py-2.5 md:table-cell">
                            @if ($project->is_public)
                                <span class="text-muted">Public</span>
                            @else
                                <div class="flex -space-x-1.5">
                                    @foreach ($project->members->take(4) as $member)<x-avatar :user="$member" size="size-6 text-[9px] ring-2 ring-surface" />@endforeach
                                    @if ($project->members->count() > 4)<span class="flex size-6 items-center justify-center rounded-full bg-canvas text-[10px] ring-2 ring-surface">+{{ $project->members->count() - 4 }}</span>@endif
                                </div>
                            @endif
                        </td>
                        <td class="pr-2 text-right">
                            @if ($this->isAdmin)
                                <div x-data="dropdown" class="relative inline-block" x-on:click.outside="close()">
                                    <button type="button" class="btn-icon" x-on:click="toggle()" aria-label="Actions"><x-icon name="dots" /></button>
                                    <div x-show="open" x-cloak class="dropdown-panel right-0 w-40 text-left">
                                        <button type="button" class="dropdown-item" x-on:click="close(); $dispatch('open-project-form', { id: {{ $project->id }}, origin: 'projects' })"><x-icon name="pencil" class="size-4 text-muted" /> Edit</button>
                                        @if ($project->archived)
                                            <button type="button" class="dropdown-item" wire:click="archive({{ $project->id }}, false)" x-on:click="close()"><x-icon name="restore" class="size-4 text-muted" /> Restore</button>
                                            <button type="button" class="dropdown-item text-danger" wire:click="delete({{ $project->id }})" wire:confirm="Delete this project? Its time entries will remain without a project." x-on:click="close()"><x-icon name="trash" class="size-4" /> Delete</button>
                                        @else
                                            <button type="button" class="dropdown-item" wire:click="archive({{ $project->id }})" x-on:click="close()"><x-icon name="archive" class="size-4 text-muted" /> Archive</button>
                                        @endif
                                    </div>
                                </div>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="8"><x-empty-state icon="projects" title="No projects found" text="Create a project to organise your time entries." /></td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="mt-4">{{ $projects->links() }}</div>

    <livewire:project-form />
</div>
