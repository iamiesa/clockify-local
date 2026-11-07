<?php

namespace App\Livewire;

use App\Actions\TimeEntrySaveAction;
use App\Actions\TimerStartAction;
use App\Actions\TimerStopAction;
use App\Livewire\Concerns\InteractsWithWorkspace;
use App\Models\Project;
use App\Models\Tag;
use App\Models\TimeEntry;
use App\Support\ClockTime;
use App\Support\Duration;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Attributes\Session;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Title('Time Tracker')]
class Tracker extends Component
{
    use InteractsWithWorkspace;

    private const PAGE_SIZE = 50;

    #[Session]
    public string $mode = 'timer';

    public string $description = '';

    public ?int $projectId = null;

    public ?int $taskId = null;

    /** @var list<int> */
    public array $tagIds = [];

    public bool $billable = false;

    public string $manualDate = '';

    public string $manualStart = '';

    public string $manualEnd = '';

    public int $limit = self::PAGE_SIZE;

    /** Bumped after a rejected edit so inline inputs re-render with the stored values. */
    public int $renderKey = 0;

    public function mount(): void
    {
        $this->resetForm();
    }

    /* ----------------------------------------------------------------- Timer bar */

    public function startTimer(TimerStartAction $start): void
    {
        $this->attempt(function () use ($start): void {
            $start($this->user(), $this->workspace, $this->formData());
            $this->dispatch('timer-changed');
        });
    }

    public function stopTimer(TimerStopAction $stop): void
    {
        $this->attempt(function () use ($stop): void {
            $stop($this->user(), $this->workspace);
            $this->resetForm();
            $this->dispatch('timer-changed');
        });
    }

    public function discardTimer(): void
    {
        $this->user()->runningEntry($this->workspace)?->delete();
        $this->resetForm();
        $this->dispatch('timer-changed');
        $this->notify('Timer discarded');
    }

    /** Keep the running entry in sync as the user edits the timer bar. */
    public function updated(string $property): void
    {
        if (! in_array($property, ['description', 'billable'], true) && ! str_starts_with($property, 'tagIds')) {
            return;
        }

        $this->saveRunning([
            'description' => $this->description,
            'billable' => $this->billable,
            'tag_ids' => $this->tagIds,
        ]);
    }

    public function setFormProject(?int $projectId, ?int $taskId = null): void
    {
        $this->projectId = $projectId ?: null;
        $this->taskId = $taskId ?: null;

        if ($this->projectId && ! $this->running) {
            $this->billable = (bool) Project::whereKey($this->projectId)->value('billable');
        }

        $this->saveRunning(['project_id' => $this->projectId, 'task_id' => $this->taskId]);
        if ($this->running) {
            $this->billable = $this->running->billable;
        }
    }

    /** @param  list<int>  $tagIds */
    public function setFormTags(array $tagIds): void
    {
        $this->tagIds = array_values(array_map('intval', $tagIds));
        $this->saveRunning(['tag_ids' => $this->tagIds]);
    }

    public function updateRunningStart(string $time): void
    {
        $running = $this->running;
        $parsed = ClockTime::parse($time);

        if (! $running || ! $parsed) {
            $this->fail('Enter a valid start time, e.g. 09:30');

            return;
        }

        $start = $this->now()->setTime($parsed[0], $parsed[1]);
        if ($start->isFuture()) {
            $start = $start->subDay();
        }

        $this->saveRunning(['start' => $start]);
        $this->dispatch('timer-changed');
    }

    public function addManualEntry(TimeEntrySaveAction $save): void
    {
        $start = $this->parseLocal($this->manualDate, $this->manualStart);
        $end = $this->parseLocal($this->manualDate, $this->manualEnd);

        if (! $start || ! $end) {
            $this->fail('Enter valid start and end times.');

            return;
        }

        if ($end->lessThan($start)) {
            $end = $end->addDay();
        }

        $this->attempt(function () use ($save, $start, $end): void {
            $save($this->user(), $this->workspace, [...$this->formData(), 'start' => $start, 'end' => $end]);
            $this->resetForm();
            $this->notify('Time entry added');
        });
    }

