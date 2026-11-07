@php use App\Support\Duration; use App\Support\Money; @endphp
<div>
    <div class="mb-5 flex flex-wrap items-center gap-3">
        <h1 class="page-title mr-auto">Dashboard</h1>

        <select wire:model.live="splitBy" class="input w-auto" aria-label="Chart breakdown">
            <option value="project">Project</option>
            <option value="billable">Billability</option>
        </select>

        @if ($canSeeTeam)
            <div class="flex rounded-sm border border-line bg-surface text-sm">
                <button type="button" wire:click="$set('scope', 'me')" @class(['px-3 h-9 cursor-pointer', 'bg-primary-soft text-ink' => $effectiveScope === 'me', 'text-muted' => $effectiveScope !== 'me'])>Only me</button>
                <button type="button" wire:click="$set('scope', 'team')" @class(['px-3 h-9 border-l border-line cursor-pointer', 'bg-primary-soft text-ink' => $effectiveScope === 'team', 'text-muted' => $effectiveScope !== 'team'])>Team</button>
            </div>
        @endif

        <x-date-range :label="$this->rangeLabel()" :preset="$preset" />
    </div>

    <div class="relative" wire:loading.class="opacity-60">
        {{-- Tiles --}}
        <div class="mb-5 grid grid-cols-1 gap-px overflow-hidden rounded-sm border border-line bg-line sm:grid-cols-3">
            <div class="bg-surface p-4">
                <p class="text-xs uppercase tracking-wide text-muted">Total time</p>
                <p class="mt-1 text-2xl text-ink tabular-nums">{{ Duration::format($totals['duration']) }}</p>
                @if ($totals['amount'] > 0)<p class="text-sm text-muted whitespace-nowrap">{{ Money::format($totals['amount'], $this->workspace->currency) }}</p>@endif
            </div>
            <div class="bg-surface p-4 min-w-0">
                <p class="text-xs uppercase tracking-wide text-muted">Top project</p>
                @if ($topProject)
                    <p class="mt-1 truncate text-2xl" style="color: {{ $topProject['color'] }}">{{ $topProject['label'] }}</p>
                    <p class="text-sm text-muted tabular-nums">{{ Duration::format($topProject['duration']) }}</p>
                @else
                    <p class="mt-1 text-2xl text-muted">—</p>
                @endif
            </div>
            <div class="bg-surface p-4 min-w-0">
                <p class="text-xs uppercase tracking-wide text-muted">Top client</p>
                @if ($topClient)
                    <p class="mt-1 truncate text-2xl text-ink">{{ $topClient['label'] }}</p>
                    <p class="text-sm text-muted tabular-nums">{{ Duration::format($topClient['duration']) }}</p>
                @else
                    <p class="mt-1 text-2xl text-muted">—</p>
                @endif
            </div>
        </div>

        {{-- Bar chart --}}
        <div class="card mb-5 p-4">
            @if ($totals['duration'] > 0)
                <div class="h-72" wire:key="bar-{{ md5(json_encode($barChart)) }}" x-data="chart(@js($barChart))"><canvas x-ref="canvas"></canvas></div>
            @else
                <x-empty-state icon="dashboard" title="No time tracked in this period" text="Track some time or pick another date range." />
            @endif
        </div>

        <div class="grid gap-5 lg:grid-cols-2">
            {{-- Most tracked activities --}}
            <div class="card">
                <div class="card-header">Most tracked activities</div>
                <ul class="divide-y divide-line-soft">
                    @forelse ($activities as $i => $activity)
                        <li wire:key="act-{{ $i }}" class="flex items-center gap-3 px-4 py-2.5 text-sm">
                            <div class="min-w-0 flex-1">
                                <p class="truncate text-ink">{{ $activity['description'] ?: '(no description)' }}</p>
                                <p class="truncate text-xs" style="color: {{ $activity['project']['color'] }}">{{ $activity['project']['label'] }}</p>
                            </div>
                            <span class="tabular-nums text-ink">{{ Duration::format($activity['duration']) }}</span>
                        </li>
                    @empty
                        <li class="px-4 py-6 text-center text-sm text-muted">No activity yet.</li>
                    @endforelse
                </ul>
            </div>

            {{-- Project split --}}
            <div class="card">
                <div class="card-header">Projects</div>
                @if ($projects)
                    <div class="flex flex-col items-center gap-4 p-4 sm:flex-row">
                        <div class="relative size-48 shrink-0" wire:key="donut-{{ md5(json_encode($donutChart)) }}" x-data="chart(@js($donutChart))">
                            <canvas x-ref="canvas"></canvas>
                            <div class="pointer-events-none absolute inset-0 flex flex-col items-center justify-center">
                                <span class="text-lg tabular-nums text-ink">{{ Duration::format($totals['duration']) }}</span>
                            </div>
                        </div>
                        <ul class="w-full min-w-0 space-y-2 text-sm">
                            @foreach (array_slice($projects, 0, 8) as $project)
                                <li wire:key="dp-{{ $project['key'] }}" class="flex items-center gap-2">
                                    <span class="size-2.5 shrink-0 rounded-full" style="background: {{ $project['color'] }}"></span>
                                    <span class="flex-1 truncate">{{ $project['label'] }}</span>
                                    <span class="tabular-nums text-muted">{{ $totals['duration'] ? round($project['duration'] / $totals['duration'] * 100, 1) : 0 }}%</span>
                                    <span class="w-20 text-right tabular-nums">{{ Duration::format($project['duration']) }}</span>
                                </li>
                            @endforeach
                        </ul>
                    </div>
                @else
                    <p class="px-4 py-6 text-center text-sm text-muted">No projects tracked.</p>
                @endif
            </div>
        </div>

        {{-- Team activity --}}
        @if ($effectiveScope === 'team')
            <div class="card mt-5 overflow-x-auto">
                <div class="card-header">Team activities</div>
                <table class="w-full text-sm">
                    <thead><tr class="table-head border-b border-line-soft text-left"><th class="px-4 py-2">Member</th><th class="hidden sm:table-cell px-4 py-2">Latest activity</th><th class="px-4 py-2 text-right">Total</th></tr></thead>
                    <tbody class="divide-y divide-line-soft">
                        @foreach ($team as $row)
                            <tr wire:key="team-{{ $row['user']->id }}">
                                <td class="w-full max-w-0 px-4 py-2.5 sm:w-auto sm:max-w-none">
                                    <div class="flex min-w-0 items-center gap-2"><x-avatar :user="$row['user']" size="size-7 text-[10px]" /> <span class="truncate">{{ $row['user']->name }}</span>@if ($row['running'])<span class="size-2 shrink-0 rounded-full bg-danger animate-pulse sm:hidden"></span>@endif</div>
                                    @if ($row['latest'])<p class="truncate pl-9 text-xs text-muted sm:hidden">{{ $row['latest']->description ?: '(no description)' }} · {{ $row['latest']->start->diffForHumans() }}</p>@endif
                                </td>
                                <td class="hidden sm:table-cell px-4 py-2.5">
                                    @if ($row['latest'])
                                        <div class="flex items-center gap-2 min-w-0">
                                            @if ($row['running'])<span class="size-2 shrink-0 rounded-full bg-danger animate-pulse" title="Timer running"></span>@endif
                                            <span class="truncate">{{ $row['latest']->description ?: '(no description)' }}</span>
                                            @if ($row['latest']->project)<span class="truncate text-xs" style="color: {{ $row['latest']->project->color }}">{{ $row['latest']->project->name }}</span>@endif
                                        </div>
                                        <p class="text-xs text-muted">{{ $row['latest']->start->diffForHumans() }}</p>
                                    @else
                                        <span class="text-muted">No activity</span>
                                    @endif
                                </td>
                                <td class="px-4 py-2.5 text-right tabular-nums">{{ Duration::format($row['duration']) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>
</div>
