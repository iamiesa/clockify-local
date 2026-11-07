<?php

namespace App\Actions;

use App\Enums\MemberStatus;
use App\Enums\Role;
use App\Models\Membership;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Support\Facades\DB;

/**
 * Gives a user without a workspace somewhere to work.
 * Shared mode: joins the organisation's single workspace (creating it for the very first user).
 * Multi-workspace mode: creates a personal workspace.
 */
class WorkspaceJoinAction
{
    public function __construct(
        private WorkspaceCreateAction $createWorkspace,
        private WorkspaceOwnerAssignAction $assignOwner,
    ) {}

    public function __invoke(User $user): Workspace
    {
        if (! config('tracker.shared_workspace')) {
            return ($this->createWorkspace)($user, "{$user->name}'s workspace");
        }

        return DB::transaction(function () use ($user): Workspace {
            $workspace = self::sharedWorkspace();

            if (! $workspace) {
                $workspace = ($this->createWorkspace)($user, (string) config('tracker.workspace_name'));
                $workspace->update(['members_can_create_projects' => true, 'members_can_create_tags' => true]);

                return $workspace->refresh();
            }

            $membership = Membership::query()->firstOrNew(['workspace_id' => $workspace->id, 'user_id' => $user->id]);
            if (! $membership->exists || $membership->status === MemberStatus::Invited) {
                $membership->fill(['role' => $membership->role ?? Role::Member, 'status' => MemberStatus::Active, 'invite_token' => null])->save();
            }

            if (WorkspaceOwnerAssignAction::isConfiguredOwner($user)) {
                ($this->assignOwner)($workspace, $user);
            }

            $user->forceFill(['current_workspace_id' => $workspace->id])->save();
            $user->forgetMemberships();

            return $workspace->refresh();
        });
    }

    /** The organisation workspace: the one owned by the configured owner, otherwise the oldest. */
    public static function sharedWorkspace(): ?Workspace
    {
        $ownerEmail = config('tracker.owner_email');
        $owner = $ownerEmail ? User::query()->whereRaw('LOWER(email) = ?', [mb_strtolower(trim($ownerEmail))])->first() : null;

        return ($owner ? Workspace::query()->where('owner_id', $owner->id)->oldest('id')->first() : null)
            ?? Workspace::query()->oldest('id')->first();
    }
}
