<?php

namespace App\Livewire;

use App\Livewire\Concerns\InteractsWithWorkspace;
use App\Support\Money;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Title('Workspace settings')]
class WorkspaceSettings extends Component
{
    use InteractsWithWorkspace;

    public string $name = '';

    public string $currency = 'USD';

    public string $hourlyRate = '';

    public bool $billableByDefault = true;

    public bool $membersCanCreateProjects = false;

    public bool $membersCanCreateTags = true;

    public bool $membersSeeTeamReports = false;

    public bool $forceProject = false;

    public function mount(): void
    {
        $this->authorizeAdmin();
        $ws = $this->workspace;
        $this->fill([
            'name' => $ws->name,
            'currency' => $ws->currency,
            'hourlyRate' => Money::fromCents($ws->hourly_rate),
            'billableByDefault' => $ws->billable_by_default,
            'membersCanCreateProjects' => $ws->members_can_create_projects,
            'membersCanCreateTags' => $ws->members_can_create_tags,
            'membersSeeTeamReports' => $ws->members_see_team_reports,
            'forceProject' => $ws->force_project,
        ]);
    }

    public function save(): void
    {
        $this->authorizeAdmin();
        $this->validate([
            'name' => 'required|string|max:100',
            'currency' => ['required', Rule::in(Money::CURRENCIES)],
            'hourlyRate' => 'nullable|numeric|min:0|max:100000',
        ]);

        $this->workspace->update([
            'name' => trim($this->name),
            'currency' => $this->currency,
            'hourly_rate' => Money::toCents($this->hourlyRate) ?? 0,
            'billable_by_default' => $this->billableByDefault,
            'members_can_create_projects' => $this->membersCanCreateProjects,
            'members_can_create_tags' => $this->membersCanCreateTags,
            'members_see_team_reports' => $this->membersSeeTeamReports,
            'force_project' => $this->forceProject,
        ]);

        $this->notify('Workspace settings saved');
    }

    public function render(): mixed
    {
        return view('livewire.workspace-settings', ['currencies' => Money::CURRENCIES]);
    }
}
