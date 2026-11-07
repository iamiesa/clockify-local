@props(['project' => null, 'task' => null, 'origin' => null, 'compact' => false])
{{-- $project: App\Models\Project|null. Emits `project-picked`; the parent wires it to a Livewire call. --}}
<div x-data="projectPicker" class="relative min-w-0" x-on:click.outside="close()" x-on:keydown.escape="close()">
    <button type="button" x-on:click="toggle()" class="flex max-w-full items-center gap-1.5 rounded-sm px-2 h-9 text-sm hover:bg-canvas cursor-pointer min-w-0">
        @if ($project)
            <span class="size-2 shrink-0 rounded-full" style="background: {{ $project->color }}"></span>
            <span class="truncate" style="color: {{ $project->color }}">{{ $project->name }}@if ($task)<span class="text-ink">: {{ $task->name }}</span>@endif</span>
            @if ($project->client && ! $compact)<span class="hidden xl:inline truncate text-muted">· {{ $project->client->name }}</span>@endif
        @else
            <span class="flex items-center gap-1 text-primary whitespace-nowrap"><x-icon name="plus" class="size-4" /> Project</span>
        @endif
    </button>

    <div x-show="open" x-cloak x-transition.origin.top class="dropdown-panel max-sm:fixed max-sm:inset-x-3 max-sm:w-auto sm:right-0 sm:w-[22rem]">
        <template x-if="open">
            <div>
                <div class="p-2 border-b border-line-soft">
                    <input x-ref="search" x-model="search" type="text" class="input h-8" placeholder="Find project or client…">
                </div>
                <div class="max-h-80 overflow-y-auto py-1">
                    @if ($project)
                        <button type="button" class="dropdown-item text-muted" x-on:click="pick(null)">No project</button>
                    @endif
                    <template x-for="group in groups" :key="group.key">
                        <div>
                            <p class="px-3 pt-2 pb-1 text-[11px] uppercase tracking-wide text-muted" x-text="group.label"></p>
                            <template x-for="p in group.projects" :key="group.key + p.id">
                                <div>
                                    <div class="flex items-center hover:bg-canvas">
                                        <button type="button" class="flex flex-1 items-center gap-2 px-3 py-2 text-left text-sm min-w-0 cursor-pointer" x-on:click="pick(p.id)">
                                            <span class="size-2 shrink-0 rounded-full" :style="`background:${p.color}`"></span>
                                            <span class="truncate" :style="`color:${p.color}`" x-text="p.name"></span>
                                        </button>
                                        <button type="button" x-show="p.tasks.length" class="px-3 py-2 text-xs text-muted hover:text-ink whitespace-nowrap cursor-pointer"
                                                x-on:click="expanded[p.id] = !expanded[p.id]" x-text="`${p.tasks.length} task${p.tasks.length > 1 ? 's' : ''}`"></button>
                                    </div>
                                    <template x-for="t in tasksFor(p)" :key="'t' + t.id">
                                        <button type="button" class="dropdown-item pl-8 text-muted" x-on:click="pick(p.id, t.id)" x-text="t.name"></button>
                                    </template>
                                </div>
                            </template>
                        </div>
                    </template>
                    <p x-show="!groups.length" class="px-3 py-3 text-sm text-muted">No matching projects.</p>
                </div>
                <div x-show="$store.catalog.canCreateProject" class="border-t border-line-soft">
                    <button type="button" class="dropdown-item text-primary" x-on:click="create(@js($origin))"><x-icon name="plus" class="size-4" /> Create new project</button>
                </div>
            </div>
        </template>
    </div>
</div>
