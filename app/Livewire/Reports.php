<?php

namespace App\Livewire;

use App\Livewire\Concerns\InteractsWithWorkspace;
use App\Livewire\Concerns\WithDateRange;
use App\Models\Client;
use App\Models\Project;
use App\Models\Tag;
use App\Models\TimeEntry;
use App\Models\UserGroup;
use App\Reports\ReportBuilder;
use App\Reports\ReportFilters;
use App\Support\Duration;
use Carbon\CarbonImmutable;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;
use Symfony\Component\HttpFoundation\StreamedResponse;

#[Title('Reports')]
class Reports extends Component
{
    use InteractsWithWorkspace, WithDateRange, WithPagination;

    public const MAX_MATRIX_DAYS = 31;

    public string $tab = 'summary';

    /** @var list<int> */
    #[Url(except: [])]
    public array $userIds = [];

    /** @var list<int> */
    #[Url(except: [])]
    public array $groupIds = [];

    /** @var list<int> */
    #[Url(except: [])]
    public array $clientIds = [];

    /** @var list<int> */
    #[Url(except: [])]
    public array $projectIds = [];

    /** @var list<int> */
    #[Url(except: [])]
    public array $tagIds = [];

    #[Url(except: '')]
    public string $billable = '';

    #[Url(except: '')]
    public string $description = '';

    #[Url]
    public string $groupBy = 'project';

    #[Url]
    public string $subGroupBy = 'description';

    #[Url]
    public string $rowsBy = 'user';

    #[Url]
    public bool $decimal = false;

    public function mount(string $tab = 'summary'): void
    {
        $this->tab = $tab;
    }

    public function updated(string $property): void
    {
        if (! in_array($property, ['decimal', 'groupBy', 'subGroupBy', 'rowsBy'], true)) {
            $this->resetPage();
        }
    }

    public function clearFilters(): void
    {
        $this->reset(['userIds', 'groupIds', 'clientIds', 'projectIds', 'tagIds', 'billable', 'description']);
        $this->resetPage();
    }

    public function deleteEntry(int $id): void
    {
        $entry = TimeEntry::query()->where('workspace_id', $this->workspace->id)->findOrFail($id);
        abort_unless($entry->user_id === $this->user()->id || $this->isAdmin, 403);
        $entry->delete();
        $this->notify('Time entry deleted');
    }

    public function export(): StreamedResponse
    {
        $builder = $this->builder();
        $name = "{$this->tab}-report-{$this->from}-{$this->to}.csv";

        return response()->streamDownload(function () use ($builder): void {
            $out = fopen('php://output', 'w');

            match ($this->tab) {
                'detailed' => $this->exportDetailed($out, $builder),
                'weekly' => $this->exportWeekly($out, $builder),
                default => $this->exportSummary($out, $builder),
            };

            fclose($out);
        }, $name, ['Content-Type' => 'text/csv']);
    }

    public function render(): mixed
    {
        $builder = $this->builder();
        $groupBy = $this->dimension($this->groupBy, 'project');
        $subGroupBy = $this->subGroupBy === '' ? null : $this->dimension($this->subGroupBy, 'description');
        $data = ['totals' => $builder->totals(), 'canSeeTeam' => $builder->canSeeTeam()];

        if ($this->tab === 'summary') {
            $daily = $builder->daily(null);
            $groups = $builder->summary($groupBy, $subGroupBy);
            $data += [
                'groups' => $groups,
                'barChart' => $this->barChart($daily),
                'donutChart' => $this->donutChart($groups),
            ];
        } elseif ($this->tab === 'detailed') {
            $data['entries'] = $builder->query()
                ->with(['user:id,name,avatar_color', 'project:id,name,color,client_id', 'project.client:id,name', 'task:id,name', 'tags:id,name'])
                ->orderByDesc('start')->orderByDesc('id')
                ->paginate(50);
        } else {
            if (! $builder->canSeeTeam()) {
                $this->rowsBy = 'project';
            }
            $range = $this->range();
            $data['tooWide'] = $range->dayCount() > self::MAX_MATRIX_DAYS;
            $data['days'] = $data['tooWide'] ? [] : $range->days();
            $data['matrix'] = $data['tooWide'] ? [] : $builder->matrix($this->rowsBy === 'project' ? 'project' : 'user');
        }

        return view('livewire.reports', $data + [
            'filterOptions' => $this->filterOptions($builder->canSeeTeam()),
            'dimensions' => ReportBuilder::DIMENSIONS,
            'timezone' => $this->user()->timezone,
            'timeFormat' => $this->timeFormat(),
        ]);
    }

    public function fmt(int $seconds): string
    {
        return $this->decimal ? Duration::decimal($seconds) : Duration::format($seconds);
    }

    private function builder(): ReportBuilder
    {
        $ints = fn (array $ids): array => array_values(array_unique(array_map('intval', $ids)));

        return new ReportBuilder($this->workspace, $this->user(), new ReportFilters(
            range: $this->range(),
            userIds: $ints($this->userIds),
            groupIds: $ints($this->groupIds),
            projectIds: $ints($this->projectIds),
            clientIds: $ints($this->clientIds),
            tagIds: $ints($this->tagIds),
            billable: $this->billable === '' ? null : $this->billable === '1',
            description: $this->description,
        ));
    }

    private function dimension(string $value, string $fallback): string
    {
        return array_key_exists($value, ReportBuilder::DIMENSIONS) ? $value : $fallback;
    }

