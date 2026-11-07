@php
    use App\Support\Duration;
    $running = $this->running;
    $catalog = $this->catalog;
    $tagNames = collect($catalog['tags'])->pluck('name', 'id');
    $formProject = $projectId ? \App\Models\Project::with('client')->find($projectId) : null;
    $formTask = $taskId ? \App\Models\Task::find($taskId) : null;
@endphp
<div x-data="{ selected: [] }" x-init="Object.assign($store.catalog, @js($catalog))">
    {{-- ================================================================ Timer bar --}}
    <div class="card mb-6 shadow-sm">
        {{-- Mobile: [description | project] / [tags | $ | timer | start]. Desktop (sm+): one line. --}}
        <div class="flex flex-wrap items-center sm:flex-nowrap sm:pr-2">
            <input type="text" wire:model.blur="description"
                   @if (! $running) wire:keydown.enter="{{ $mode === 'timer' ? 'startTimer' : 'addManualEntry' }}" @endif
                   placeholder="{{ $running ? 'Add description' : 'What are you working on?' }}"
                   class="order-1 h-14 min-w-0 flex-1 text-ellipsis border-0 bg-transparent pl-4 pr-2 text-base text-ink placeholder:text-muted focus:outline-none">

            <div class="order-2 flex h-9 max-w-[48%] shrink-0 items-center pr-2 sm:max-w-[16rem] sm:px-1" wire:key="form-project-{{ $projectId }}-{{ $taskId }}" x-on:project-picked="$wire.setFormProject($event.detail.projectId, $event.detail.taskId)">
                <x-project-picker :project="$formProject" :task="$formTask" origin="tracker" />
            </div>

            <div class="order-3 basis-full border-t border-line-soft sm:hidden"></div>

            <div class="order-4 flex h-12 shrink-0 items-center px-1 sm:h-9 sm:border-l sm:border-line-soft sm:px-1" wire:key="form-tags-{{ implode('-', $tagIds) }}-{{ $renderKey }}" x-on:tags-picked="$wire.setFormTags($event.detail.ids)">
                <x-tag-picker :selected="$tagIds" :names="collect($tagIds)->map(fn ($id) => $tagNames[$id] ?? null)->filter()->all()" />
            </div>

            <div class="order-5 flex h-12 shrink-0 items-center border-l border-line-soft px-1 sm:h-9">
                <button type="button" wire:click="$toggle('billable')" title="{{ $billable ? 'Billable' : 'Non-billable' }}"
                        @class(['btn-icon size-9', 'text-primary!' => $billable])>
                    <x-icon name="dollar" class="size-5" />
                </button>
            </div>

            <div @class([
                'order-6 flex items-center justify-end gap-x-1.5 border-line-soft py-1.5 pr-1 sm:ml-0 sm:h-9 sm:flex-none sm:shrink-0 sm:gap-x-3 sm:border-l sm:border-t-0 sm:py-0 sm:pl-4 sm:pr-0',
                'min-w-0 flex-1 border-l pl-2' => $mode === 'timer',
                'w-full flex-wrap border-t pl-2 sm:w-auto sm:flex-nowrap' => $mode !== 'timer',
            ])>
                    @if ($mode === 'timer')
                        @if ($running)
                            <div x-data="{ edit: false }" class="relative" wire:key="run-{{ $running->id }}-{{ $running->start->getTimestamp() }}">
                                <button type="button" x-on:click="edit = !edit" title="Edit start time"
                                        x-data="timer({{ $running->start->getTimestampMs() }}, {{ now()->getTimestampMs() }})"
                                        class="whitespace-nowrap text-right text-lg font-medium tabular-nums text-ink cursor-pointer sm:w-24" x-text="elapsed">0:00:00</button>
                                <div x-show="edit" x-cloak x-on:click.outside="edit = false" class="dropdown-panel right-0 w-56 p-3">
                                    <label class="label">Start time</label>
                                    <input type="text" class="input" value="{{ $running->start->setTimezone($timezone)->format($timeFormat) }}"
                                           x-on:keydown.enter="$wire.updateRunningStart($event.target.value); edit = false"
                                           x-on:change="$wire.updateRunningStart($event.target.value); edit = false">
                                    <p class="mt-1 text-xs text-muted">Started {{ $running->start->setTimezone($timezone)->format('D, M j') }}</p>
                                </div>
                            </div>
                            <button type="button" wire:click="stopTimer" class="btn w-[4.5rem] shrink-0 bg-danger px-2 text-white hover:bg-red-700 sm:w-24" wire:loading.attr="disabled" wire:target="stopTimer">Stop</button>
                            <div x-data="dropdown" class="relative -ml-2 hidden sm:block" x-on:click.outside="close()">
                                <button type="button" class="btn-icon" x-on:click="toggle()" aria-label="Timer options"><x-icon name="dots" /></button>
                                <div x-show="open" x-cloak class="dropdown-panel right-0 w-40">
                                    <button type="button" class="dropdown-item text-danger" wire:click="discardTimer" wire:confirm="Discard the running timer?" x-on:click="close()">Discard</button>
                                </div>
                            </div>
                        @else
                            <span class="whitespace-nowrap text-right text-lg font-medium tabular-nums text-ink sm:w-24">0:00:00</span>
                            <button type="button" wire:click="startTimer" class="btn-primary w-[4.5rem] shrink-0 px-2 sm:w-24" wire:loading.attr="disabled" wire:target="startTimer">Start</button>
                        @endif
                    @else
                        <div class="flex basis-full items-center gap-1 sm:basis-auto">
                            <input type="text" wire:model="manualStart" class="input h-8 w-[4.25rem] px-1 text-center" aria-label="Start time">
                            <span class="text-muted">-</span>
                            <input type="text" wire:model="manualEnd" class="input h-8 w-[4.25rem] px-1 text-center" aria-label="End time">
                            <input type="text" placeholder="0:00" class="input ml-auto h-8 w-20 px-1 text-center font-medium sm:ml-0 sm:w-16" aria-label="Duration"
                                   x-on:change="$wire.setManualDuration($event.target.value)">
                        </div>
                        <input type="date" wire:model="manualDate" class="input h-8 min-w-0 flex-1 px-1 text-xs sm:w-auto sm:flex-none" aria-label="Date">
                        <button type="button" wire:click="addManualEntry" class="btn-primary w-20 sm:w-24" wire:loading.attr="disabled" wire:target="addManualEntry">Add</button>
                    @endif

                    {{-- Mobile: one ⋮ menu for mode + discard. Desktop: inline toggle. --}}
                    <div x-data="dropdown" class="relative shrink-0 sm:hidden" x-on:click.outside="close()">
                        <button type="button" class="btn-icon" x-on:click="toggle()" aria-label="Timer options"><x-icon name="dots" /></button>
                        <div x-show="open" x-cloak x-transition class="dropdown-panel right-0 w-48">
                            <button type="button" wire:click="$set('mode', 'timer')" x-on:click="close()" @class(['dropdown-item', 'text-primary' => $mode === 'timer'])><x-icon name="clock" class="size-4" /> Timer mode</button>
                            <button type="button" wire:click="$set('mode', 'manual')" x-on:click="close()" @class(['dropdown-item', 'text-primary' => $mode === 'manual'])><x-icon name="list" class="size-4" /> Manual mode</button>
                            @if ($running && $mode === 'timer')
                                <button type="button" class="dropdown-item border-t border-line-soft text-danger" wire:click="discardTimer" wire:confirm="Discard the running timer?" x-on:click="close()"><x-icon name="trash" class="size-4" /> Discard timer</button>
                            @endif
                        </div>
                    </div>
                    <div class="hidden flex-col border-l border-line-soft pl-2 sm:flex">
                        <button type="button" wire:click="$set('mode', 'timer')" title="Timer mode" @class(['p-0.5 cursor-pointer', 'text-primary' => $mode === 'timer', 'text-muted hover:text-ink' => $mode !== 'timer'])><x-icon name="clock" class="size-4" /></button>
                        <button type="button" wire:click="$set('mode', 'manual')" title="Manual mode" @class(['p-0.5 cursor-pointer', 'text-primary' => $mode === 'manual', 'text-muted hover:text-ink' => $mode !== 'manual'])><x-icon name="list" class="size-4" /></button>
                    </div>
            </div>
        </div>
    </div>

    {{-- ================================================================ Bulk bar --}}
    <div x-show="selected.length" x-cloak x-transition class="sticky top-16 z-20 mb-3 flex items-center justify-between gap-3 rounded-sm bg-[#333] px-4 py-2 text-sm text-white dark:bg-[#0f1417] shadow">
        <span><span x-text="selected.length"></span> selected</span>
        <div class="flex items-center gap-3">
            <button type="button" class="hover:underline cursor-pointer" x-on:click="selected = []">Clear</button>
            <button type="button" class="btn-danger h-8" x-on:click="if (confirm(`Delete ${selected.length} time entries?`)) $wire.deleteEntries(selected).then(() => selected = [])">Delete</button>
        </div>
    </div>

    {{-- ================================================================ Entries --}}
    @php $timeline = $this->timeline; @endphp
    @forelse ($timeline['weeks'] as $week)
        <section wire:key="week-{{ $week['key'] }}" class="mb-6">
            <div class="mb-2 flex flex-col gap-1 px-1 text-sm text-muted sm:flex-row sm:items-center sm:justify-between">
                <span class="text-base text-ink sm:text-sm sm:text-muted">{{ $week['label'] }}</span>
                <span>Week total: <span class="ml-1 text-xl font-medium text-ink tabular-nums sm:ml-0 sm:text-sm">{{ Duration::format($week['total']) }}</span></span>
            </div>

            @foreach ($week['days'] as $day)
                @php $dayIds = $day['entries']->pluck('id')->all(); @endphp
                <div wire:key="day-{{ $day['key'] }}" class="card mb-3">
                    <div class="card-header">
                        <label class="flex items-center gap-3">
                            <input type="checkbox" class="accent-primary"
                                   :checked="@js($dayIds).every(id => selected.includes(id))"
                                   x-on:change="$event.target.checked ? selected = [...new Set([...selected, ...@js($dayIds)])] : selected = selected.filter(id => !@js($dayIds).includes(id))">
                            <span>{{ $day['label'] }}</span>
                        </label>
                        <span><span class="hidden sm:inline">Total: </span><span class="text-lg font-medium text-ink tabular-nums sm:text-sm">{{ Duration::format($day['total']) }}</span></span>
                    </div>

                    <ul class="divide-y divide-line-soft">
                        @foreach ($day['entries'] as $entry)
                            @php
                                $start = $entry->start->setTimezone($timezone);
                                $end = $entry->end->setTimezone($timezone);
                                $extraDays = (int) $start->startOfDay()->diffInDays($end->startOfDay());
                                $tagIdsOfEntry = $entry->tags->pluck('id')->all();
                            @endphp
                            {{-- Mobile: 3 rows (description+duration / project+tags+$ / times+actions). Desktop (sm+): one line. --}}
                            <li wire:key="entry-{{ $entry->id }}-{{ $entry->updated_at?->getTimestamp() }}-{{ $renderKey }}"
                                class="flex flex-wrap items-center gap-x-1 px-2 pt-2 pb-1 sm:flex-nowrap sm:py-1.5"
                                x-data="{ id: {{ $entry->id }} }"
                                x-on:project-picked="$wire.updateEntryProject(id, $event.detail.projectId, $event.detail.taskId)"
                                x-on:tags-picked="$wire.updateEntry(id, 'tags', $event.detail.ids)">
                                <input type="checkbox" value="{{ $entry->id }}" x-model.number="selected" class="order-1 mx-1 accent-primary" aria-label="Select entry">

                                <input type="text" value="{{ $entry->description }}" placeholder="Add description"
                                       x-on:change="$wire.updateEntry(id, 'description', $event.target.value)"
                                       x-on:keydown.enter="$event.target.blur()"
                                       class="order-2 h-9 min-w-0 flex-1 rounded-sm border border-transparent px-2 text-sm text-ink placeholder:text-muted hover:border-line focus:border-primary focus:outline-none">

                                <input type="text" value="{{ Duration::format($entry->duration) }}" aria-label="Duration"
                                       x-on:change="$wire.updateEntryDuration(id, $event.target.value)" x-on:keydown.enter="$event.target.blur()"
                                       class="order-3 h-9 w-[5rem] rounded-none border-0 border-l border-line-soft px-1 text-right text-base font-medium tabular-nums text-ink focus:outline-none focus:ring-1 focus:ring-primary sm:order-7 sm:w-20 sm:rounded-sm sm:border sm:border-transparent sm:text-sm sm:hover:border-line sm:focus:ring-0 sm:focus:border-primary">

                                <div class="order-4 basis-full sm:hidden"></div>

                                <div class="order-5 flex min-w-0 flex-1 items-center pl-5 sm:order-3 sm:max-w-[40%] sm:flex-none sm:pl-0">
                                    <x-project-picker :project="$entry->project" :task="$entry->task" :compact="true" />
                                </div>

                                <div class="order-6 flex shrink-0 items-center sm:order-4 sm:border-l sm:border-line-soft">
                                    <x-tag-picker :selected="$tagIdsOfEntry" :names="$entry->tags->pluck('name')->all()" />
                                </div>

                                <button type="button" wire:click="updateEntry({{ $entry->id }}, 'billable', {{ $entry->billable ? 'false' : 'true' }})" title="{{ $entry->billable ? 'Billable' : 'Non-billable' }}"
                                        @class(['order-7 btn-icon shrink-0 sm:order-5', 'text-primary!' => $entry->billable])>
                                    <x-icon name="dollar" class="size-5" />
                                </button>

                                <div class="order-8 -mx-2 mt-1.5 basis-[calc(100%+1rem)] border-t border-line-soft sm:hidden"></div>

                                <div class="order-9 flex shrink-0 items-center gap-0.5 pt-1 sm:pt-0 sm:order-6 sm:gap-1 sm:border-l sm:border-line-soft sm:pl-2" x-data>
                                    <input type="text" x-ref="s" value="{{ $start->format($timeFormat) }}" aria-label="Start time"
                                           x-on:change="$wire.updateEntryTimes(id, $refs.s.value, $refs.e.value)" x-on:keydown.enter="$event.target.blur()"
                                           class="h-8 w-[4.25rem] rounded-sm border border-transparent text-center text-sm text-muted hover:border-line focus:border-primary focus:outline-none sm:text-ink">
                                    <span class="text-muted">-</span>
                                    <span class="relative">
                                        <input type="text" x-ref="e" value="{{ $end->format($timeFormat) }}" aria-label="End time"
                                               x-on:change="$wire.updateEntryTimes(id, $refs.s.value, $refs.e.value)" x-on:keydown.enter="$event.target.blur()"
                                               class="h-8 w-[4.25rem] rounded-sm border border-transparent text-center text-sm text-muted hover:border-line focus:border-primary focus:outline-none sm:text-ink">
                                        @if ($extraDays > 0)<sup class="absolute -right-1 top-0 text-[10px] text-primary">+{{ $extraDays }}</sup>@endif
                                    </span>
                                    <span class="relative">
                                        <button type="button" class="btn-icon" x-on:click="$refs.d.showPicker ? $refs.d.showPicker() : $refs.d.focus()" title="{{ $start->format('D, M j, Y') }}"><x-icon name="calendar" class="size-4" /></button>
                                        <input type="date" x-ref="d" value="{{ $start->format('Y-m-d') }}" class="pointer-events-none absolute inset-0 opacity-0" tabindex="-1"
                                               x-on:change="$wire.updateEntryDate(id, $event.target.value)">
                                    </span>
                                </div>

                                <div class="order-10 ml-auto flex shrink-0 items-center pt-1 sm:order-8 sm:ml-0 sm:pt-0">
                                    <button type="button" wire:click="continueEntry({{ $entry->id }})" class="btn-icon border-l border-line-soft rounded-none w-11 sm:w-8 sm:rounded-sm sm:border-0" title="Continue this activity"><x-icon name="play" class="size-4" /></button>
                                    <div x-data="dropdown" class="relative" x-on:click.outside="close()">
                                        <button type="button" class="btn-icon" x-on:click="toggle()" aria-label="More actions"><x-icon name="dots" /></button>
                                        <div x-show="open" x-cloak class="dropdown-panel right-0 w-40">
                                            <button type="button" class="dropdown-item" wire:click="duplicateEntry({{ $entry->id }})" x-on:click="close()"><x-icon name="copy" class="size-4 text-muted" /> Duplicate</button>
                                            <button type="button" class="dropdown-item text-danger" wire:click="deleteEntry({{ $entry->id }})" wire:confirm="Delete this time entry?" x-on:click="close()"><x-icon name="trash" class="size-4" /> Delete</button>
                                        </div>
                                    </div>
                                </div>
                            </li>
                        @endforeach
                    </ul>
                </div>
            @endforeach
        </section>
    @empty
        <x-empty-state icon="clock" title="Let's start tracking!" text="Type what you're working on above and hit Start, or switch to manual mode to add time you've already spent." />
    @endforelse

    @if ($timeline['hasMore'])
        <div class="text-center">
            <button type="button" wire:click="loadMore" class="btn-secondary" wire:loading.attr="disabled" wire:target="loadMore">
                <span wire:loading.remove wire:target="loadMore">Load more</span>
                <span wire:loading wire:target="loadMore">Loading…</span>
            </button>
        </div>
    @endif

    <livewire:project-form />
</div>
