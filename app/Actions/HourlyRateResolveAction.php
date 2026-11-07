<?php

namespace App\Actions;

use App\Models\Membership;
use App\Models\Project;
use App\Models\Workspace;
use Illuminate\Support\Facades\DB;

/**
 * Resolves the effective hourly rate (cents) with precedence:
 * project member rate > project rate > workspace member rate > workspace rate.
 */
class HourlyRateResolveAction
{
    public function __invoke(Workspace $workspace, int $userId, ?Project $project): int
    {
        if ($project) {
            $projectMemberRate = DB::table('project_user')
                ->where('project_id', $project->id)
                ->where('user_id', $userId)
                ->value('hourly_rate');

            if ($projectMemberRate !== null) {
                return (int) $projectMemberRate;
            }

            if ($project->hourly_rate !== null) {
                return $project->hourly_rate;
            }
        }

        $memberRate = Membership::query()
            ->where('workspace_id', $workspace->id)
            ->where('user_id', $userId)
            ->value('hourly_rate');

        return $memberRate !== null ? (int) $memberRate : $workspace->hourly_rate;
    }
}
