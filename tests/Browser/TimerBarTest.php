<?php

use App\Models\Client;
use App\Models\Project;
use App\Models\Tag;
use App\Models\TimeEntry;

it('lays out the timer bar cleanly in every state', function () {
    [$user, $workspace] = signIn();
    $client = Client::factory()->for($workspace)->create(['name' => 'Bluepeak Labs']);
    $project = Project::factory()->for($workspace)->create(['name' => 'Website Redesign Phase Two', 'client_id' => $client->id, 'color' => '#9c27b0']);
    $tag = Tag::factory()->for($workspace)->create(['name' => 'Urgent']);

    $page = visit('/tracker');
    $page->screenshot(filename: 'bar-idle-desktop');
    $page->on()->mobile();

    $entry = TimeEntry::factory()->for($workspace)->for($user)->running()->create(['project_id' => $project->id, 'description' => 'Running thing']);
    $entry->tags()->attach($tag);

    visit('/tracker')->assertSee('Stop')->assertNoJavaScriptErrors()->screenshot(filename: 'bar-running-desktop');
    visit('/tracker')->on()->mobile()->assertSee('Stop')->screenshot(filename: 'bar-running-mobile');
    $entry->delete();
    visit('/tracker')->on()->mobile()->screenshot(filename: 'bar-idle-mobile');
});