    public function setManualDuration(string $value): void
    {
        $seconds = Duration::parse($value);
        $start = $this->parseLocal($this->manualDate, $this->manualStart);

        if ($seconds === null || ! $start) {
            $this->fail('Enter a valid duration, e.g. 1:30');

            return;
        }

        $this->manualEnd = $start->addSeconds($seconds)->format('H:i');
    }

    /* ----------------------------------------------------------------- Entry list */

    public function updateEntry(int $id, string $field, mixed $value): void
    {
        $entry = $this->findEntry($id);
        $data = match ($field) {
            'description' => ['description' => (string) $value],
            'billable' => ['billable' => (bool) $value],
            'tags' => ['tag_ids' => array_map('intval', (array) $value)],
            default => null,
        };

        if ($data !== null) {
            $this->saveEntry($entry, $data);
        }
    }

    public function updateEntryProject(int $id, ?int $projectId, ?int $taskId = null): void
    {
        $this->saveEntry($this->findEntry($id), ['project_id' => $projectId ?: null, 'task_id' => $taskId ?: null]);
    }

    public function updateEntryTimes(int $id, string $startTime, string $endTime): void
    {
        $entry = $this->findEntry($id);
        $date = $entry->start->setTimezone($this->user()->timezone)->format('Y-m-d');
        $start = $this->parseLocal($date, $startTime);
        $end = $this->parseLocal($date, $endTime);

        if (! $start || ! $end) {
            $this->fail('Enter a valid time, e.g. 09:30 or 2:15pm');

            return;
        }

        if ($end->lessThan($start)) {
            $end = $end->addDay();
        }

        $this->saveEntry($entry, ['start' => $start, 'end' => $end]);
    }

    public function updateEntryDate(int $id, string $date): void
    {
        $entry = $this->findEntry($id);
        $tz = $this->user()->timezone;

        try {
            $target = CarbonImmutable::createFromFormat('!Y-m-d', $date, $tz);
        } catch (\Throwable) {
            return;
        }

        $localStart = CarbonImmutable::instance($entry->start)->setTimezone($tz);
        $shift = (int) $localStart->startOfDay()->diffInDays($target, false);
        $start = $localStart->addDays($shift);

        $this->saveEntry($entry, ['start' => $start, 'end' => $start->addSeconds($entry->duration)]);
    }

    public function updateEntryDuration(int $id, string $value): void
    {
        $entry = $this->findEntry($id);
        $seconds = Duration::parse($value);

        if ($seconds === null) {
            $this->fail('Enter a valid duration, e.g. 1:30 or 1.5');

            return;
        }

        $this->saveEntry($entry, ['end' => CarbonImmutable::instance($entry->start)->addSeconds($seconds)]);
    }

    public function continueEntry(int $id, TimerStartAction $start): void
    {
        $entry = $this->findEntry($id)->load('tags:id');

        $this->attempt(function () use ($start, $entry): void {
            $start($this->user(), $this->workspace, [
                'description' => $entry->description,
                'project_id' => $entry->project_id,
                'task_id' => $entry->task_id,
                'tag_ids' => $entry->tags->pluck('id')->all(),
                'billable' => $entry->billable,
            ]);
            $this->resetForm();
            $this->dispatch('timer-changed');
        });
    }

    public function duplicateEntry(int $id, TimeEntrySaveAction $save): void
    {
        $entry = $this->findEntry($id)->load('tags:id');

        $this->attempt(function () use ($save, $entry): void {
            $save($this->user(), $this->workspace, [
                'description' => $entry->description,
                'project_id' => $entry->project_id,
                'task_id' => $entry->task_id,
                'tag_ids' => $entry->tags->pluck('id')->all(),
                'billable' => $entry->billable,
                'start' => $entry->start,
                'end' => $entry->end,
            ]);
            $this->notify('Time entry duplicated');
        });
    }

    public function deleteEntry(int $id): void
    {
        $this->findEntry($id)->delete();
        $this->notify('Time entry deleted');
    }

    /** @param  list<int>  $ids */
    public function deleteEntries(array $ids): void
    {
        $count = $this->ownEntries()->whereIn('id', array_map('intval', $ids))->delete();
        $this->notify("{$count} time entries deleted");
    }

    public function loadMore(): void
    {
        $this->limit += self::PAGE_SIZE;
    }

    /* ----------------------------------------------------------------- Catalog */

