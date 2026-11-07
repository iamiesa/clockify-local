<?php

namespace Database\Factories;

use App\Enums\MemberStatus;
use App\Enums\Role;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Workspace>
 */
class WorkspaceFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => fake()->company(),
            'owner_id' => User::factory(),
            'currency' => 'USD',
            'hourly_rate' => 0,
        ];
    }

    /** Attach the owner as an active Owner member and make it their current workspace. */
    public function configure(): static
    {
        return $this->afterCreating(function (Workspace $workspace): void {
            $workspace->users()->syncWithoutDetaching([$workspace->owner_id => ['role' => Role::Owner->value, 'status' => MemberStatus::Active->value]]);
            User::whereKey($workspace->owner_id)->whereNull('current_workspace_id')->update(['current_workspace_id' => $workspace->id]);
        });
    }
}
