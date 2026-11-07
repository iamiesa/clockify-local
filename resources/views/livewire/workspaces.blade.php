<div>
    <h1 class="page-title mb-5">Workspaces</h1>

    @if (config('tracker.shared_workspace'))
        <p class="mb-4 text-sm text-muted">Everyone in your organisation works in one shared workspace.</p>
    @else
    <form wire:submit="create" class="card mb-4 flex flex-wrap items-start gap-2 p-3">
        <div class="min-w-48 flex-1">
            <input wire:model="name" class="input" placeholder="New workspace name (e.g. your company or organization)">
            @error('name')<p class="error">{{ $message }}</p>@enderror
        </div>
        <button class="btn-primary">Create workspace</button>
    </form>
    @endif

    <div class="card overflow-x-auto">
        <div class="card-header">Your workspaces</div>
        <table class="w-full text-sm">
            <thead><tr class="table-head border-b border-line-soft text-left"><th class="px-4 py-2">Name</th><th class="px-4 py-2">Role</th><th class="hidden sm:table-cell px-4 py-2">Members</th><th class="px-4 py-2"></th></tr></thead>
            <tbody class="divide-y divide-line-soft">
                @foreach ($memberships as $m)
                    @php $current = $m->workspace_id === $this->workspace->id; @endphp
                    <tr wire:key="ws-{{ $m->workspace_id }}">
                        <td class="px-4 py-2.5">{{ $m->workspace->name }} @if ($current)<span class="chip ml-1 bg-primary-soft text-primary-dark">Current</span>@endif</td>
                        <td class="px-4 py-2.5 text-muted">{{ $m->role->label() }}</td>
                        <td class="hidden sm:table-cell px-4 py-2.5 text-muted">{{ $memberCounts[$m->workspace_id] ?? 1 }}</td>
                        <td class="px-4 py-2.5 text-right whitespace-nowrap">
                            @unless ($current)
                                <form method="POST" action="{{ route('workspaces.switch', $m->workspace_id) }}" class="inline">@csrf<button class="btn-link">Switch</button></form>
                            @endunless
                            @if ($m->role !== \App\Enums\Role::Owner && ! config('tracker.shared_workspace'))
                                <button type="button" class="btn-link ml-3 text-danger!" wire:click="leave({{ $m->workspace_id }})" wire:confirm="Leave {{ $m->workspace->name }}?">Leave</button>
                            @endif
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>
