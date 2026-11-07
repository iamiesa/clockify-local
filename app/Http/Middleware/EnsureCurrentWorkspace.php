<?php

namespace App\Http\Middleware;

use App\Actions\WorkspaceCreateAction;
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
                ?? app(WorkspaceCreateAction::class)($user, "{$user->name}'s workspace");

            $user->forceFill(['current_workspace_id' => $workspace->id])->save();
            $user->setRelation('currentWorkspace', $workspace);
        }

        return $next($request);
    }
}
