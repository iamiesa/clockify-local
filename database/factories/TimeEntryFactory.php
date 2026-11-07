<?php

namespace Database\Factories;

use App\Models\TimeEntry;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TimeEntry>
 */
class TimeEntryFactory extends Factory
{
    public function definition(): array
    {
        $start = now()->subDays(fake()->numberBetween(0, 6))->setTime(fake()->numberBetween(8, 15), 0);
        $duration = fake()->numberBetween(15, 180) * 60;

        return [
            'workspace_id' => Workspace::factory(),
            'user_id' => User::factory(),
            'description' => fake()->sentence(3),
            'start' => $start,
            'end' => $start->addSeconds($duration),
            'duration' => $duration,
            'billable' => false,
            'hourly_rate' => 0,
        ];
    }

    public function running(): static
    {
        return $this->state(['start' => now()->subMinutes(30), 'end' => null, 'duration' => 0]);
    }
}
