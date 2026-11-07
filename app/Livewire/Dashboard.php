<?php

namespace App\Livewire;

use App\Livewire\Concerns\InteractsWithWorkspace;
use App\Livewire\Concerns\WithDateRange;
use App\Models\TimeEntry;
use App\Models\User;
use App\Reports\ReportBuilder;
use App\Reports\ReportFilters;
use App\Support\Duration;
use Carbon\CarbonImmutable;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

#[Title('Dashboard')]
class Dashboard extends Component
{
    use InteractsWithWorkspace, WithDateRange;

    /** '' = default: team for admins, own data for members. */
    #[Url(except: '')]
    public string $scope = '';

    #[Url]
    public string $splitBy = 'project';

    public function render(): mixed
    {
        $range = $this->range();
        $probe = new ReportBuilder($this->workspace, $this->user(), new ReportFilters($range));
        $canSeeTeam = $probe->canSeeTeam();
        $wanted = $this->scope !== '' ? $this->scope : ($this->isAdmin ? 'team' : 'me');
        $scope = $canSeeTeam && $wanted === 'team' ? 'team' : 'me';
        $splitBy = in_array($this->splitBy, ['project', 'billable'], true) ? $this->splitBy : 'project';

        $builder = new ReportBuilder($this->workspace, $this->user(), new ReportFilters(
            range: $range,
            userIds: $scope === 'me' ? [$this->user()->id] : [],
        ));

        $totals = $builder->totals();
        $projects = $builder->summary('project');
        $clients = array_values(array_filter($builder->summary('client'), fn (array $c): bool => $c['key'] !== 0));
        $daily = $builder->daily($splitBy);

        return view('livewire.dashboard', [
            'canSeeTeam' => $canSeeTeam,
            'effectiveScope' => $scope,
            'totals' => $totals,
            'topProject' => $projects[0] ?? null,
            'topClient' => $clients[0] ?? null,
            'projects' => $projects,
            'activities' => $this->topActivities($builder),
            'barChart' => $this->barChart($daily),
            'donutChart' => $this->donutChart($projects),
            'team' => $scope === 'team' ? $this->teamActivity($builder) : [],
        ]);
    }

    /** @return list<array{description:string, project:array, duration:int}> */
    private function topActivities(ReportBuilder $builder): array
    {
        return $builder->rows()
            ->groupBy(fn (object $r): string => $r->description.'|'.($r->project_id ?? 0))
            ->map(fn ($group): array => [
                'description' => $group->first()->description,
                'project' => $builder->label('project', $group->first()->project_id ?? 0) + ['id' => $group->first()->project_id],
                'duration' => (int) $group->sum('duration'),
            ])
            ->sortByDesc('duration')
            ->take(10)
            ->values()
            ->all();
    }

    /** @return list<array{user:User, duration:int, latest:?TimeEntry, running:bool}> */
    private function teamActivity(ReportBuilder $builder): array
    {
        $durations = $builder->rows()->groupBy('user_id')->map(fn ($g): int => (int) $g->sum('duration'));
        $members = $this->workspace->activeUsers()->orderBy('name')->get();

        $latest = TimeEntry::query()
            ->where('workspace_id', $this->workspace->id)
            ->whereIn('id', TimeEntry::query()->selectRaw('MAX(id)')->where('workspace_id', $this->workspace->id)->groupBy('user_id'))
            ->with('project:id,name,color')
            ->get()
            ->keyBy('user_id');

        return $members->map(fn (User $member): array => [
            'user' => $member,
            'duration' => $durations[$member->id] ?? 0,
            'latest' => $latest[$member->id] ?? null,
            'running' => ($latest[$member->id] ?? null)?->end === null && isset($latest[$member->id]),
        ])->sortByDesc('duration')->values()->all();
    }

    /** @param array{days:list<string>, totals:list<int>, series:list<array>} $daily */
    private function barChart(array $daily): array
    {
        $labels = array_map(fn (string $d): string => CarbonImmutable::parse($d)->format(count($daily['days']) > 14 ? 'M j' : 'D, M j'), $daily['days']);

        return [
            'type' => 'bar',
            'data' => [
                'labels' => $labels,
                'datasets' => array_map(fn (array $s): array => [
                    'label' => $s['label'],
                    'data' => $s['data'],
                    'backgroundColor' => $s['color'],
                    'borderRadius' => 2,
                    'maxBarThickness' => 48,
                ], $daily['series']),
            ],
            'options' => [
                'responsive' => true,
                'maintainAspectRatio' => false,
                'plugins' => ['legend' => ['display' => false]],
                'scales' => [
                    'x' => ['stacked' => true, 'grid' => ['display' => false]],
                    'y' => ['stacked' => true, 'beginAtZero' => true, 'grid' => ['color' => '#e4eaee']],
                ],
            ],
        ];
    }

    /** @param list<array> $groups */
    private function donutChart(array $groups): array
    {
        return [
            'type' => 'doughnut',
            'data' => [
                'labels' => array_column($groups, 'label'),
                'datasets' => [[
                    'data' => array_column($groups, 'duration'),
                    'backgroundColor' => array_column($groups, 'color'),
                    'borderWidth' => 1,
                ]],
            ],
            'options' => [
                'responsive' => true,
                'maintainAspectRatio' => false,
                'cutout' => '62%',
                'plugins' => ['legend' => ['display' => false]],
            ],
        ];
    }

    public function formatDuration(int $seconds): string
    {
        return Duration::format($seconds);
    }
}
