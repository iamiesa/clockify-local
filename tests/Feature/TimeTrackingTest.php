<?php

use App\Actions\TimeEntrySaveAction;
use App\Actions\TimerStartAction;
use App\Actions\TimerStopAction;
use App\Enums\Role;
use App\Livewire\Tracker;
use App\Models\Project;
use App\Models\Tag;
use App\Models\TimeEntry;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;

it('starts and stops a timer, snapshotting duration and rate', function () {
    [$user, $workspace] = signIn();
    $workspace->update(['hourly_rate' => 5000]);
    $project = Project::factory()->for($workspace)->create(['billable' => true]);

    $this->travelTo(CarbonImmutable::parse('2026-11-02 09:00:00'));
    $entry = app(TimerStartAction::class)($user, $workspace->fresh(), ['description' => 'Build', 'project_id' => $project->id]);
    expect($entry->isRunning())->toBeTrue()->and($entry->billable)->toBeTrue();

    $this->travelTo(CarbonImmutable::parse('2026-11-02 10:30:00'));
    $stopped = app(TimerStopAction::class)($user, $workspace->fresh());

    expect($stopped->duration)->toBe(5400)
        ->and($stopped->hourly_rate)->toBe(5000)
        ->and($stopped->amount())->toBe(7500);
});

it('never keeps two running timers', function () {
    [$user, $workspace] = signIn();

    app(TimerStartAction::class)($user, $workspace, ['description' => 'first']);
    $this->travel(5)->minutes();
    app(TimerStartAction::class)($user, $workspace, ['description' => 'second']);

    expect(TimeEntry::running()->count())->toBe(1)
        ->and(TimeEntry::running()->first()->description)->toBe('second')
        ->and(TimeEntry::where('description', 'first')->first()->duration)->toBe(300);
});

it('resolves hourly rates by precedence', function () {
    [$owner, $workspace] = signIn();
    $workspace->update(['hourly_rate' => 1000]);
    $member = addMember($workspace, Role::Member);
    $project = Project::factory()->for($workspace)->create(['hourly_rate' => null]);
    $save = fn () => app(TimeEntrySaveAction::class)($member, $workspace->fresh(), [
        'project_id' => $project->id, 'start' => now()->subHour(), 'end' => now(),
    ]);

    expect($save()->hourly_rate)->toBe(1000);

    DB::table('workspace_user')->where('user_id', $member->id)->update(['hourly_rate' => 2000]);
    expect($save()->hourly_rate)->toBe(2000);

    $project->update(['hourly_rate' => 3000]);
    expect($save()->hourly_rate)->toBe(3000);

    $project->members()->attach($member->id, ['hourly_rate' => 4000]);
    expect($save()->hourly_rate)->toBe(4000);
});

it('rejects projects from other workspaces and private projects without access', function () {
    [$user, $workspace] = signIn();
    $foreign = Project::factory()->create();
    $member = addMember($workspace);
    $private = Project::factory()->for($workspace)->private()->create();

    expect(fn () => app(TimeEntrySaveAction::class)($user, $workspace, ['project_id' => $foreign->id, 'start' => now()->subHour(), 'end' => now()]))
        ->toThrow(ValidationException::class);
    expect(fn () => app(TimeEntrySaveAction::class)($member, $workspace, ['project_id' => $private->id, 'start' => now()->subHour(), 'end' => now()]))
        ->toThrow(ValidationException::class);
});

it('ignores tags from other workspaces', function () {
    [$user, $workspace] = signIn();
    $own = Tag::factory()->for($workspace)->create();
    $foreign = Tag::factory()->create();

    $entry = app(TimeEntrySaveAction::class)($user, $workspace, ['tag_ids' => [$own->id, $foreign->id], 'start' => now()->subHour(), 'end' => now()]);

    expect($entry->tags->pluck('id')->all())->toBe([$own->id]);
});

it('rejects end before start and enforces required project', function () {
    [$user, $workspace] = signIn();

    expect(fn () => app(TimeEntrySaveAction::class)($user, $workspace, ['start' => now(), 'end' => now()->subMinute()]))
        ->toThrow(ValidationException::class);

    $workspace->update(['force_project' => true]);
    expect(fn () => app(TimeEntrySaveAction::class)($user, $workspace->fresh(), ['start' => now()->subHour(), 'end' => now()]))
        ->toThrow(ValidationException::class);
});

it('starts, edits and stops the timer from the tracker', function () {
    [$user, $workspace] = signIn();
    $project = Project::factory()->for($workspace)->create();

    Livewire::test(Tracker::class)
        ->set('description', 'Writing tests')
        ->call('setFormProject', $project->id)
        ->call('startTimer')
        ->assertDispatched('timer-changed')
        ->set('description', 'Writing more tests')
        ->call('stopTimer');

    $entry = TimeEntry::sole();
    expect($entry->description)->toBe('Writing more tests')
        ->and($entry->project_id)->toBe($project->id)
        ->and($entry->end)->not->toBeNull();
});

