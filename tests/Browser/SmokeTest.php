<?php

use App\Models\Project;
use App\Models\Tag;
use App\Models\TimeEntry;
use App\Models\User;

it('renders every page without JavaScript errors', function () {
    [$user, $workspace] = signIn(['password' => 'password']);
    $project = Project::factory()->for($workspace)->create(['name' => 'Website']);
    TimeEntry::factory()->for($workspace)->for($user)->count(5)->create(['project_id' => $project->id]);

    $pages = visit(['/tracker', '/dashboard', '/reports', '/reports/detailed', '/reports/weekly', '/projects', "/projects/{$project->id}", '/clients', '/tags', '/team', '/workspaces', '/workspace/settings', '/profile']);

    $pages->assertNoJavaScriptErrors()->assertNoConsoleLogs();
});

it('logs in through the form', function () {
    User::factory()->create(['email' => 'me@example.com', 'password' => 'password']);

    visit('/login')
        ->fill('email', 'me@example.com')
        ->fill('password', 'password')
        ->click('button[type=submit], form button')
        ->assertPathIs('/tracker')
        ->assertVisible('input[placeholder="What are you working on?"]')
        ->assertSee('Time Tracker');
});

it('tracks time end to end with project and tag pickers', function () {
    [$user, $workspace] = signIn();
    Project::factory()->for($workspace)->create(['name' => 'Apollo', 'color' => '#2196f3']);
    Tag::factory()->for($workspace)->create(['name' => 'Urgent']);

    $page = visit('/tracker');

    $page->fill('input[placeholder="What are you working on?"]', 'Browser test task')
        ->click('Project')
        ->assertSee('Apollo')
        ->click('Apollo')
        ->wait(0.5)
        ->click('button[title="Tags"]')
        ->assertSee('Urgent')
        ->click('Urgent')
        ->click('Start')
        ->wait(1.5)
        ->assertSee('Stop')
        ->click('Stop')
        ->wait(0.5)
        ->assertSee('Today')
        ->assertNoJavaScriptErrors();

    $entry = TimeEntry::with(['project', 'tags'])->sole();
    expect($entry->description)->toBe('Browser test task')
        ->and($entry->project->name)->toBe('Apollo')
        ->and($entry->end)->not->toBeNull();
});

it('creates a project from the tracker picker modal', function () {
    signIn();

    visit('/tracker')
        ->click('Project')
        ->fill('input[placeholder="Find project or client…"]', 'Fresh project')
        ->click('Create new project')
        ->assertSee('Create new project')
        ->click('button[form=project-form]')
        ->wait(0.8)
        ->assertSee('Fresh project')
        ->assertNoJavaScriptErrors();

    expect(Project::where('name', 'Fresh project')->exists())->toBeTrue();
});

it('is usable at phone width', function () {
    [$user, $workspace] = signIn();
    TimeEntry::factory()->for($workspace)->for($user)->count(3)->create();

    visit('/tracker')->on()->mobile()
        ->assertVisible('input[placeholder="What are you working on?"]')
        ->assertSee('This week')
        ->click('button[aria-label="Toggle navigation"]')
        ->assertSee('Dashboard')
        ->screenshot(filename: 'mobile-nav')
        ->assertNoJavaScriptErrors();
});

it('renders dashboard charts and report interactions', function () {
    [$user, $workspace] = signIn();
    $project = Project::factory()->for($workspace)->create(['name' => 'Charted']);
    TimeEntry::factory()->for($workspace)->for($user)->count(6)->create(['project_id' => $project->id]);

    visit('/dashboard')
        ->wait(0.5)
        ->assertScript("document.querySelectorAll('canvas').length", 2)
        ->assertSee('Charted')
        ->assertNoJavaScriptErrors()
        ->screenshot(filename: 'dashboard');

    visit('/reports')
        ->wait(0.5)
        ->assertScript("document.querySelectorAll('canvas').length", 2)
        ->click('This week')
        ->click('Last week')
        ->wait(0.8)
        ->assertSee('Last week')
        ->assertNoJavaScriptErrors()
        ->screenshot(filename: 'reports');
});