    public function createTag(string $name): ?int
    {
        $name = trim($name);
        if ($name === '' || mb_strlen($name) > 100) {
            return null;
        }

        if (! $this->isAdmin && ! $this->workspace->members_can_create_tags) {
            $this->fail('Only admins can create tags in this workspace.');

            return null;
        }

        $tag = Tag::firstOrCreate(['workspace_id' => $this->workspace->id, 'name' => $name]);
        $this->dispatchCatalog();

        return $tag->id;
    }

    #[On('project-saved')]
    public function onProjectSaved(int $id, ?string $origin = null): void
    {
        $this->dispatchCatalog();

        if ($origin === 'tracker') {
            $this->setFormProject($id);
        }
    }

    /** @return array{projects:list<array<string,mixed>>, tags:list<array{id:int,name:string}>, canCreateProject:bool, canCreateTag:bool} */
    #[Computed]
    public function catalog(): array
    {
        $user = $this->user();
        $favorites = $user->favoriteProjects()->pluck('projects.id')->flip();

        $projects = Project::query()
            ->where('workspace_id', $this->workspace->id)
            ->where('archived', false)
            ->visibleTo($user, $this->workspace)
            ->with(['client:id,name', 'tasks' => fn ($q) => $q->where('done', false)->orderBy('name')->select('id', 'project_id', 'name')])
            ->orderBy('name')
            ->get()
            ->sortBy(fn (Project $p): string => mb_strtolower(($p->client?->name ?? '').'|'.$p->name))
            ->map(fn (Project $p): array => [
                'id' => $p->id,
                'name' => $p->name,
                'color' => $p->color,
                'client' => $p->client?->name,
                'favorite' => $favorites->has($p->id),
                'tasks' => $p->tasks->map(fn ($t): array => ['id' => $t->id, 'name' => $t->name])->values()->all(),
            ])
            ->values()
            ->all();

        return [
            'projects' => $projects,
            'tags' => Tag::query()->where('workspace_id', $this->workspace->id)->where('archived', false)
                ->orderBy('name')->get(['id', 'name'])->map(fn (Tag $t): array => ['id' => $t->id, 'name' => $t->name])->all(),
            'canCreateProject' => $this->isAdmin || $this->workspace->members_can_create_projects,
            'canCreateTag' => $this->isAdmin || $this->workspace->members_can_create_tags,
        ];
    }

    /* ----------------------------------------------------------------- Rendering */

    #[Computed]
    public function running(): ?TimeEntry
    {
        return $this->user()->runningEntry($this->workspace);
    }

    /**
     * Latest entries grouped into weeks → days, extended so the oldest day shown is complete.
     *
     * @return array{weeks: list<array<string,mixed>>, hasMore: bool}
     */
    #[Computed]
    public function timeline(): array
    {
        $tz = $this->user()->timezone;
        $weekStart = $this->user()->week_start;

        $latest = $this->ownEntries()->completed()->latest('start')->limit($this->limit + 1)->get(['id', 'start']);
        $hasMore = $latest->count() > $this->limit;
        $latest = $latest->take($this->limit);

        if ($latest->isEmpty()) {
            return ['weeks' => [], 'hasMore' => false];
        }

        $cutoff = CarbonImmutable::instance($latest->last()->start)->setTimezone($tz)->startOfDay()->utc();
        $entries = $this->ownEntries()->completed()
            ->where('start', '>=', $cutoff)
            ->with(['project:id,name,color,client_id', 'project.client:id,name', 'task:id,name', 'tags:id,name'])
            ->orderByDesc('start')->orderByDesc('id')
            ->get();

        $hasMore = $hasMore && $this->ownEntries()->completed()->where('start', '<', $cutoff)->exists();
        $today = $this->now()->startOfDay();

        $weeks = $entries
            ->groupBy(fn (TimeEntry $e): string => $e->start->setTimezone($tz)->startOfWeek($weekStart)->format('Y-m-d'))
            ->map(function (Collection $weekEntries, string $weekKey) use ($tz, $today, $weekStart): array {
                $weekFrom = CarbonImmutable::parse($weekKey, $tz);
                $thisWeek = $today->startOfWeek($weekStart);

                return [
                    'key' => $weekKey,
                    'label' => match (true) {
                        $weekFrom->equalTo($thisWeek) => 'This week',
                        $weekFrom->equalTo($thisWeek->subWeek()) => 'Last week',
                        default => $weekFrom->format('M j').' – '.$weekFrom->addDays(6)->format('M j, Y'),
                    },
                    'total' => (int) $this->ownEntries()->completed()
                        ->whereBetween('start', [$weekFrom->utc(), $weekFrom->addWeek()->subSecond()->utc()])
                        ->sum('duration'),
                    'days' => $weekEntries
                        ->groupBy(fn (TimeEntry $e): string => $e->start->setTimezone($tz)->format('Y-m-d'))
                        ->map(fn (Collection $dayEntries, string $dayKey): array => [
                            'key' => $dayKey,
                            'label' => $this->dayLabel(CarbonImmutable::parse($dayKey, $tz), $today),
                            'total' => (int) $dayEntries->sum('duration'),
                            'entries' => $dayEntries->values(),
                        ])
                        ->values()
                        ->all(),
                ];
            })
            ->values()
            ->all();

        return ['weeks' => $weeks, 'hasMore' => $hasMore];
    }

