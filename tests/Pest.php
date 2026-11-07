<?php

use App\Actions\WorkspaceCreateAction;
use App\Enums\Role;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a specific PHPUnit test
| case class. By default, that class is "PHPUnit\Framework\TestCase". Of course, you may
| need to change it using the "pest()" function to bind different classes or traits.
|
*/

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature', 'Browser');

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
|
| When you're writing tests, you often need to check that values meet certain conditions. The
| "expect()" function gives you access to a set of "expectations" methods that you can use
| to assert different things. Of course, you may extend the Expectation API at any time.
|
*/

expect()->extend('toBeOne', function () {
    return $this->toBe(1);
});

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
|
| While Pest is very powerful out-of-the-box, you may have some testing code specific to your
| project that you don't want to repeat in every file. Here you can also expose helpers as
| global functions to help you to reduce the number of lines of code in your test files.
|
*/

function something()
{
    // ..
}

/**
 * Create a user with an active workspace and log them in.
 *
 * @return array{0: User, 1: Workspace}
 */
function signIn(array $userAttributes = []): array
{
    $user = User::factory()->create($userAttributes);
    $workspace = app(WorkspaceCreateAction::class)($user, 'Test workspace');
    test()->actingAs($user->fresh());

    return [$user->fresh(), $workspace->fresh()];
}

function addMember(Workspace $workspace, Role $role = Role::Member, array $attributes = []): User
{
    $user = User::factory()->create(['current_workspace_id' => $workspace->id, ...$attributes]);
    $workspace->users()->attach($user->id, ['role' => $role->value, 'status' => 'active']);

    return $user;
}