it('adds manual entries in the user timezone, rolling overnight ranges to the next day', function () {
    [$user] = signIn(['timezone' => 'Asia/Kolkata']);

    Livewire::test(Tracker::class)
        ->set('mode', 'manual')
        ->set('manualDate', '2026-11-03')
        ->set('manualStart', '22:00')
        ->set('manualEnd', '1:30')
        ->call('addManualEntry');

    $entry = TimeEntry::sole();
    expect($entry->start->toDateTimeString())->toBe('2026-11-03 16:30:00')
        ->and($entry->duration)->toBe(12600);
});

it('edits times, date and duration inline', function () {
    [$user, $workspace] = signIn(['timezone' => 'UTC']);
    $entry = TimeEntry::factory()->for($workspace)->for($user)->create([
        'start' => '2026-11-03 09:00:00', 'end' => '2026-11-03 10:00:00', 'duration' => 3600,
    ]);

    $component = Livewire::test(Tracker::class)->call('updateEntryTimes', $entry->id, '9:30', '11:00');
    expect($entry->fresh()->duration)->toBe(5400);

    $component->call('updateEntryDuration', $entry->id, '2:15');
    expect($entry->fresh()->end->toDateTimeString())->toBe('2026-11-03 11:45:00');

    $component->call('updateEntryDate', $entry->id, '2026-11-05');
    expect($entry->fresh()->start->toDateTimeString())->toBe('2026-11-05 09:30:00')
        ->and($entry->fresh()->duration)->toBe(8100);

    $component->call('updateEntryTimes', $entry->id, 'nonsense', '11:00')->assertDispatched('notify');
    expect($entry->fresh()->duration)->toBe(8100);
});

it('continues, duplicates and deletes entries', function () {
    [$user, $workspace] = signIn();
    $tag = Tag::factory()->for($workspace)->create();
    $entry = TimeEntry::factory()->for($workspace)->for($user)->create(['description' => 'Repeat me']);
    $entry->tags()->attach($tag);

    Livewire::test(Tracker::class)->call('continueEntry', $entry->id)->call('duplicateEntry', $entry->id);

    expect(TimeEntry::where('description', 'Repeat me')->count())->toBe(3)
        ->and(TimeEntry::running()->sole()->tags->pluck('id')->all())->toBe([$tag->id]);

    Livewire::test(Tracker::class)->call('deleteEntries', TimeEntry::pluck('id')->all());
    expect(TimeEntry::count())->toBe(0);
});

it('cannot touch another user\'s entries from the tracker', function () {
    [, $workspace] = signIn();
    $other = addMember($workspace);
    $entry = TimeEntry::factory()->for($workspace)->for($other)->create();

    Livewire::test(Tracker::class)->call('deleteEntry', $entry->id)->assertNotFound();
    Livewire::test(Tracker::class)->call('deleteEntries', [$entry->id]);

    expect($entry->fresh())->not->toBeNull();
});

it('groups the timeline into weeks and days', function () {
    [$user, $workspace] = signIn(['timezone' => 'UTC']);
    $this->travelTo(CarbonImmutable::parse('2026-11-05 12:00:00'));
    TimeEntry::factory()->for($workspace)->for($user)->create(['start' => '2026-11-05 08:00', 'end' => '2026-11-05 09:00', 'duration' => 3600]);
    TimeEntry::factory()->for($workspace)->for($user)->create(['start' => '2026-11-05 10:00', 'end' => '2026-11-05 10:30', 'duration' => 1800]);
    TimeEntry::factory()->for($workspace)->for($user)->create(['start' => '2026-10-28 10:00', 'end' => '2026-10-28 11:00', 'duration' => 3600]);

    $timeline = Livewire::test(Tracker::class)->instance()->timeline();

    expect($timeline['weeks'])->toHaveCount(2)
        ->and($timeline['weeks'][0]['label'])->toBe('This week')
        ->and($timeline['weeks'][0]['days'][0]['label'])->toBe('Today')
        ->and($timeline['weeks'][0]['days'][0]['total'])->toBe(5400)
        ->and($timeline['weeks'][1]['label'])->toBe('Last week');
});

it('lets members create tags only when allowed', function () {
    [, $workspace] = signIn();
    $member = addMember($workspace);
    $this->actingAs($member);

    expect(Livewire::test(Tracker::class)->call('createTag', 'Focus')->effects['returns'][0] ?? null)->toBeInt();

    $workspace->update(['members_can_create_tags' => false]);
    $this->actingAs($member->fresh());
    Livewire::test(Tracker::class)->call('createTag', 'Blocked');
    expect(Tag::where('name', 'Blocked')->exists())->toBeFalse();
});
