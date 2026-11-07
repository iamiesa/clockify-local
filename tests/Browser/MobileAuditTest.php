<?php

use App\Models\Project;
use App\Models\User;

/**
 * Visual audit: screenshots every page at phone width using the demo seed data.
 */
it('captures every page at phone width', function () {
    $this->seed();
    $this->actingAs(User::where('email', 'demo@example.com')->sole());
    $project = Project::where('name', 'Website Redesign')->sole();

    $pages = [
        'tracker' => '/tracker', 'dashboard' => '/dashboard?scope=team', 'reports-summary' => '/reports',
        'reports-detailed' => '/reports/detailed', 'reports-weekly' => '/reports/weekly', 'projects' => '/projects',
        'project-show' => "/projects/{$project->id}", 'team' => '/team', 'clients' => '/clients', 'tags' => '/tags',
        'settings' => '/workspace/settings', 'profile' => '/profile',
    ];

    foreach ($pages as $name => $uri) {
        $page = visit($uri)->on()->mobile()->wait(0.4)->assertNoJavaScriptErrors()
            ->assertScript('document.documentElement.scrollWidth <= window.innerWidth', true)
            ->screenshot(fullPage: false, filename: "m-{$name}");
        $page->script('window.scrollTo(0, 640)');
        $page->wait(0.2)->screenshot(fullPage: false, filename: "m-{$name}-2");
    }
});
