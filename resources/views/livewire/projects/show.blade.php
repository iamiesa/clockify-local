@php use App\Support\Duration; use App\Support\Money; $currency = $this->workspace->currency; @endphp
<div>
    <a href="{{ route('projects.index') }}" wire:navigate class="mb-3 inline-flex items-center gap-1 text-sm text-muted hover:text-ink"><x-icon name="chevron-left" class="size-4" /> Projects</a>

    <div class="mb-4 flex flex-wrap items-center gap-3">
        <span class="size-4 rounded-full" style="background: {{ $project->color }}"></span>
        <h1 class="page-title">{{ $project->name }}</h1>
        @if ($project->client)<span class="text-muted">· {{ $project->client->name }}</span>@endif
        @if ($project->archived)<span class="chip bg-canvas text-muted">Archived</span>@endif
        @if ($this->isAdmin)
            <button type="button" class="btn-secondary ml-auto" x-on:click="$dispatch('open-project-form', { id: {{ $project->id }}, origin: 'project' })"><x-icon name="pencil" class="size-4" /> Edit</button>
        @endif
    </div>

    <nav class="mb-4 flex overflow-x-auto border-b border-line">
        @foreach (['tasks' => 'Tasks', 'access' => 'Access', 'status' => 'Status', 'note' => 'Note'] as $key => $label)
            <button type="button" wire:click="$set('tab', '{{ $key }}')" @class(['tab', 'tab-active' => $tab === $key])>{{ $label }}</button>
        @endforeach
    </nav>

    @if ($tab === 'tasks')
        @if ($this->canManage)
            <form wire:submit="addTask" class="card mb-4 flex flex-wrap items-start gap-2 p-3">
                <div class="min-w-48 flex-1">
                    <input wire:model="newTask" class="input" placeholder="Add new task">
                    @error('newTask')<p class="error">{{ $message }}</p>@enderror
                </div>
                <input wire:model="newTaskEstimate" type="number" min="0" class="input w-36" placeholder="Estimate (h)">
                <button class="btn-primary">Add</button>
            </form>
        @endif
        <div class="card overflow-x-auto">
            <table class="w-full text-sm">
                <thead><tr class="table-head border-b border-line-soft text-left"><th class="px-4 py-2">Name</th><th class="px-4 py-2 text-right">Tracked</th><th class="hidden sm:table-cell px-4 py-2 text-right">Estimate</th><th class="w-24"></th></tr></thead>
                <tbody class="divide-y divide-line-soft">
                    @forelse ($tasks as $task)
                        <tr wire:key="task-{{ $task->id }}-{{ $task->updated_at?->getTimestamp() }}">
                            <td class="px-4 py-2">
                                @if ($this->canManage)
                                    <input value="{{ $task->name }}" x-on:change="$wire.renameTask({{ $task->id }}, $event.target.value)" x-on:keydown.enter="$event.target.blur()"
                                           @class(['h-8 w-full rounded-sm border border-transparent px-2 hover:border-line focus:border-primary focus:outline-none', 'line-through text-muted' => $task->done])>
                                @else
                                    <span @class(['line-through text-muted' => $task->done])>{{ $task->name }}</span>
                                @endif
                            </td>
                            <td class="whitespace-nowrap px-4 py-2 text-right tabular-nums">{{ Duration::format((int) $task->tracked) }}</td>
                            <td class="hidden sm:table-cell px-4 py-2 text-right text-muted">{{ $task->estimate_hours ? $task->estimate_hours.'h' : '—' }}</td>
                            <td class="px-2 py-2 text-right whitespace-nowrap">
                                @if ($this->canManage)
                                    <button type="button" class="btn-icon" wire:click="toggleTaskDone({{ $task->id }})" title="{{ $task->done ? 'Mark active' : 'Mark done' }}"><x-icon :name="$task->done ? 'restore' : 'check'" class="size-4" /></button>
                                    <button type="button" class="btn-icon" wire:click="deleteTask({{ $task->id }})" wire:confirm="Delete this task?" title="Delete"><x-icon name="trash" class="size-4" /></button>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="px-4 py-8 text-center text-muted">No tasks yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    @elseif ($tab === 'access')
        <div class="card mb-4 p-4">
            <p class="label">Visibility</p>
            <div class="flex flex-wrap gap-4 text-sm">
                <label class="flex items-center gap-2"><input type="radio" name="vis" class="accent-primary" @checked($project->is_public) @disabled(! $this->canManage) wire:click="setVisibility(true)"> <x-icon name="globe" class="size-4 text-muted" /> Public — everyone in the workspace</label>
                <label class="flex items-center gap-2"><input type="radio" name="vis" class="accent-primary" @checked(! $project->is_public) @disabled(! $this->canManage) wire:click="setVisibility(false)"> <x-icon name="lock" class="size-4 text-muted" /> Private — members below & admins</label>
            </div>
        </div>
        <div class="card overflow-x-auto">
            <div class="card-header">
                <span>Project members</span>
                @if ($this->canManage && $candidates->isNotEmpty())
                    <form wire:submit="addMember" class="flex items-center gap-2">
                        <select wire:model="memberToAdd" class="input h-8 w-48 py-0" required>
                            <option value="">Add member…</option>
                            @foreach ($candidates as $candidate)<option value="{{ $candidate->id }}" wire:key="cand-{{ $candidate->id }}">{{ $candidate->name }}</option>@endforeach
                        </select>
                        <button class="btn-primary h-8">Add</button>
                    </form>
                @endif
            </div>
            <table class="w-full text-sm">
                <thead><tr class="table-head border-b border-line-soft text-left"><th class="px-4 py-2">Name</th><th class="px-4 py-2">Project rate ({{ $currency }})</th><th class="px-4 py-2">Manager</th><th class="w-12"></th></tr></thead>
                <tbody class="divide-y divide-line-soft">
                    @forelse ($members as $member)
                        <tr wire:key="pm-{{ $member->id }}">
                            <td class="px-4 py-2"><div class="flex min-w-0 items-center gap-2"><x-avatar :user="$member" size="size-7 text-[10px]" /> <span class="truncate">{{ $member->name }}</span></div></td>
                            <td class="px-4 py-2">
                                <input type="number" step="0.01" min="0" value="{{ Money::fromCents($member->pivot->hourly_rate) }}" placeholder="Default" @disabled(! $this->isAdmin)
                                       x-on:change="$wire.setMemberRate({{ $member->id }}, $event.target.value)" class="input h-8 w-24 sm:w-32">
                            </td>
                            <td class="px-4 py-2"><input type="checkbox" class="accent-primary" @checked($member->pivot->is_manager) @disabled(! $this->isAdmin) wire:click="toggleManager({{ $member->id }})"></td>
                            <td class="pr-2 text-right">@if ($this->canManage)<button type="button" class="btn-icon" wire:click="removeMember({{ $member->id }})" title="Remove"><x-icon name="x" class="size-4" /></button>@endif</td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="px-4 py-8 text-center text-muted">No members. {{ $project->is_public ? 'Public projects are available to everyone.' : '' }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    @elseif ($tab === 'status')
        @php $estimate = $project->estimate_hours ? $project->estimate_hours * 3600 : null; @endphp
        <div class="mb-4 grid grid-cols-1 gap-px overflow-hidden rounded-sm border border-line bg-line sm:grid-cols-3">
            <div class="bg-surface p-4"><p class="text-xs uppercase tracking-wide text-muted">Tracked</p><p class="mt-1 text-2xl tabular-nums">{{ Duration::format((int) $totals->tracked) }}</p></div>
            <div class="bg-surface p-4"><p class="text-xs uppercase tracking-wide text-muted">Billable</p><p class="mt-1 text-2xl tabular-nums">{{ Duration::format((int) $totals->billable) }}</p><p class="text-sm text-muted whitespace-nowrap">{{ Money::format((int) round((float) $totals->amount / 3600), $currency) }}</p></div>
            <div class="bg-surface p-4">
                <p class="text-xs uppercase tracking-wide text-muted">Estimate</p>
                @if ($estimate)
                    @php $pct = round($totals->tracked / $estimate * 100); @endphp
                    <p class="mt-1 text-2xl">{{ $project->estimate_hours }}h <span class="text-sm text-muted">({{ $pct }}% used)</span></p>
                    <div class="mt-2 h-2 rounded-full bg-line-soft"><div class="h-2 rounded-full {{ $pct >= 100 ? 'bg-danger' : 'bg-primary' }}" style="width: {{ min(100, $pct) }}%"></div></div>
                @else
                    <p class="mt-1 text-2xl text-muted">—</p>
                @endif
            </div>
        </div>
        <div class="card">
            <div class="card-header">Tracked by member</div>
            <ul class="divide-y divide-line-soft">
                @forelse ($byUser as $row)
                    <li wire:key="bu-{{ $row->id }}" class="flex items-center gap-3 px-4 py-2.5 text-sm">
                        <span class="flex size-7 items-center justify-center rounded-full text-[10px] text-white" style="background: {{ $row->avatar_color }}">{{ mb_strtoupper(mb_substr($row->name, 0, 1)) }}</span>
                        <span class="flex-1">{{ $row->name }}</span>
                        <span class="tabular-nums">{{ Duration::format((int) $row->tracked) }}</span>
                    </li>
                @empty
                    <li class="px-4 py-8 text-center text-sm text-muted">No time tracked on this project yet.</li>
                @endforelse
            </ul>
        </div>
    @else
        <div class="card p-4">
            <textarea wire:model="note" rows="8" class="input" placeholder="Add a note about this project" @disabled(! $this->canManage)></textarea>
            @error('note')<p class="error">{{ $message }}</p>@enderror
            @if ($this->canManage)<div class="mt-3 text-right"><button type="button" wire:click="saveNote" class="btn-primary">Save note</button></div>@endif
        </div>
    @endif

    <livewire:project-form />
</div>
