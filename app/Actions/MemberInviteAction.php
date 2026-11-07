<?php

namespace App\Actions;

use App\Enums\MemberStatus;
use App\Enums\Role;
use App\Models\Membership;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Support\Str;

/**
 * Invites an email address to a workspace. Unknown emails get a password-less user that is
 * activated when the invitation link is accepted.
 */
class MemberInviteAction
{
    public function __invoke(Workspace $workspace, string $email, Role $role = Role::Member): Membership
    {
        $email = mb_strtolower(trim($email));
        $user = User::firstOrCreate(['email' => $email], ['name' => Str::before($email, '@')]);

        $membership = Membership::firstOrNew(['workspace_id' => $workspace->id, 'user_id' => $user->id]);

        if ($membership->exists && $membership->status === MemberStatus::Active) {
            return $membership;
        }

        $membership->fill([
            'role' => $role === Role::Owner ? Role::Admin : $role,
            'status' => MemberStatus::Invited,
            'invite_token' => Str::random(48),
        ])->save();

        return $membership;
    }
}
