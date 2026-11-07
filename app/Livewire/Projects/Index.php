<?php

namespace App\Livewire\Projects;

use App\Livewire\Concerns\InteractsWithWorkspace;
use App\Models\Client;
use App\Models\Project;
use Livewire\Attributes\On;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

#[Title('Projects')]
class Index extends Component
{
    use InteractsWithWorkspace, WithPagination;

    #[Url(except: 'active')]
    public string $status = 'active';

    #[Url(except: '')]
    public string $clientId = '';

    #[Url(except: '')]
    public string $access = '';

    #[Url(except: '')]
    public string $billing = '';

    #[Url(except: '')]
    public string $search = '';

    #[Url(except: 'name')]
    public string $sort = 'name';

    public function updated(): void
    {
        $this->resetPage();
    }

    public function sortBy(string $column): void
    {
        $this->sort = in_array($column, ['name', 'client', 'tracked'], true) ? $column : 'name';
    }

    #[On('project-saved')]
    public function refreshList(): void {}

    public function toggleFavorite(int $id): void
    {
        $project = $this->find($id, adminOnly: false);
        $this->user()->favoriteProjects()->toggle($project->id);
    }

    public function archive(int $id, bool $archived = true): void
    {
        $this->find($id)->update(['archived' => $archived]);
        $this->notify($archived ? 'Project archived' : 'Project restored');
    }

    public function delete(int $id): void
    {
        $project = $this->find($id);
        if (! $project->archived) {
            $this->notify('Archive the project before deleting it.', 'error');

            return;
        }
        $project->delete();
        $this->notify('Project deleted');
    }

    private function find(int $id, bool $adminOnly = true): Project
    {
        if ($adminOnly) {
            $this->authorizeAdmin();
        }

        return Project::query()->where('workspace_id', $this->workspace->id)->visibleTo($this->user(), $this->workspace)->findOrFail($id);
    }

    public function render(): mixed
    {
        $user = $this->user();
        $search = trim($this->search);

        $projects = Project::query()
            ->where('projects.workspace_id', $this->workspace->id)
            ->visibleTo($user, $this->workspace)
            ->with(['client:id,name', 'members:id,name,avatar_color'])
            ->select('projects.*')
            ->selectSub(fn ($q) => $q->from('time_entries')->whereColumn('project_id', 'projects.id')->selectRaw('COALESCE(SUM(duration),0)'), 'tracked')
            ->selectSub(fn ($q) => $q->from('time_entries')->whereColumn('project_id', 'projects.id')->where('billable', true)->selectRaw('COALESCE(SUM(duration * 1.0 * hourly_rate),0) / 3600.0'), 'amount')
            ->selectSub(fn ($q) => $q->from('project_favorites')->whereColumn('project_id', 'projects.id')->where('user_id', $user->id)->selectRaw('COUNT(*)'), 'is_favorite')
            ->when($this->status !== 'all', fn ($q) => $q->where('projects.archived', $this->status === 'archived'))
            ->when($this->clientId === 'none', fn ($q) => $q->whereNull('projects.client_id'))
            ->when(ctype_digit($this->clientId), fn ($q) => $q->where('projects.client_id', (int) $this->clientId))
            ->when($this->access !== '', fn ($q) => $q->where('projects.is_public', $this->access === 'public'))
            ->when($this->billing !== '', fn ($q) => $q->where('projects.billable', $this->billing === 'billable'))
            ->when($search !== '', fn ($q) => $q->whereLike('projects.name', '%'.addcslashes($search, '%_\\').'%'))
            ->orderByDesc('is_favorite')
            ->when($this->sort === 'client', fn ($q) => $q->orderBy(Client::select('name')->whereColumn('clients.id', 'projects.client_id')))
            ->when($this->sort === 'tracked', fn ($q) => $q->orderByDesc('tracked'))
            ->orderBy('projects.name')
            ->paginate(50);

        return view('livewire.projects.index', [
            'projects' => $projects,
            'clients' => Client::query()->where('workspace_id', $this->workspace->id)->orderBy('name')->get(['id', 'name']),
            'canCreate' => $this->isAdmin || $this->workspace->members_can_create_projects,
        ]);
    }
}
