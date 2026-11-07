<?php

namespace App\Actions;

use App\Models\Project;
use App\Models\Tag;
use App\Models\Task;
use App\Models\TimeEntry;
use App\Models\User;
use App\Models\Workspace;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Creates or updates a time entry, guaranteeing every reference belongs to the workspace,
 * the duration/rate snapshot is consistent and the actor is allowed to touch it.
 */
class TimeEntrySaveAction
{
    public function __construct(private HourlyRateResolveAction $resolveRate) {}

    /**
     * @param  array{description?:string|null, project_id?:int|null, task_id?:int|null, tag_ids?:array<int>, billable?:bool, start?:CarbonInterface, end?:CarbonInterface|null}  $data
     */
    public function __invoke(User $actor, Workspace $workspace, array $data, ?TimeEntry $entry = null): TimeEntry
    {
        if ($entry && ($entry->workspace_id !== $workspace->id || ($entry->user_id !== $actor->id && ! $actor->isAdminOf($workspace)))) {
            abort(403);
        }

        $entry ??= new TimeEntry(['workspace_id' => $workspace->id, 'user_id' => $actor->id]);
        $projectChanged = array_key_exists('project_id', $data) && (int) $data['project_id'] !== (int) $entry->project_id;

        if (array_key_exists('description', $data)) {
            $entry->description = mb_substr(trim((string) $data['description']), 0, 3000);
        }

        $project = $entry->project_id ? Project::find($entry->project_id) : null;

        if (array_key_exists('project_id', $data)) {
            $project = $this->resolveProject($actor, $workspace, $data['project_id'] ? (int) $data['project_id'] : null);
            $entry->project_id = $project?->id;
            $entry->task_id = $project ? $this->resolveTaskId($project, $data['task_id'] ?? null) : null;
        }

        if (array_key_exists('billable', $data)) {
            $entry->billable = (bool) $data['billable'];
        } elseif (! $entry->exists || $projectChanged) {
            $entry->billable = $project ? $project->billable : $workspace->billable_by_default;
        }

        if (array_key_exists('start', $data)) {
            $entry->start = $data['start']->copy()->utc()->startOfSecond();
        }

        if (array_key_exists('end', $data)) {
            $entry->end = $data['end']?->copy()->utc()->startOfSecond();
        }

        $this->guardTimes($entry);

        if ($entry->end !== null && $workspace->force_project && $entry->project_id === null) {
            throw ValidationException::withMessages(['project_id' => 'A project is required in this workspace.']);
        }

        $entry->duration = $entry->end ? (int) $entry->start->diffInSeconds($entry->end) : 0;
        $entry->hourly_rate = ($this->resolveRate)($workspace, $entry->user_id, $project);

        return DB::transaction(function () use ($entry, $data, $workspace): TimeEntry {
            $entry->save();

            if (array_key_exists('tag_ids', $data)) {
                $tagIds = Tag::query()
                    ->where('workspace_id', $workspace->id)
                    ->whereIn('id', array_map('intval', $data['tag_ids']))
                    ->pluck('id');
                $entry->tags()->sync($tagIds);
            }

            return $entry->load(['project.client', 'task', 'tags']);
        });
    }

    private function resolveProject(User $actor, Workspace $workspace, ?int $projectId): ?Project
    {
        if ($projectId === null) {
            return null;
        }

        $project = Project::query()
            ->where('workspace_id', $workspace->id)
            ->visibleTo($actor, $workspace)
            ->find($projectId);

        if (! $project) {
            throw ValidationException::withMessages(['project_id' => 'The selected project is not available.']);
        }

        return $project;
    }

    private function resolveTaskId(Project $project, mixed $taskId): ?int
    {
        if (! $taskId) {
            return null;
        }

        return Task::query()->where('project_id', $project->id)->whereKey((int) $taskId)->value('id');
    }

    private function guardTimes(TimeEntry $entry): void
    {
        if ($entry->start === null) {
            throw ValidationException::withMessages(['start' => 'A start time is required.']);
        }

        if ($entry->end !== null && $entry->end->lessThan($entry->start)) {
            throw ValidationException::withMessages(['end' => 'The end time must be after the start time.']);
        }

        if ($entry->end !== null && $entry->start->diffInSeconds($entry->end) > 999 * 3600) {
            throw ValidationException::withMessages(['end' => 'A time entry cannot be longer than 999 hours.']);
        }
    }
}