    /** @return array<string, array<int, string|array{label:string,color:string}>> */
    private function filterOptions(bool $canSeeTeam): array
    {
        $ws = $this->workspace->id;

        return [
            'users' => $canSeeTeam ? $this->workspace->users()->orderBy('name')->pluck('name', 'users.id')->all() : [],
            'groups' => $canSeeTeam ? UserGroup::query()->where('workspace_id', $ws)->orderBy('name')->pluck('name', 'id')->all() : [],
            'clients' => Client::query()->where('workspace_id', $ws)->orderBy('name')->pluck('name', 'id')->all(),
            'projects' => Project::query()->where('workspace_id', $ws)->visibleTo($this->user(), $this->workspace)->orderBy('name')->get(['id', 'name', 'color'])
                ->mapWithKeys(fn (Project $p): array => [$p->id => ['label' => $p->name, 'color' => $p->color]])->all(),
            'tags' => Tag::query()->where('workspace_id', $ws)->orderBy('name')->pluck('name', 'id')->all(),
        ];
    }

    /** @param array{days:list<string>, totals:list<int>} $daily */
    private function barChart(array $daily): array
    {
        $many = count($daily['days']) > 14;

        return [
            'type' => 'bar',
            'data' => [
                'labels' => array_map(fn (string $d): string => CarbonImmutable::parse($d)->format($many ? 'M j' : 'D, M j'), $daily['days']),
                'datasets' => [['label' => 'Tracked', 'data' => $daily['totals'], 'backgroundColor' => '#03a9f4', 'borderRadius' => 2, 'maxBarThickness' => 48]],
            ],
            'options' => [
                'responsive' => true,
                'maintainAspectRatio' => false,
                'plugins' => ['legend' => ['display' => false]],
                'scales' => ['x' => ['grid' => ['display' => false]], 'y' => ['beginAtZero' => true, 'grid' => ['color' => '#e4eaee']]],
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
                'datasets' => [['data' => array_column($groups, 'duration'), 'backgroundColor' => array_column($groups, 'color'), 'borderWidth' => 1]],
            ],
            'options' => ['responsive' => true, 'maintainAspectRatio' => false, 'cutout' => '62%', 'plugins' => ['legend' => ['display' => false]]],
        ];
    }

    /** @param resource $out */
    private function exportSummary($out, ReportBuilder $builder): void
    {
        $groupBy = $this->dimension($this->groupBy, 'project');
        $sub = $this->subGroupBy === '' ? null : $this->dimension($this->subGroupBy, 'description');
        fputcsv($out, array_filter([ReportBuilder::DIMENSIONS[$groupBy], $sub ? ReportBuilder::DIMENSIONS[$sub] : null, 'Time (h:mm:ss)', 'Time (decimal)', 'Amount ('.$this->workspace->currency.')']));

        foreach ($builder->summary($groupBy, $sub) as $group) {
            fputcsv($out, array_values(array_filter([$group['label'], $sub ? '' : null, Duration::format($group['duration']), Duration::decimal($group['duration']), number_format($group['amount'] / 100, 2, '.', '')], fn ($v) => $v !== null)));
            foreach ($group['children'] as $child) {
                fputcsv($out, [$group['label'], $child['label'], Duration::format($child['duration']), Duration::decimal($child['duration']), number_format($child['amount'] / 100, 2, '.', '')]);
            }
        }
    }

    /** @param resource $out */
    private function exportDetailed($out, ReportBuilder $builder): void
    {
        $tz = $this->user()->timezone;
        fputcsv($out, ['Project', 'Client', 'Description', 'Task', 'User', 'Email', 'Tags', 'Billable', 'Start Date', 'Start Time', 'End Date', 'End Time', 'Duration (h)', 'Duration (decimal)', 'Billable Rate', 'Billable Amount']);

        $builder->query()
            ->with(['user:id,name,email', 'project:id,name,client_id', 'project.client:id,name', 'task:id,name', 'tags:id,name'])
            ->orderBy('start')
            ->chunk(500, function ($entries) use ($out, $tz): void {
                foreach ($entries as $e) {
                    $start = $e->start->setTimezone($tz);
                    $end = $e->end->setTimezone($tz);
                    fputcsv($out, [
                        $e->project?->name, $e->project?->client?->name, $e->description, $e->task?->name,
                        $e->user->name, $e->user->email, $e->tags->pluck('name')->implode(', '), $e->billable ? 'Yes' : 'No',
                        $start->format('Y-m-d'), $start->format('H:i:s'), $end->format('Y-m-d'), $end->format('H:i:s'),
                        Duration::format($e->duration), Duration::decimal($e->duration),
                        number_format($e->hourly_rate / 100, 2, '.', ''), number_format($e->amount() / 100, 2, '.', ''),
                    ]);
                }
            });
    }

    /** @param resource $out */
    private function exportWeekly($out, ReportBuilder $builder): void
    {
        $range = $this->range();
        $days = array_slice($range->days(), 0, self::MAX_MATRIX_DAYS);
        fputcsv($out, [$this->rowsBy === 'project' ? 'Project' : 'User', ...array_map(fn ($d) => $d->format('Y-m-d'), $days), 'Total']);

        foreach ($builder->matrix($this->rowsBy === 'project' ? 'project' : 'user') as $row) {
            fputcsv($out, [$row['label'], ...array_map(fn (int $s) => Duration::decimal($s), array_slice($row['days'], 0, count($days))), Duration::decimal($row['duration'])]);
        }
    }
}
