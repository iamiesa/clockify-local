<?php

namespace App\Livewire;

use App\Actions\WorkspaceCreateAction;
use App\Enums\Role;
use App\Livewire\Concerns\InteractsWithWorkspace;
use App\Models\Membership;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Title('Workspaces')]
class Workspaces extends Component
{
    use InteractsWithWorkspace;

    public string $name = '';

    public function create(WorkspaceCreateAction $create): mixed
    {
        abort_if(config('tracker.shared_workspace'), 403, 'This organisation uses a single shared workspace.');
        $this->validate(['name' => 'required|string|max:100']);
        $create($this->user(), trim($this->name));

        return $this->redirect(route('tracker'));
    }

    public function leave(int $workspaceId): mixed
    {
        abort_if(config('tracker.shared_workspace'), 403, 'This organisation uses a single shared workspace.');
        $membership = Membership::query()->where('user_id', $this->user()->id)->where('workspace_id', $workspaceId)->firstOrFail();
        abort_if($membership->role === Role::Owner, 403, 'Owners cannot leave their workspace.');
        $membership->delete();

        return $this->redirect(route('workspaces'));
    }

    public function render(): mixed
    {
        return view('livewire.workspaces', [
            'memberships' => Membership::query()->where('user_id', $this->user()->id)->where('status', 'active')
                ->with('workspace')->get()
                ->sortBy(fn (Membership $m): string => mb_strtolower($m->workspace->name)),
            'memberCounts' => Membership::query()->whereIn('workspace_id', Membership::query()->where('user_id', $this->user()->id)->select('workspace_id'))
                ->selectRaw('workspace_id, COUNT(*) as total')->groupBy('workspace_id')->pluck('total', 'workspace_id'),
        ]);
    }
}
