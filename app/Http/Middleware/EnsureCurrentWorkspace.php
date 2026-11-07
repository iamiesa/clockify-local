<?php

namespace App\Http\Middleware;

use App\Actions\WorkspaceJoinAction;
use App\Actions\WorkspaceOwnerAssignAction;
use App\Enums\Role;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Guarantees the authenticated user has a valid, active current workspace.
 */
class EnsureCurrentWorkspace
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        $workspace = $user->currentWorkspace;

        if (! $workspace || ! $user->isActiveMemberOf($workspace)) {
            $workspace = $user->activeWorkspaces()->oldest('workspaces.id')->first()
                ?? app(WorkspaceJoinAction::class)($user);

            $user->forceFill(['current_workspace_id' => $workspace->id])->save();
            $user->setRelation('currentWorkspace', $workspace);
        }

        // The configured owner email always holds the Owner role in the shared workspace.
        if (config('tracker.shared_workspace') && WorkspaceOwnerAssignAction::isConfiguredOwner($user) && $user->roleIn($workspace) !== Role::Owner) {
            app(WorkspaceOwnerAssignAction::class)($workspace, $user);
        }

        return $next($request);
    }
}
