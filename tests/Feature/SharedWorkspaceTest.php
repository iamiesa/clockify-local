<?php

use App\Enums\MemberStatus;
use App\Enums\Role;
use App\Livewire\Auth\Register;
use App\Livewire\Dashboard;
use App\Livewire\ProjectForm;
use App\Livewire\Projects\Index as ProjectsIndex;
use App\Livewire\Tracker;
use App\Livewire\Workspaces;
use App\Models\Client;
use App\Models\Membership;
use App\Models\Project;
use App\Models\Tag;
use App\Models\TimeEntry;
use App\Models\User;
use App\Models\Workspace;
use Livewire\Livewire;

beforeEach(fn () => config(['tracker.shared_workspace' => true, 'tracker.owner_email' => 'Owner@Example.com', 'tracker.workspace_name' => 'TimeTrack']));

function registerUser(string $name, string $email): User
{
    auth()->logout();
    Livewire::test(Register::class)->set('name', $name)->set('email', $email)->set('password', 'secret-pass')->call('register')->assertHasNoErrors();

    return User::where('email', $email)->sole()->fresh();
}

it('puts every new sign-up in the same shared workspace', function () {
    $first = registerUser('First', 'first@example.com');
    $second = registerUser('Second', 'second@example.com');

    expect(Workspace::count())->toBe(1)
        ->and($second->current_workspace_id)->toBe($first->current_workspace_id)
        ->and($second->roleIn($second->currentWorkspace))->toBe(Role::Member)
        ->and(Workspace::sole()->name)->toBe('TimeTrack');
});

it('makes the configured owner email the Owner even when they sign up later', function () {
    $first = registerUser('First', 'first@example.com');
    expect($first->roleIn($first->currentWorkspace))->toBe(Role::Owner);

    $owner = registerUser('Iesa', 'owner@example.com');
    $workspace = Workspace::sole();

    expect($owner->roleIn($workspace))->toBe(Role::Owner)
        ->and($workspace->owner_id)->toBe($owner->id)
        ->and($first->fresh()->roleIn($workspace))->toBe(Role::Admin);
});

it('promotes the owner email on their next request if they are not owner yet', function () {
    [, $workspace] = signIn();
    $owner = addMember($workspace, Role::Member, ['email' => 'owner@example.com']);

    $this->actingAs($owner)->get('/tracker')->assertOk();

    expect($owner->fresh()->roleIn($workspace))->toBe(Role::Owner);
});

it('shows projects and tags created by anyone to every member', function () {
    $alice = registerUser('Alice', 'alice@example.com');
    $bob = registerUser('Bob', 'bob@example.com');

    $this->actingAs($bob->fresh());
    Livewire::test(ProjectForm::class)->call('open')->set('name', 'Bob project')->call('save')->assertHasNoErrors();
    Livewire::test(Tracker::class)->call('createTag', 'bob-tag');

    $this->actingAs($alice->fresh());
    $catalog = Livewire::test(Tracker::class)->instance()->catalog();

    expect(collect($catalog['projects'])->pluck('name'))->toContain('Bob project')
        ->and(collect($catalog['tags'])->pluck('name'))->toContain('bob-tag');
    Livewire::test(ProjectsIndex::class)->assertSee('Bob project');

    // Formerly-private projects are visible too, and members can track on them.
    $private = Project::factory()->for(Workspace::sole())->private()->create(['name' => 'Legacy private']);
    TimeEntry::query()->delete();
    Livewire::test(Tracker::class)->call('setFormProject', $private->id)->call('startTimer');
    expect(TimeEntry::sole()->project_id)->toBe($private->id);
});

it('lets admins see the team dashboard by default and members only their own', function () {
    [$owner, $workspace] = signIn();
    $member = addMember($workspace);

    Livewire::test(Dashboard::class)->assertViewHas('effectiveScope', 'team');

    $this->actingAs($member);
    Livewire::test(Dashboard::class)->assertViewHas('effectiveScope', 'me');
});

it('does not allow creating or leaving workspaces in shared mode', function () {
    signIn();

    Livewire::test(Workspaces::class)->set('name', 'Mine')->call('create')->assertForbidden();
    expect(Workspace::count())->toBe(1);
});

it('merges existing personal workspaces into the shared one without losing data', function () {
    $ownerUser = User::factory()->create(['email' => 'owner@example.com']);
    $other = User::factory()->create();
    $shared = Workspace::factory()->create(['owner_id' => $ownerUser->id]);
    $personal = Workspace::factory()->create(['owner_id' => $other->id, 'name' => 'Other WS']);

    // Duplicates by name in both workspaces.
    $sharedTag = Tag::factory()->for($shared)->create(['name' => 'Urgent']);
    $dupTag = Tag::factory()->for($personal)->create(['name' => 'urgent']);
    $sharedClient = Client::factory()->for($shared)->create(['name' => 'Acme Labs']);
    $dupClient = Client::factory()->for($personal)->create(['name' => 'Acme Labs']);
    Project::factory()->for($shared)->create(['name' => 'Website', 'client_id' => $sharedClient->id]);
    $dupProject = Project::factory()->for($personal)->private()->create(['name' => 'Website', 'client_id' => $dupClient->id]);
    $entry = TimeEntry::factory()->for($personal)->for($other)->create(['project_id' => $dupProject->id]);
    $entry->tags()->attach($dupTag);

    $this->artisan('tracker:consolidate')->assertSuccessful();

    expect(Workspace::count())->toBe(1)
        ->and($entry->fresh()->workspace_id)->toBe($shared->id)
        ->and($entry->fresh()->tags->pluck('id')->all())->toBe([$sharedTag->id])
        ->and($dupProject->fresh()->workspace_id)->toBe($shared->id)
        ->and($dupProject->fresh()->client_id)->toBe($sharedClient->id)
        ->and($dupProject->fresh()->name)->toBe('Website (Other WS)')
        ->and($dupProject->fresh()->is_public)->toBeTrue()
        ->and(Membership::where('workspace_id', $shared->id)->where('user_id', $other->id)->sole()->status)->toBe(MemberStatus::Active)
        ->and($other->fresh()->current_workspace_id)->toBe($shared->id)
        ->and($ownerUser->fresh()->roleIn($shared))->toBe(Role::Owner);

    // Idempotent.
    $this->artisan('tracker:consolidate')->assertSuccessful();
    expect(Workspace::count())->toBe(1)->and(TimeEntry::count())->toBe(1);
});
