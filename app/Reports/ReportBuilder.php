<?php

namespace App\Reports;

use App\Models\Client;
use App\Models\Project;
use App\Models\Tag;
use App\Models\Task;
use App\Models\TimeEntry;
use App\Models\User;
use App\Models\Workspace;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Single source of truth for tracked-time aggregation used by the dashboard and reports.
 * Filtering happens in SQL; grouping happens in PHP so day buckets respect the viewer's timezone
 * and multi-tag entries count under each of their tags.
 */
final class ReportBuilder
{
    public const DIMENSIONS = [
        'project' => 'Project',
        'client' => 'Client',
        'user' => 'User',
        'tag' => 'Tag',
        'task' => 'Task',
        'date' => 'Date',
        'description' => 'Description',
    ];

    private ?Collection $rows = null;

    /** @var array<string, array<int|string, array{label:string, color:string, sub:?string}>> */
    private array $labels = [];

    public function __construct(
        private readonly Workspace $workspace,
        private readonly User $viewer,
        private readonly ReportFilters $filters,
    ) {}

    public function canSeeTeam(): bool
    {
        return $this->viewer->isAdminOf($this->workspace) || $this->workspace->members_see_team_reports;
    }

    /** @return Builder<TimeEntry> */
    public function query(): Builder
    {
        $f = $this->filters;

        $query = TimeEntry::query()
            ->where('time_entries.workspace_id', $this->workspace->id)
            ->whereNotNull('time_entries.end')
            ->whereBetween('time_entries.start', [$f->range->utcFrom(), $f->range->utcTo()]);

        if (! $this->canSeeTeam()) {
            $query->where('time_entries.user_id', $this->viewer->id);
        }

        $userIds = $f->userIds;
        if ($f->groupIds !== []) {
            $userIds = array_merge($userIds, DB::table('user_group_user')
                ->join('user_groups', 'user_groups.id', '=', 'user_group_user.user_group_id')
                ->where('user_groups.workspace_id', $this->workspace->id)
                ->whereIn('user_group_id', $f->groupIds)
                ->pluck('user_id')->map(fn ($id): int => (int) $id)->all());
            $userIds = $userIds === [] ? [0] : $userIds;
        }
        if ($userIds !== []) {
            $query->whereIn('time_entries.user_id', array_unique($userIds));
        }

        $this->whereNullable($query, 'time_entries.project_id', $f->projectIds);

        if ($f->clientIds !== []) {
            $ids = array_values(array_filter($f->clientIds));
            $query->where(function (Builder $q) use ($ids, $f): void {
                if ($ids !== []) {
                    $q->whereIn('time_entries.project_id', Project::query()->select('id')->whereIn('client_id', $ids));
                }
                if (in_array(0, $f->clientIds, true)) {
                    $q->orWhereNull('time_entries.project_id')
                        ->orWhereIn('time_entries.project_id', Project::query()->select('id')->whereNull('client_id'));
                }
            });
        }

        if ($f->tagIds !== []) {
            $ids = array_values(array_filter($f->tagIds));
            $query->where(function (Builder $q) use ($ids, $f): void {
                if ($ids !== []) {
                    $q->whereHas('tags', fn (Builder $t) => $t->whereIn('tags.id', $ids));
                }
                if (in_array(0, $f->tagIds, true)) {
                    $q->orWhereDoesntHave('tags');
                }
            });
        }

        if ($f->billable !== null) {
            $query->where('time_entries.billable', $f->billable);
        }

        if (trim($f->description) !== '') {
            $query->whereLike('time_entries.description', '%'.addcslashes(trim($f->description), '%_\\').'%');
        }

        return $query;
    }

    /** @return array{duration:int, billable:int, amount:int, count:int} */
    public function totals(): array
    {
        $row = $this->query()->toBase()->selectRaw(
            'COUNT(*) as entries, COALESCE(SUM(duration),0) as total, '.
            'COALESCE(SUM(CASE WHEN billable THEN duration ELSE 0 END),0) as billable_total, '.
            'COALESCE(SUM(CASE WHEN billable THEN duration * 1.0 * hourly_rate ELSE 0 END),0) as amount_raw'
        )->first();

        return [
            'duration' => (int) $row->total,
            'billable' => (int) $row->billable_total,
            'amount' => (int) round((float) $row->amount_raw / 3600),
            'count' => (int) $row->entries,
        ];
    }

