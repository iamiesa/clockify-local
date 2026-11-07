<?php

use App\Enums\MemberStatus;
use App\Enums\Role;
use App\Livewire\Auth\AcceptInvitation;
use App\Livewire\Auth\Register;
use App\Livewire\Clients;
use App\Livewire\ProjectForm;
use App\Livewire\Projects\Index as ProjectsIndex;
use App\Livewire\Tags;
use App\Livewire\Team;
use App\Models\Membership;
use App\Models\Project;
use App\Models\User;
use App\Models\Workspace;
use Livewire\Livewire;

it('registers a user with a personal workspace', function () {
    Livewire::test(Register::class)
        ->set('name', 'Jane Doe')->set('email', 'Jane@Example.com')->set('password', 'secret-pass')->set('timezone', 'Europe/London')
        ->call('register')
        ->assertRedirect(route('tracker'));

    $user = User::where('email', 'jane@example.com')->sole();
    expect($user->currentWorkspace->name)->toBe("Jane Doe's workspace")
        ->and($user->roleIn($user->currentWorkspace))->toBe(Role::Owner);
});

it('invites a new member who accepts with a password', function () {
    [, $workspace] = signIn();

    Livewire::test(Team::class)->set('inviteEmails', 'new@example.com, bad-email')->call('invite')->assertHasErrors('inviteEmails');
    Livewire::test(Team::class)->set('inviteEmails', 'new@example.com')->set('inviteRole', 'admin')->call('invite');

    $membership = Membership::where('workspace_id', $workspace->id)->where('status', 'invited')->sole();
    auth()->logout();

    Livewire::test(AcceptInvitation::class, ['token' => $membership->invite_token])
        ->set('name', 'New Person')->set('password', 'password123')
        ->call('accept')
        ->assertRedirect(route('tracker'));

    expect($membership->fresh()->status)->toBe(MemberStatus::Active)
        ->and($membership->fresh()->role)->toBe(Role::Admin)
        ->and(auth()->user()->email)->toBe('new@example.com');
});

it('prevents members from managing the team, and admins from editing the owner', function () {
    [$owner, $workspace] = signIn();
    $member = addMember($workspace);
    $admin = addMember($workspace, Role::Admin);
    $ownerMembership = $owner->membershipIn($workspace);

    $this->actingAs($member);
    Livewire::test(Team::class)->call('openInvite')->assertForbidden();

    $this->actingAs($admin);
    Livewire::test(Team::class)->call('setRole', $ownerMembership->id, 'member')->assertForbidden();
    Livewire::test(Team::class)->call('setRole', $member->membershipIn($workspace)->id, 'admin');
    expect($member->fresh()->roleIn($workspace))->toBe(Role::Admin);
});

it('creates projects with an inline client and enforces member permissions', function () {
    [$owner, $workspace] = signIn();

    Livewire::test(ProjectForm::class)->call('open')->set('name', 'Apollo')->set('newClient', 'NASA')->call('save')->assertDispatched('project-saved');
    $project = Project::with('client')->where('name', 'Apollo')->sole();
    expect($project->client->name)->toBe('NASA');

    Livewire::test(ProjectForm::class)->call('open')->set('name', 'Apollo')->set('newClient', 'NASA')->call('save')->assertHasErrors('name');

    $member = addMember($workspace);
    $this->actingAs($member);
    Livewire::test(ProjectForm::class)->call('open')->assertForbidden();

    $workspace->update(['members_can_create_projects' => true]);
    $this->actingAs($member->fresh());
    Livewire::test(ProjectForm::class)->call('open')->set('name', 'Member project')->call('save');
    expect(Project::where('name', 'Member project')->sole()->members->pluck('id')->all())->toBe([$member->id]);
});

it('archives before deleting projects and hides private projects from non-members', function () {
    [$owner, $workspace] = signIn();
    $project = Project::factory()->for($workspace)->private()->create(['name' => 'Secret']);

    Livewire::test(ProjectsIndex::class)->call('delete', $project->id)->assertDispatched('notify');
    expect($project->fresh())->not->toBeNull();
    Livewire::test(ProjectsIndex::class)->call('archive', $project->id)->call('delete', $project->id);
    expect($project->fresh())->toBeNull();

    $hidden = Project::factory()->for($workspace)->private()->create(['name' => 'Hidden']);
    $this->actingAs(addMember($workspace));
    Livewire::test(ProjectsIndex::class)->assertDontSee('Hidden');
    $this->get(route('projects.show', $hidden))->assertForbidden();
});

it('manages clients and tags with unique names per workspace', function () {
    signIn();

    Livewire::test(Clients::class)->set('newName', 'Northpoint')->call('create')->assertHasNoErrors()
        ->set('newName', 'Northpoint')->call('create')->assertHasErrors('newName');
    Livewire::test(Tags::class)->set('newName', 'urgent')->call('create')->assertHasNoErrors()
        ->set('newName', 'urgent')->call('create')->assertHasErrors('newName');
});

it('isolates workspaces when switching', function () {
    [$user, $first] = signIn();
    $second = Workspace::factory()->create();

    $this->post(route('workspaces.switch', $second))->assertForbidden();

    $second->users()->attach($user->id, ['role' => 'member', 'status' => 'active']);
    $this->actingAs($user->fresh())->post(route('workspaces.switch', $second))->assertRedirect(route('tracker'));
    expect($user->fresh()->current_workspace_id)->toBe($second->id);
});

it('accepts legacy browser timezones at sign-up and falls back to UTC for junk', function (string $browserTimezone, string $stored) {
    $email = 'tz-'.md5($browserTimezone).'@example.com';

    Livewire::test(Register::class)
        ->set('name', 'Tz User')->set('email', $email)->set('password', 'secret-pass')->set('timezone', $browserTimezone)
        ->call('register')
        ->assertHasNoErrors()
        ->assertRedirect(route('tracker'));

    expect(User::where('email', $email)->sole()->timezone)->toBe($stored);
})->with([
    'Chrome in India' => ['Asia/Calcutta', 'Asia/Kolkata'],
    'canonical' => ['Europe/London', 'Europe/London'],
    'legacy but valid' => ['US/Eastern', 'US/Eastern'],
    'garbage' => ['Not/AZone', 'UTC'],
    'empty' => ['', 'UTC'],
]);
