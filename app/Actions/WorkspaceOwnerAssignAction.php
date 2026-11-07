<?php

namespace App\Actions;

use App\Enums\MemberStatus;
use App\Enums\Role;
use App\Models\Membership;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Support\Facades\DB;

/**
 * Makes $user the Owner of $workspace. The previous owner stays on as an Admin.
 */
class WorkspaceOwnerAssignAction
{
    public function __invoke(Workspace $workspace, User $user): void
    {
        DB::transaction(function () use ($workspace, $user): void {
            Membership::query()
                ->where('workspace_id', $workspace->id)
                ->where('role', Role::Owner->value)
                ->where('user_id', '!=', $user->id)
                ->update(['role' => Role::Admin->value]);

            Membership::query()->updateOrCreate(
                ['workspace_id' => $workspace->id, 'user_id' => $user->id],
                ['role' => Role::Owner, 'status' => MemberStatus::Active, 'invite_token' => null],
            );

            $workspace->forceFill(['owner_id' => $user->id])->save();
            $user->forgetMemberships();
        });
    }

    public static function isConfiguredOwner(User $user): bool
    {
        $email = config('tracker.owner_email');

        return is_string($email) && $email !== '' && mb_strtolower(trim($email)) === mb_strtolower($user->email);
    }
}
