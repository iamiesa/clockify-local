<?php

use App\Models\Project;
use App\Models\TimeEntry;
use App\Models\User;

it('redirects guests to login', function (string $uri) {
    $this->get($uri)->assertRedirect('/login');
})->with(['/tracker', '/dashboard', '/reports', '/projects', '/team', '/clients', '/tags', '/profile']);

it('renders guest pages', function () {
    $this->get('/login')->assertOk()->assertSee('Log in');
    $this->get('/register')->assertOk()->assertSee('Sign up');
});

it('renders every authenticated page', function (string $uri) {
    [$user, $workspace] = signIn();
    $project = Project::factory()->for($workspace)->create();
    TimeEntry::factory()->for($workspace)->for($user)->create(['project_id' => $project->id]);
    TimeEntry::factory()->for($workspace)->for($user)->running()->create();

    $this->get(str_replace('{project}', (string) $project->id, $uri))->assertOk();
})->with([
    '/tracker', '/dashboard', '/dashboard?scope=team', '/reports', '/reports/summary', '/reports/detailed', '/reports/weekly',
    '/projects', '/projects/{project}', '/projects/{project}?tab=access', '/projects/{project}?tab=status', '/projects/{project}?tab=note',
    '/clients', '/tags', '/team', '/team?tab=groups', '/workspaces', '/workspace/settings', '/profile',
]);

it('blocks members from workspace settings', function () {
    [, $workspace] = signIn();
    $member = addMember($workspace);

    $this->actingAs($member)->get('/workspace/settings')->assertForbidden();
});

it('creates a workspace on the fly for users without one', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->get('/tracker')->assertOk();
    expect($user->fresh()->current_workspace_id)->not->toBeNull();
});
