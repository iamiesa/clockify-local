<?php

use App\Http\Controllers\SessionController;
use App\Livewire;
use Illuminate\Support\Facades\Route;

Route::middleware('guest')->group(function (): void {
    Route::livewire('/login', Livewire\Auth\Login::class)->name('login');
    Route::livewire('/register', Livewire\Auth\Register::class)->name('register');
});

Route::livewire('/invitations/{token}', Livewire\Auth\AcceptInvitation::class)->name('invitations.accept');

Route::middleware(['auth', 'workspace'])->group(function (): void {
    Route::redirect('/', '/tracker');
    Route::livewire('/tracker', Livewire\Tracker::class)->name('tracker');
    Route::livewire('/dashboard', Livewire\Dashboard::class)->name('dashboard');
    Route::livewire('/reports/{tab?}', Livewire\Reports::class)->whereIn('tab', ['summary', 'detailed', 'weekly'])->name('reports');
    Route::livewire('/projects', Livewire\Projects\Index::class)->name('projects.index');
    Route::livewire('/projects/{project}', Livewire\Projects\Show::class)->name('projects.show');
    Route::livewire('/clients', Livewire\Clients::class)->name('clients');
    Route::livewire('/tags', Livewire\Tags::class)->name('tags');
    Route::livewire('/team', Livewire\Team::class)->name('team');
    Route::livewire('/workspaces', Livewire\Workspaces::class)->name('workspaces');
    Route::livewire('/workspace/settings', Livewire\WorkspaceSettings::class)->name('workspace.settings');
    Route::livewire('/profile', Livewire\Profile::class)->name('profile');

    Route::post('/workspaces/{workspace}/switch', [SessionController::class, 'switchWorkspace'])->name('workspaces.switch');
});

Route::post('/logout', [SessionController::class, 'logout'])->middleware('auth')->name('logout');
