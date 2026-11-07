<?php

namespace App\Livewire\Projects;

use App\Livewire\Concerns\InteractsWithWorkspace;
use App\Models\Project;
use App\Models\Task;
use App\Support\Money;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Attributes\On;
use Livewire\Attributes\Url;
use Livewire\Component;

class Show extends Component
{
    use InteractsWithWorkspace;

    #[Locked]
    public int $projectId;

    #[Url]
    public string $tab = 'tasks';

    public string $newTask = '';

    public string $newTaskEstimate = '';

    public string $note = '';

    public string $memberToAdd = '';

    public function mount(Project $project): void
    {
        abort_unless($project->workspace_id === $this->workspace->id, 404);
        abort_unless(Project::query()->whereKey($project->id)->visibleTo($this->user(), $this->workspace)->exists(), 403);

        $this->projectId = $project->id;
        $this->note = (string) $project->note;
    }

    #[Computed]
    public function project(): Project
    {
        return Project::query()->with('client:id,name')->where('workspace_id', $this->workspace->id)->findOrFail($this->projectId);
    }

    #[Computed]
    public function canManage(): bool
    {
        return $this->isAdmin || (bool) DB::table('project_user')
            ->where('project_id', $this->projectId)->where('user_id', $this->user()->id)->where('is_manager', true)->exists();
    }

    #[On('project-saved')]
    public function refreshProject(): void
    {
        unset($this->project);
    }

    /* ------------------------------------------------------------- Tasks */

    public function addTask(): void
    {
        $this->authorizeManage();
        $this->validate([
            'newTask' => ['required', 'string', 'max:150', Rule::unique('tasks', 'name')->where('project_id', $this->projectId)],
            'newTaskEstimate' => ['nullable', 'integer', 'min:0', 'max:100000'],
        ], [], ['newTask' => 'task name']);

        Task::create([
            'project_id' => $this->projectId,
            'name' => trim($this->newTask),
            'estimate_hours' => $this->newTaskEstimate === '' ? null : (int) $this->newTaskEstimate,
        ]);
        $this->reset('newTask', 'newTaskEstimate');
    }

    public function renameTask(int $id, string $name): void
    {
        $this->authorizeManage();
        $name = trim($name);
        $task = $this->task($id);

        if ($name === '' || Task::where('project_id', $this->projectId)->where('name', $name)->whereKeyNot($id)->exists()) {
            $this->notify('Task names must be unique and not empty.', 'error');

            return;
        }

        $task->update(['name' => mb_substr($name, 0, 150)]);
    }

    public function toggleTaskDone(int $id): void
    {
        $this->authorizeManage();
        $task = $this->task($id);
        $task->update(['done' => ! $task->done]);
    }

    public function deleteTask(int $id): void
    {
        $this->authorizeManage();
        $this->task($id)->delete();
        $this->notify('Task deleted');
    }

    /* ------------------------------------------------------------- Access */

    public function setVisibility(bool $public): void
    {
        $this->authorizeManage();
        if (config('tracker.shared_workspace')) {
            return;
        }
        $this->project->update(['is_public' => $public]);
        unset($this->project);
    }

    public function addMember(): void
    {
        $this->authorizeManage();
        $userId = (int) $this->memberToAdd;
        abort_unless($this->workspace->users()->where('users.id', $userId)->exists(), 422);

        $this->project->members()->syncWithoutDetaching([$userId]);
        $this->reset('memberToAdd');
    }

    public function removeMember(int $userId): void
    {
        $this->authorizeManage();
        $this->project->members()->detach($userId);
    }

    public function setMemberRate(int $userId, string $rate): void
    {
        $this->authorizeAdmin();
        if ($rate !== '' && (! is_numeric($rate) || (float) $rate < 0)) {
            $this->notify('Enter a valid rate.', 'error');

            return;
        }

        $this->project->members()->updateExistingPivot($userId, ['hourly_rate' => Money::toCents($rate)]);
        $this->notify('Rate updated — applies to new and edited entries');
    }

    public function toggleManager(int $userId): void
    {
        $this->authorizeAdmin();
        $current = (bool) DB::table('project_user')->where('project_id', $this->projectId)->where('user_id', $userId)->value('is_manager');
        $this->project->members()->updateExistingPivot($userId, ['is_manager' => ! $current]);
    }

    /* ------------------------------------------------------------- Note */

    public function saveNote(): void
    {
        $this->authorizeManage();
        $this->validate(['note' => 'nullable|string|max:10000']);
        $this->project->update(['note' => $this->note]);
        $this->notify('Note saved');
    }

    private function authorizeManage(): void
    {
        abort_unless($this->canManage, 403);
    }

    private function task(int $id): Task
    {
        return Task::query()->where('project_id', $this->projectId)->findOrFail($id);
    }

    public function render(): mixed
    {
        $project = $this->project;

        $tasks = Task::query()->where('project_id', $project->id)
            ->withSum('timeEntries as tracked', 'duration')
            ->orderBy('done')->orderBy('name')->get();

        $members = $project->members()->orderBy('name')->get();

        $totals = DB::table('time_entries')->where('project_id', $project->id)
            ->selectRaw('COALESCE(SUM(duration),0) as tracked, COALESCE(SUM(CASE WHEN billable THEN duration ELSE 0 END),0) as billable, COALESCE(SUM(CASE WHEN billable THEN duration * 1.0 * hourly_rate ELSE 0 END),0) as amount')
            ->first();

        $byUser = DB::table('time_entries')->join('users', 'users.id', '=', 'time_entries.user_id')
            ->where('project_id', $project->id)
            ->groupBy('users.id', 'users.name', 'users.avatar_color')
            ->selectRaw('users.id, users.name, users.avatar_color, SUM(duration) as tracked')
            ->orderByDesc('tracked')->get();

        return view('livewire.projects.show', [
            'project' => $project,
            'tasks' => $tasks,
            'members' => $members,
            'candidates' => $this->workspace->activeUsers()->whereNotIn('users.id', $members->pluck('id'))->orderBy('name')->get(['users.id', 'users.name']),
            'totals' => $totals,
            'byUser' => $byUser,
        ])->title($project->name);
    }
}
