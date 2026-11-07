<?php

namespace App\Actions;

use App\Enums\MemberStatus;
use App\Enums\Role;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Support\Facades\DB;

class WorkspaceCreateAction
{
    public function __invoke(User $owner, string $name): Workspace
    {
        return DB::transaction(function () use ($owner, $name): Workspace {
            $workspace = Workspace::create(['name' => $name, 'owner_id' => $owner->id]);

            $workspace->users()->attach($owner->id, [
                'role' => Role::Owner->value,
                'status' => MemberStatus::Active->value,
            ]);

            $owner->forceFill(['current_workspace_id' => $workspace->id])->save();
            $owner->forgetMemberships();

            return $workspace->refresh();
        });
    }
}
