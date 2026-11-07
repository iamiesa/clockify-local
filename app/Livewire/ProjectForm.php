<?php

namespace App\Livewire;

use App\Livewire\Concerns\InteractsWithWorkspace;
use App\Models\Client;
use App\Models\Project;
use App\Support\Money;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\On;
use Livewire\Component;

/**
 * Create / edit project modal, shared by the tracker pickers and the projects pages.
 */
class ProjectForm extends Component
{
    use InteractsWithWorkspace;

    public ?int $projectId = null;

    public ?string $origin = null;

    public string $name = '';

    public string $clientId = '';

    public string $newClient = '';

    public string $color = '#03a9f4';

    public bool $isPublic = true;

    public bool $billable = true;

    public string $hourlyRate = '';

    public string $estimateHours = '';

    #[On('open-project-form')]
    public function open(?int $id = null, ?string $name = null, ?string $origin = null): void
    {
        $this->resetValidation();
        $this->origin = $origin;
        $this->projectId = $id;
        $this->newClient = '';

        if ($id) {
            $this->authorizeAdmin();
            $project = $this->findProject($id);
            $this->fill([
                'name' => $project->name,
                'clientId' => (string) ($project->client_id ?? ''),
                'color' => $project->color,
                'isPublic' => $project->is_public,
                'billable' => $project->billable,
                'hourlyRate' => Money::fromCents($project->hourly_rate),
                'estimateHours' => (string) ($project->estimate_hours ?? ''),
            ]);
        } else {
            abort_unless($this->canCreate(), 403);
            $this->fill([
                'name' => (string) $name,
                'clientId' => '',
                'color' => Project::COLORS[array_rand(Project::COLORS)],
                'isPublic' => true,
                'billable' => $this->workspace->billable_by_default,
                'hourlyRate' => '',
                'estimateHours' => '',
            ]);
        }

        $this->dispatch('open-modal', name: 'project-form');
    }

    public function save(): void
    {
        $this->projectId ? $this->authorizeAdmin() : abort_unless($this->canCreate(), 403);
        $ws = $this->workspace->id;

        $data = $this->validate([
            'name' => ['required', 'string', 'max:150'],
            'clientId' => ['nullable', Rule::exists('clients', 'id')->where('workspace_id', $ws)],
            'newClient' => ['nullable', 'string', 'max:150'],
            'color' => ['required', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'hourlyRate' => ['nullable', 'numeric', 'min:0', 'max:100000'],
            'estimateHours' => ['nullable', 'integer', 'min:0', 'max:1000000'],
        ]);

        $project = DB::transaction(function () use ($data, $ws): Project {
            $clientId = $data['clientId'] ?: null;
            if (trim($data['newClient'] ?? '') !== '') {
                $clientId = Client::firstOrCreate(['workspace_id' => $ws, 'name' => trim($data['newClient'])])->id;
            }

            $duplicate = Project::query()->where('workspace_id', $ws)->where('client_id', $clientId)
                ->where('name', trim($data['name']))->when($this->projectId, fn ($q) => $q->whereKeyNot($this->projectId))->exists();
            if ($duplicate) {
                throw ValidationException::withMessages(['name' => 'A project with this name already exists for this client.']);
            }

            $attributes = [
                'name' => trim($data['name']),
                'client_id' => $clientId,
                'color' => $data['color'],
                'is_public' => $this->isPublic,
                'billable' => $this->billable,
                'hourly_rate' => Money::toCents($data['hourlyRate']),
                'estimate_hours' => $data['estimateHours'] === '' || $data['estimateHours'] === null ? null : (int) $data['estimateHours'],
            ];

            if ($this->projectId) {
                $project = $this->findProject($this->projectId);
                $project->update($attributes);

                return $project;
            }

            $project = Project::create([...$attributes, 'workspace_id' => $ws]);
            $project->members()->attach($this->user()->id, ['is_manager' => true]);

            return $project;
        });

        $this->dispatch('close-modal', name: 'project-form');
        $this->dispatch('project-saved', id: $project->id, origin: $this->origin);
        $this->notify($this->projectId ? 'Project updated' : 'Project created');
    }

    private function canCreate(): bool
    {
        return $this->isAdmin || $this->workspace->members_can_create_projects;
    }

    private function findProject(int $id): Project
    {
        return Project::query()->where('workspace_id', $this->workspace->id)->findOrFail($id);
    }

    public function render(): mixed
    {
        return view('livewire.project-form', [
            'clients' => Client::query()->where('workspace_id', $this->workspace->id)->where('archived', false)->orderBy('name')->get(['id', 'name']),
            'colors' => Project::COLORS,
        ]);
    }
}