    /**
     * Slim rows for in-memory grouping.
     *
     * @return Collection<int, object{id:int, user_id:int, project_id:?int, client_id:?int, task_id:?int, description:string, day:string, duration:int, amount:int, tag_ids:list<int>}>
     */
    public function rows(): Collection
    {
        if ($this->rows !== null) {
            return $this->rows;
        }

        $timezone = $this->viewer->timezone;
        $entries = $this->query()
            ->leftJoin('projects', 'projects.id', '=', 'time_entries.project_id')
            ->toBase()
            ->select([
                'time_entries.id', 'time_entries.user_id', 'time_entries.project_id', 'projects.client_id',
                'time_entries.task_id', 'time_entries.description', 'time_entries.start', 'time_entries.duration',
                'time_entries.billable', 'time_entries.hourly_rate',
            ])
            ->get();

        $tagMap = [];
        foreach ($entries->pluck('id')->chunk(500) as $chunk) {
            foreach (DB::table('tag_time_entry')->whereIn('time_entry_id', $chunk->all())->get() as $pivot) {
                $tagMap[$pivot->time_entry_id][] = (int) $pivot->tag_id;
            }
        }

        return $this->rows = $entries->map(fn (object $e): object => (object) [
            'id' => (int) $e->id,
            'user_id' => (int) $e->user_id,
            'project_id' => $e->project_id !== null ? (int) $e->project_id : null,
            'client_id' => $e->client_id !== null ? (int) $e->client_id : null,
            'task_id' => $e->task_id !== null ? (int) $e->task_id : null,
            'description' => (string) $e->description,
            'day' => CarbonImmutable::parse($e->start, 'UTC')->setTimezone($timezone)->format('Y-m-d'),
            'duration' => (int) $e->duration,
            'billable' => (bool) $e->billable,
            'amount' => $e->billable ? (int) round($e->duration / 3600 * $e->hourly_rate) : 0,
            'tag_ids' => $tagMap[$e->id] ?? [],
        ]);
    }

    /**
     * Totals per day of the range, optionally split by a dimension (for stacked charts).
     *
     * @return array{days:list<string>, totals:list<int>, series:list<array{key:int|string, label:string, color:string, data:list<int>}>}
     */
    public function daily(?string $splitBy = 'project'): array
    {
        $days = array_map(fn (CarbonImmutable $d): string => $d->format('Y-m-d'), $this->filters->range->days());
        $index = array_flip($days);
        $totals = array_fill(0, count($days), 0);
        $series = [];

        foreach ($this->rows() as $row) {
            if (! isset($index[$row->day])) {
                continue;
            }
            $i = $index[$row->day];
            $totals[$i] += $row->duration;

            if ($splitBy) {
                foreach ($this->keysFor($row, $splitBy) as $key) {
                    $series[$key] ??= array_fill(0, count($days), 0);
                    $series[$key][$i] += $row->duration;
                }
            }
        }

        return [
            'days' => $days,
            'totals' => $totals,
            'series' => collect($series)
                ->sortByDesc(fn (array $data): int => array_sum($data))
                ->map(fn (array $data, int|string $key): array => [...$this->label($splitBy, $key), 'key' => $key, 'data' => $data])
                ->values()
                ->all(),
        ];
    }

    /**
     * Grouped summary with an optional second level.
     *
     * @return list<array{key:int|string, label:string, color:string, sub:?string, duration:int, amount:int, children:list<array>}>
     */
    public function summary(string $groupBy, ?string $subGroupBy = null): array
    {
        $groups = [];

        foreach ($this->rows() as $row) {
            foreach ($this->keysFor($row, $groupBy) as $key) {
                $groups[$key] ??= ['duration' => 0, 'amount' => 0, 'children' => []];
                $groups[$key]['duration'] += $row->duration;
                $groups[$key]['amount'] += $row->amount;

                if ($subGroupBy && $subGroupBy !== $groupBy) {
                    foreach ($this->keysFor($row, $subGroupBy) as $subKey) {
                        $groups[$key]['children'][$subKey] ??= ['duration' => 0, 'amount' => 0];
                        $groups[$key]['children'][$subKey]['duration'] += $row->duration;
                        $groups[$key]['children'][$subKey]['amount'] += $row->amount;
                    }
                }
            }
        }

        $build = function (array $items, string $dimension) use (&$build): array {
            $out = [];
            foreach ($items as $key => $item) {
                $out[] = [
                    ...$this->label($dimension, $key),
                    'key' => $key,
                    'duration' => $item['duration'],
                    'amount' => $item['amount'],
                    'children' => [],
                    '_children' => $item['children'] ?? [],
                ];
            }

            usort($out, fn (array $a, array $b): int => $dimension === 'date'
                ? strcmp((string) $a['key'], (string) $b['key'])
                : [$b['duration'], $a['label']] <=> [$a['duration'], $b['label']]);

            return $out;
        };

        $result = $build($groups, $groupBy);
        foreach ($result as &$group) {
            if ($subGroupBy && $group['_children'] !== []) {
                $group['children'] = array_map(function (array $child): array {
                    unset($child['_children']);

                    return $child;
                }, $build($group['_children'], $subGroupBy));
            }
            unset($group['_children']);
        }

        return $result;
    }