    public function render(): mixed
    {
        return view('livewire.tracker', [
            'timezone' => $this->user()->timezone,
            'timeFormat' => $this->timeFormat(),
        ]);
    }

    /* ----------------------------------------------------------------- Internals */

    private function resetForm(): void
    {
        unset($this->running);
        $running = $this->running;

        $this->description = $running->description ?? '';
        $this->projectId = $running?->project_id;
        $this->taskId = $running?->task_id;
        $this->tagIds = $running ? $running->tags()->pluck('tags.id')->all() : [];
        $this->billable = $running ? $running->billable : $this->workspace->billable_by_default;

        $now = $this->now();
        $this->manualDate = $now->format('Y-m-d');
        $this->manualStart = $now->subHour()->format('H:i');
        $this->manualEnd = $now->format('H:i');
    }

    /** @return array{description:string, project_id:?int, task_id:?int, tag_ids:list<int>, billable:bool} */
    private function formData(): array
    {
        return [
            'description' => $this->description,
            'project_id' => $this->projectId,
            'task_id' => $this->taskId,
            'tag_ids' => $this->tagIds,
            'billable' => $this->billable,
        ];
    }

    /** @param  array<string, mixed>  $data */
    private function saveRunning(array $data): void
    {
        if ($running = $this->running) {
            $this->saveEntry($running, $data);
            unset($this->running);
        }
    }

    /** @param  array<string, mixed>  $data */
    private function saveEntry(TimeEntry $entry, array $data, bool $quiet = true): void
    {
        $this->attempt(function () use ($entry, $data, $quiet): void {
            app(TimeEntrySaveAction::class)($this->user(), $this->workspace, $data, $entry);
            if (! $quiet) {
                $this->notify('Time entry updated');
            }
        });
    }

    private function attempt(callable $callback): void
    {
        try {
            $callback();
        } catch (ValidationException $e) {
            $this->fail(collect($e->errors())->flatten()->first() ?? 'Something went wrong.');
        }
    }

    private function fail(string $message): void
    {
        $this->renderKey++;
        $this->notify($message, 'error');
    }

    private function findEntry(int $id): TimeEntry
    {
        return $this->ownEntries()->findOrFail($id);
    }

    /** @return Builder<TimeEntry> */
    private function ownEntries(): Builder
    {
        return TimeEntry::query()->where('workspace_id', $this->workspace->id)->where('user_id', $this->user()->id);
    }

    private function parseLocal(string $date, string $time): ?CarbonImmutable
    {
        $parsed = ClockTime::parse($time);
        if (! $parsed || ! preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            return null;
        }

        return CarbonImmutable::createFromFormat('!Y-m-d', $date, $this->user()->timezone)->setTime($parsed[0], $parsed[1]);
    }

    private function dayLabel(CarbonImmutable $day, CarbonImmutable $today): string
    {
        return match (true) {
            $day->equalTo($today) => 'Today',
            $day->equalTo($today->subDay()) => 'Yesterday',
            default => $day->format('D, M j'),
        };
    }

    private function dispatchCatalog(): void
    {
        unset($this->catalog);
        $this->dispatch('catalog-updated', catalog: $this->catalog);
    }
}
