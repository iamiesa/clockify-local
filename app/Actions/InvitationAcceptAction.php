<?php

namespace App\Actions;

use App\Enums\MemberStatus;
use App\Models\Membership;
use App\Models\User;

class InvitationAcceptAction
{
    public function __invoke(Membership $membership, User $user): void
    {
        abort_unless($membership->user_id === $user->id, 403);

        $membership->forceFill(['status' => MemberStatus::Active, 'invite_token' => null])->save();
        $user->forceFill(['current_workspace_id' => $membership->workspace_id])->save();
        $user->forgetMemberships();
    }
}
