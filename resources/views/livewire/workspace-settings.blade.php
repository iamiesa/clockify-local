<div class="max-w-3xl">
    <h1 class="page-title mb-5">Workspace settings</h1>
    <form wire:submit="save" class="space-y-4">
        <div class="card p-5 space-y-4">
            <h2 class="text-sm uppercase tracking-wide text-muted">General</h2>
            <div><label class="label">Workspace name</label><input wire:model="name" class="input">@error('name')<p class="error">{{ $message }}</p>@enderror</div>
            <div class="grid gap-4 sm:grid-cols-2">
                <div>
                    <label class="label">Currency</label>
                    <select wire:model="currency" class="input">@foreach ($currencies as $c)<option value="{{ $c }}">{{ $c }}</option>@endforeach</select>
                    @error('currency')<p class="error">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label class="label">Default billable rate (per hour)</label>
                    <input type="number" min="0" step="0.01" wire:model="hourlyRate" class="input" placeholder="0.00">
                    @error('hourlyRate')<p class="error">{{ $message }}</p>@enderror
                </div>
            </div>
            <p class="text-xs text-muted">Rate precedence: project member rate → project rate → member rate (Team page) → this default.</p>
        </div>

        <div class="card p-5 space-y-4">
            <h2 class="text-sm uppercase tracking-wide text-muted">Time tracking</h2>
            <x-toggle wire:model="billableByDefault" label="New entries are billable by default" description="Applies to entries without a project; projects have their own default." />
            <x-toggle wire:model="forceProject" label="Require a project on time entries" description="Timers can't be stopped and entries can't be saved without a project." />
        </div>

        <div class="card p-5 space-y-4">
            <h2 class="text-sm uppercase tracking-wide text-muted">Permissions</h2>
            <x-toggle wire:model="membersCanCreateProjects" label="Members can create projects and clients" />
            <x-toggle wire:model="membersCanCreateTags" label="Members can create tags" />
            <x-toggle wire:model="membersSeeTeamReports" label="Members can see team dashboard & reports" description="Otherwise members only see their own data." />
        </div>

        <div class="text-right"><button class="btn-primary" wire:loading.attr="disabled">Save changes</button></div>
    </form>
</div>
