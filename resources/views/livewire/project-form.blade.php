<div>
    <x-modal name="project-form" :title="$projectId ? 'Edit project' : 'Create new project'">
        <form wire:submit="save" id="project-form" class="space-y-4">
            <div>
                <label class="label" for="pf-name">Project name</label>
                <input id="pf-name" wire:model="name" class="input" placeholder="Enter project name">
                @error('name')<p class="error">{{ $message }}</p>@enderror
            </div>
            <div class="grid gap-4 sm:grid-cols-2">
                <div>
                    <label class="label" for="pf-client">Client</label>
                    <select id="pf-client" wire:model="clientId" class="input">
                        <option value="">No client</option>
                        @foreach ($clients as $client)
                            <option value="{{ $client->id }}" wire:key="pfc-{{ $client->id }}">{{ $client->name }}</option>
                        @endforeach
                    </select>
                    @error('clientId')<p class="error">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label class="label" for="pf-new-client">…or create client</label>
                    <input id="pf-new-client" wire:model="newClient" class="input" placeholder="New client name">
                    @error('newClient')<p class="error">{{ $message }}</p>@enderror
                </div>
            </div>
            <div>
                <span class="label">Color</span>
                <div class="flex flex-wrap gap-2">
                    @foreach ($colors as $c)
                        <button type="button" wire:key="pf-color-{{ $c }}" wire:click="$set('color', '{{ $c }}')" aria-label="Color {{ $c }}"
                                @class(['size-7 rounded-full cursor-pointer ring-offset-2', 'ring-2 ring-ink' => $color === $c]) style="background: {{ $c }}"></button>
                    @endforeach
                </div>
            </div>
            <div class="grid gap-4 sm:grid-cols-2">
                <div>
                    <label class="label" for="pf-rate">Hourly rate ({{ $this->workspace->currency }})</label>
                    <input id="pf-rate" type="number" step="0.01" min="0" wire:model="hourlyRate" class="input" placeholder="Default">
                    @error('hourlyRate')<p class="error">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label class="label" for="pf-est">Time estimate (hours)</label>
                    <input id="pf-est" type="number" min="0" wire:model="estimateHours" class="input" placeholder="None">
                    @error('estimateHours')<p class="error">{{ $message }}</p>@enderror
                </div>
            </div>
            <div class="flex flex-col gap-3 sm:flex-row sm:gap-8">
                @unless (config('tracker.shared_workspace'))
                    <x-toggle wire:model="isPublic" label="Public" description="Everyone in the workspace can track on it" />
                @endunless
                <x-toggle wire:model="billable" label="Billable" description="New entries are billable by default" />
            </div>
        </form>
        <x-slot:footer>
            <button type="button" class="btn-secondary" x-on:click="$dispatch('close-modal', 'project-form')">Cancel</button>
            <button type="submit" form="project-form" class="btn-primary" wire:loading.attr="disabled">{{ $projectId ? 'Save' : 'Create' }}</button>
        </x-slot:footer>
    </x-modal>
</div>