    /**
     * Matrix of a dimension × each day of the range (weekly report / timesheet view).
     *
     * @return list<array{key:int|string, label:string, color:string, sub:?string, days:list<int>, duration:int, amount:int}>
     */
    public function matrix(string $rowsBy): array
    {
        $days = array_map(fn (CarbonImmutable $d): string => $d->format('Y-m-d'), $this->filters->range->days());
        $index = array_flip($days);
        $matrix = [];

        foreach ($this->rows() as $row) {
            if (! isset($index[$row->day])) {
                continue;
            }
            foreach ($this->keysFor($row, $rowsBy) as $key) {
                $matrix[$key] ??= ['days' => array_fill(0, count($days), 0), 'duration' => 0, 'amount' => 0];
                $matrix[$key]['days'][$index[$row->day]] += $row->duration;
                $matrix[$key]['duration'] += $row->duration;
                $matrix[$key]['amount'] += $row->amount;
            }
        }

        return collect($matrix)
            ->map(fn (array $item, int|string $key): array => [...$this->label($rowsBy, $key), 'key' => $key, ...$item])
            ->sortByDesc('duration')
            ->values()
            ->all();
    }

    /** @return list<int|string> */
    private function keysFor(object $row, string $dimension): array
    {
        return match ($dimension) {
            'project' => [$row->project_id ?? 0],
            'client' => [$row->client_id ?? 0],
            'user' => [$row->user_id],
            'task' => [$row->task_id ?? 0],
            'tag' => $row->tag_ids !== [] ? $row->tag_ids : [0],
            'date' => [$row->day],
            'description' => [$row->description === '' ? '' : $row->description],
            'billable' => [$row->billable ? 1 : 0],
            default => [0],
        };
    }

    /** @return array{label:string, color:string, sub:?string} */
    public function label(string $dimension, int|string $key): array
    {
        $this->labels[$dimension] ??= $this->loadLabels($dimension);

        return $this->labels[$dimension][$key] ?? match ($dimension) {
            'project' => ['label' => 'Without project', 'color' => '#9e9e9e', 'sub' => null],
            'client' => ['label' => 'Without client', 'color' => '#9e9e9e', 'sub' => null],
            'tag' => ['label' => 'Without tag', 'color' => '#9e9e9e', 'sub' => null],
            'task' => ['label' => 'Without task', 'color' => '#9e9e9e', 'sub' => null],
            'date' => ['label' => CarbonImmutable::parse((string) $key)->format('D, M j, Y'), 'color' => '#03a9f4', 'sub' => null],
            'description' => ['label' => $key === '' ? '(no description)' : (string) $key, 'color' => '#607d8b', 'sub' => null],
            'billable' => $key ? ['label' => 'Billable', 'color' => '#03a9f4', 'sub' => null] : ['label' => 'Non-billable', 'color' => '#b0bec5', 'sub' => null],
            default => ['label' => 'Unknown', 'color' => '#9e9e9e', 'sub' => null],
        };
    }

    /** @return array<int|string, array{label:string, color:string, sub:?string}> */
    private function loadLabels(string $dimension): array
    {
        $palette = Project::COLORS;
        $ws = $this->workspace->id;

        return match ($dimension) {
            'project' => Project::query()->where('workspace_id', $ws)->with('client:id,name')->get()
                ->mapWithKeys(fn (Project $p): array => [$p->id => ['label' => $p->name, 'color' => $p->color, 'sub' => $p->client?->name]])->all(),
            'client' => Client::query()->where('workspace_id', $ws)->get()
                ->mapWithKeys(fn (Client $c): array => [$c->id => ['label' => $c->name, 'color' => $palette[$c->id % count($palette)], 'sub' => null]])->all(),
            'user' => $this->workspace->users()->get()
                ->mapWithKeys(fn (User $u): array => [$u->id => ['label' => $u->name, 'color' => $u->avatar_color, 'sub' => $u->email]])->all(),
            'tag' => Tag::query()->where('workspace_id', $ws)->get()
                ->mapWithKeys(fn (Tag $t): array => [$t->id => ['label' => $t->name, 'color' => $palette[$t->id % count($palette)], 'sub' => null]])->all(),
            'task' => Task::query()->whereIn('project_id', Project::query()->select('id')->where('workspace_id', $ws))->with('project:id,name,color')->get()
                ->mapWithKeys(fn (Task $t): array => [$t->id => ['label' => $t->name, 'color' => $t->project->color, 'sub' => $t->project->name]])->all(),
            default => [],
        };
    }

    /**
     * @param  Builder<TimeEntry>  $query
     * @param  list<int>  $ids
     */
    private function whereNullable(Builder $query, string $column, array $ids): void
    {
        if ($ids === []) {
            return;
        }

        $real = array_values(array_filter($ids));
        $query->where(function (Builder $q) use ($column, $real, $ids): void {
            if ($real !== []) {
                $q->whereIn($column, $real);
            }
            if (in_array(0, $ids, true)) {
                $q->orWhereNull($column);
            }
        });
    }
}
