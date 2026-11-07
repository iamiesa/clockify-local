<?php

use App\Livewire\Reports;
use App\Models\Project;
use App\Models\Tag;
use App\Models\TimeEntry;
use App\Reports\ReportBuilder;
use App\Reports\ReportFilters;
use App\Support\DateRange;
use Carbon\CarbonImmutable;
use Livewire\Livewire;

function weekRange(): DateRange
{
    return DateRange::fromStrings('2026-11-02', '2026-11-08', 'UTC');
}

it('limits members to their own data unless team reports are enabled', function () {
    [$owner, $workspace] = signIn();
    $member = addMember($workspace);
    TimeEntry::factory()->for($workspace)->for($owner)->create(['start' => '2026-11-03 09:00', 'end' => '2026-11-03 10:00', 'duration' => 3600]);
    TimeEntry::factory()->for($workspace)->for($member)->create(['start' => '2026-11-03 09:00', 'end' => '2026-11-03 09:30', 'duration' => 1800]);

    expect((new ReportBuilder($workspace, $owner, new ReportFilters(weekRange())))->totals()['duration'])->toBe(5400)
        ->and((new ReportBuilder($workspace, $member, new ReportFilters(weekRange())))->totals()['duration'])->toBe(1800);

    $workspace->update(['members_see_team_reports' => true]);
    expect((new ReportBuilder($workspace->fresh(), $member, new ReportFilters(weekRange())))->totals()['duration'])->toBe(5400);
});

it('filters by project including "without project" and excludes running entries', function () {
    [$user, $workspace] = signIn();
    $project = Project::factory()->for($workspace)->create();
    TimeEntry::factory()->for($workspace)->for($user)->create(['project_id' => $project->id, 'start' => '2026-11-03 09:00', 'end' => '2026-11-03 10:00', 'duration' => 3600]);
    TimeEntry::factory()->for($workspace)->for($user)->create(['project_id' => null, 'start' => '2026-11-04 09:00', 'end' => '2026-11-04 09:15', 'duration' => 900]);
    TimeEntry::factory()->for($workspace)->for($user)->create(['start' => '2026-11-04 12:00', 'end' => null, 'duration' => 0]);

    $total = fn (array $ids) => (new ReportBuilder($workspace, $user, new ReportFilters(weekRange(), projectIds: $ids)))->totals()['duration'];

    expect($total([]))->toBe(4500)
        ->and($total([$project->id]))->toBe(3600)
        ->and($total([0]))->toBe(900)
        ->and($total([$project->id, 0]))->toBe(4500);
});

it('counts multi-tag entries under each tag and buckets days in the viewer timezone', function () {
    [$user, $workspace] = signIn(['timezone' => 'Asia/Kolkata']);
    [$a, $b] = Tag::factory()->for($workspace)->count(2)->create();
    $entry = TimeEntry::factory()->for($workspace)->for($user)->create(['start' => '2026-11-03 20:00', 'end' => '2026-11-03 21:00', 'duration' => 3600]);
    $entry->tags()->attach([$a->id, $b->id]);

    $builder = new ReportBuilder($workspace, $user->fresh(), new ReportFilters(DateRange::fromStrings('2026-11-02', '2026-11-08', 'Asia/Kolkata')));
    $byTag = collect($builder->summary('tag'))->pluck('duration', 'key');
    $byDate = collect($builder->summary('date'))->pluck('duration', 'key');

    expect($byTag[$a->id])->toBe(3600)->and($byTag[$b->id])->toBe(3600)
        ->and($byDate->keys()->all())->toBe(['2026-11-04']);
});

it('renders report tabs and exports CSV', function (string $tab) {
    [$user, $workspace] = signIn();
    TimeEntry::factory()->for($workspace)->for($user)->create(['start' => now()->startOfWeek()->addHours(9), 'end' => now()->startOfWeek()->addHours(10), 'duration' => 3600]);

    Livewire::test(Reports::class, ['tab' => $tab])
        ->assertOk()
        ->call('export')
        ->assertFileDownloaded();
})->with(['summary', 'detailed', 'weekly']);

it('shifts week and month ranges', function () {
    signIn();
    $component = Livewire::test(Reports::class)->call('applyPreset', 'this_month');
    $from = CarbonImmutable::parse($component->get('from'));

    $component->call('shiftRange', -1);
    expect($component->get('from'))->toBe($from->subMonthNoOverflow()->format('Y-m-d'))
        ->and($component->get('to'))->toBe($from->subMonthNoOverflow()->endOfMonth()->format('Y-m-d'));
});
