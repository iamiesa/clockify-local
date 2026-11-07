@php use App\Support\Money; $currency = $this->workspace->currency; @endphp
<div>
    <div class="mb-4 flex flex-wrap items-center gap-3">
        <nav class="mr-auto flex border-b border-line">
            @foreach (['summary' => 'Summary', 'detailed' => 'Detailed', 'weekly' => 'Weekly'] as $key => $label)
                <a href="{{ route('reports', $key) }}?{{ http_build_query(['range' => $preset, 'from' => $from, 'to' => $to]) }}" wire:navigate
                   @class(['tab', 'tab-active' => $tab === $key])>{{ $label }}</a>
            @endforeach
        </nav>
        <x-date-range :label="$this->rangeLabel()" :preset="$preset" />
    </div>

    {{-- Filter bar --}}
    <div class="card mb-4 flex flex-wrap items-center gap-x-1 gap-y-1 px-2 py-1">
        <span class="px-2 text-xs uppercase tracking-wide text-muted">Filter</span>
        @if ($canSeeTeam)
            <x-filter-select label="Team" model="userIds" :options="$filterOptions['users']" :selected="$userIds" />
            @if ($filterOptions['groups'])
                <x-filter-select label="Groups" model="groupIds" :options="$filterOptions['groups']" :selected="$groupIds" />
            @endif
        @endif
        <x-filter-select label="Client" model="clientIds" :options="$filterOptions['clients']" :selected="$clientIds" with-none="Without client" />
        <x-filter-select label="Project" model="projectIds" :options="$filterOptions['projects']" :selected="$projectIds" with-none="Without project" />
        <x-filter-select label="Tag" model="tagIds" :options="$filterOptions['tags']" :selected="$tagIds" with-none="Without tag" />
        <select wire:model.live="billable" @class(['h-9 [field-sizing:content] bg-transparent px-2 text-sm uppercase tracking-wide cursor-pointer focus:outline-none', 'text-primary' => $billable !== '', 'text-muted' => $billable === '']) aria-label="Billable filter">
            <option value="">Billable</option>
            <option value="1">Billable only</option>
            <option value="0">Non-billable only</option>
        </select>
        <div class="relative flex-1 min-w-40">
            <x-icon name="search" class="absolute left-2 top-2.5 size-4 text-muted" />
            <input type="text" wire:model.live.debounce.400ms="description" placeholder="Description" class="h-9 w-full bg-transparent pl-8 pr-2 text-sm focus:outline-none">
        </div>
        @if ($userIds || $groupIds || $clientIds || $projectIds || $tagIds || $billable !== '' || $description !== '')
            <button type="button" wire:click="clearFilters" class="btn-link px-2">Clear filters</button>
        @endif
    </div>

    {{-- Totals bar --}}
    <div class="mb-4 flex flex-wrap items-center gap-x-6 gap-y-2 text-sm">
        <span class="text-muted">Total: <strong class="text-lg font-medium text-ink tabular-nums">{{ $this->fmt($totals['duration']) }}</strong></span>
        <span class="text-muted">Billable: <strong class="font-medium text-ink tabular-nums">{{ $this->fmt($totals['billable']) }}</strong></span>
        <span class="text-muted">Amount: <strong class="font-medium text-ink tabular-nums whitespace-nowrap">{{ Money::format($totals['amount'], $currency) }}</strong></span>
        <div class="ml-auto flex items-center gap-3">
            <label class="flex items-center gap-2 text-muted"><input type="checkbox" wire:model.live="decimal" class="accent-primary"> Decimal</label>
            <button type="button" wire:click="export" class="btn-secondary h-8"><x-icon name="download" class="size-4" /> Export CSV</button>
        </div>
    </div>

    <div wire:loading.class="opacity-60" class="transition-opacity">
        @if ($tab === 'summary')
            <div class="card mb-4 p-4">
                @if ($totals['duration'] > 0)
                    <div class="h-64" wire:key="rbar-{{ md5(json_encode($barChart)) }}" x-data="chart(@js($barChart))"><canvas x-ref="canvas"></canvas></div>
                @else
                    <x-empty-state icon="reports" title="No data for this report" text="Adjust the date range or filters." />
                @endif
            </div>

            @if ($groups)
                <div class="grid gap-4 lg:grid-cols-[1fr_18rem]">
                    <div class="card overflow-x-auto">
                        <div class="card-header flex-wrap">
                            <div class="flex items-center gap-2">
                                <span>Group by</span>
                                <select wire:model.live="groupBy" class="input h-8 w-auto py-0">
                                    @foreach ($dimensions as $key => $label)<option value="{{ $key }}">{{ $label }}</option>@endforeach
                                </select>
                                <select wire:model.live="subGroupBy" class="input h-8 w-auto py-0">
                                    <option value="">(none)</option>
                                    @foreach ($dimensions as $key => $label)<option value="{{ $key }}">{{ $label }}</option>@endforeach
                                </select>
                            </div>
                        </div>
                        <table class="w-full text-sm">
                            <thead><tr class="table-head border-b border-line-soft text-left"><th class="px-3 py-2 sm:px-4">Title</th><th class="px-3 py-2 text-right sm:px-4">Duration</th><th class="hidden sm:table-cell px-4 py-2 text-right">Amount</th></tr></thead>
                            @foreach ($groups as $group)
                                <tbody x-data="{ open: false }" wire:key="g-{{ $groupBy }}-{{ $group['key'] }}" class="border-b border-line-soft">
                                    <tr class="hover:bg-canvas/60 {{ $group['children'] ? 'cursor-pointer' : '' }}" x-on:click="open = !open">
                                        <td class="w-full max-w-0 px-3 py-2.5 sm:px-4">
                                            <div class="flex items-center gap-2 min-w-0">
                                                @if ($group['children'])
                                                    <span class="flex size-5 items-center justify-center rounded-sm border border-line text-xs text-muted" x-text="open ? '−' : '{{ count($group['children']) }}'"></span>
                                                @endif
                                                <span class="size-2.5 shrink-0 rounded-full" style="background: {{ $group['color'] }}"></span>
                                                <span class="truncate">{{ $group['label'] }}</span>
                                                @if ($group['sub'] && $groupBy === 'project')<span class="hidden truncate text-xs text-muted sm:inline">· {{ $group['sub'] }}</span>@endif
                                            </div>
                                        </td>
                                        <td class="whitespace-nowrap px-3 py-2.5 text-right tabular-nums sm:px-4">{{ $this->fmt($group['duration']) }}</td>
                                        <td class="hidden sm:table-cell px-4 py-2.5 text-right tabular-nums text-muted whitespace-nowrap">{{ Money::format($group['amount'], $currency) }}</td>
                                    </tr>
                                    @foreach ($group['children'] as $child)
                                        <tr x-show="open" x-cloak wire:key="g-{{ $group['key'] }}-c-{{ $child['key'] }}" class="bg-canvas/40">
                                            <td class="w-full max-w-0 py-2 pl-10 pr-3 sm:pl-14 sm:pr-4"><span class="block truncate text-muted">{{ $child['label'] }}</span></td>
                                            <td class="whitespace-nowrap px-3 py-2 text-right tabular-nums text-muted sm:px-4">{{ $this->fmt($child['duration']) }}</td>
                                            <td class="hidden sm:table-cell px-4 py-2 text-right tabular-nums text-muted whitespace-nowrap">{{ Money::format($child['amount'], $currency) }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            @endforeach
                        </table>
                    </div>
                    <div class="card p-4">
                        <div class="relative mx-auto size-52" wire:key="rdonut-{{ md5(json_encode($donutChart)) }}" x-data="chart(@js($donutChart))">
                            <canvas x-ref="canvas"></canvas>
                            <div class="pointer-events-none absolute inset-0 flex items-center justify-center text-lg tabular-nums">{{ $this->fmt($totals['duration']) }}</div>
                        </div>
                        <ul class="mt-4 space-y-1.5 text-sm">
                            @foreach (array_slice($groups, 0, 10) as $group)
                                <li wire:key="leg-{{ $group['key'] }}" class="flex items-center gap-2">
                                    <span class="size-2.5 shrink-0 rounded-full" style="background: {{ $group['color'] }}"></span>
                                    <span class="flex-1 truncate">{{ $group['label'] }}</span>
                                    <span class="tabular-nums text-muted">{{ $totals['duration'] ? round($group['duration'] / $totals['duration'] * 100, 1) : 0 }}%</span>
                                </li>
                            @endforeach
                        </ul>
                    </div>
                </div>
            @endif
        @elseif ($tab === 'detailed')
            <div class="card overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="table-head border-b border-line-soft text-left">
                            <th class="px-3 py-2 sm:px-4">Time entry</th>
                            <th class="hidden px-4 py-2 md:table-cell">User</th>
                            <th class="hidden px-4 py-2 md:table-cell">Time</th>
                            <th class="px-3 py-2 text-right sm:px-4">Duration</th>
                            <th class="hidden sm:table-cell px-4 py-2 text-right">Amount</th>
                            <th class="w-10"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-line-soft">
                        @forelse ($entries as $entry)
                            @php $start = $entry->start->setTimezone($timezone); @endphp
                            <tr wire:key="d-{{ $entry->id }}" class="hover:bg-canvas/60">
                                <td class="w-full max-w-0 px-3 py-2.5 sm:px-4">
                                    <p class="truncate text-ink">{{ $entry->description ?: '(no description)' }}</p>
                                    <p class="flex flex-wrap items-center gap-x-2 text-xs">
                                        @if ($entry->project)
                                            <span style="color: {{ $entry->project->color }}">● {{ $entry->project->name }}@if ($entry->task): {{ $entry->task->name }}@endif</span>
                                            @if ($entry->project->client)<span class="text-muted">{{ $entry->project->client->name }}</span>@endif
                                        @else
                                            <span class="text-muted">No project</span>
                                        @endif
                                        @foreach ($entry->tags as $tag)<span class="chip bg-primary-soft text-primary-dark">{{ $tag->name }}</span>@endforeach
                                        @if ($entry->billable)<span class="text-primary" title="Billable">$</span>@endif
                                    </p>
                                    <p class="mt-0.5 truncate text-xs text-muted md:hidden">{{ $entry->user->name }} · {{ $start->format('M j') }}, {{ $start->format($timeFormat) }} – {{ $entry->end->setTimezone($timezone)->format($timeFormat) }}</p>
                                </td>
                                <td class="hidden px-4 py-2.5 md:table-cell"><div class="flex items-center gap-2"><x-avatar :user="$entry->user" size="size-6 text-[10px]" /><span class="truncate">{{ $entry->user->name }}</span></div></td>
                                <td class="hidden px-4 py-2.5 whitespace-nowrap text-muted md:table-cell">
                                    {{ $start->format('M j, Y') }}<br>
                                    <span class="text-xs">{{ $start->format($timeFormat) }} – {{ $entry->end->setTimezone($timezone)->format($timeFormat) }}</span>
                                </td>
                                <td class="whitespace-nowrap px-3 py-2.5 text-right tabular-nums sm:px-4">{{ $this->fmt($entry->duration) }}</td>
                                <td class="hidden sm:table-cell px-4 py-2.5 text-right tabular-nums text-muted whitespace-nowrap">{{ Money::format($entry->amount(), $currency) }}</td>
                                <td class="pr-2">
                                    @if ($this->isAdmin || $entry->user_id === auth()->id())
                                        <button type="button" class="btn-icon opacity-60 hover:opacity-100" wire:click="deleteEntry({{ $entry->id }})" wire:confirm="Delete this time entry?" aria-label="Delete"><x-icon name="trash" class="size-4" /></button>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="6"><x-empty-state icon="reports" title="No time entries" text="Adjust the date range or filters." /></td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="mt-4">{{ $entries->links() }}</div>
        @else
            <div class="mb-3 flex items-center gap-2 text-sm text-muted">
                <span>Rows</span>
                <select wire:model.live="rowsBy" class="input h-8 w-auto py-0">
                    @if ($canSeeTeam)<option value="user">Users</option>@endif
                    <option value="project">Projects</option>
                </select>
            </div>
            @if ($tooWide)
                <div class="card p-6 text-center text-sm text-muted">The weekly view shows up to {{ \App\Livewire\Reports::MAX_MATRIX_DAYS }} days. Pick a shorter range (e.g. "This week").</div>
            @else
                <div class="card overflow-x-auto">
                    <table class="w-full text-sm" style="min-width: {{ 16 + count($days) * 5.5 }}rem">
                        <thead>
                            <tr class="table-head border-b border-line-soft">
                                <th class="sticky left-0 bg-surface px-4 py-2 text-left">{{ $rowsBy === 'project' ? 'Project' : 'User' }}</th>
                                @foreach ($days as $day)
                                    <th wire:key="wd-{{ $day->format('Ymd') }}" @class(['px-2 py-2 text-right whitespace-nowrap', 'text-primary' => $day->isToday()])>{{ $day->format('D') }}<br><span class="normal-case">{{ $day->format('M j') }}</span></th>
                                @endforeach
                                <th class="px-4 py-2 text-right">Total</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-line-soft">
                            @php $colTotals = array_fill(0, count($days), 0); @endphp
                            @forelse ($matrix as $row)
                                <tr wire:key="w-{{ $row['key'] }}" class="hover:bg-canvas/60">
                                    <td class="sticky left-0 bg-surface px-4 py-2.5"><div class="flex items-center gap-2 max-w-56"><span class="size-2.5 shrink-0 rounded-full" style="background: {{ $row['color'] }}"></span><span class="truncate">{{ $row['label'] }}</span></div></td>
                                    @foreach ($row['days'] as $i => $seconds)
                                        @php $colTotals[$i] += $seconds; @endphp
                                        <td class="px-2 py-2.5 text-right tabular-nums {{ $seconds ? 'text-ink' : 'text-line' }}">{{ $seconds ? $this->fmt($seconds) : '—' }}</td>
                                    @endforeach
                                    <td class="px-4 py-2.5 text-right font-medium tabular-nums">{{ $this->fmt($row['duration']) }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="{{ count($days) + 2 }}"><x-empty-state icon="reports" title="No data for this period" /></td></tr>
                            @endforelse
                        </tbody>
                        @if ($matrix)
                            <tfoot>
                                <tr class="border-t border-line bg-canvas/60 font-medium">
                                    <td class="sticky left-0 bg-canvas px-4 py-2.5">Total</td>
                                    @foreach ($colTotals as $i => $seconds)<td wire:key="wt-{{ $i }}" class="px-2 py-2.5 text-right tabular-nums">{{ $seconds ? $this->fmt($seconds) : '—' }}</td>@endforeach
                                    <td class="px-4 py-2.5 text-right tabular-nums">{{ $this->fmt($totals['duration']) }}</td>
                                </tr>
                            </tfoot>
                        @endif
                    </table>
                </div>
            @endif
        @endif
    </div>
</div>
