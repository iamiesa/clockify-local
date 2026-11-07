<?php

namespace App\Http\Controllers;

use App\Models\Workspace;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class SessionController extends Controller
{
    public function logout(Request $request): RedirectResponse
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }

    public function switchWorkspace(Request $request, Workspace $workspace): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user->isActiveMemberOf($workspace), 403);

        $user->forceFill(['current_workspace_id' => $workspace->id])->save();

        return redirect()->route('tracker');
    }
}
