<?php

namespace Database\Seeders;

use App\Actions\WorkspaceCreateAction;
use App\Enums\MemberStatus;
use App\Enums\Role;
use App\Models\Client;
use App\Models\Project;
use App\Models\Tag;
use App\Models\Task;
use App\Models\User;
use App\Models\UserGroup;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Demo workspace: log in as demo@example.com / password.
 */
class DatabaseSeeder extends Seeder
{
    private const TZ = 'Asia/Kolkata';

    public function run(): void
    {
        $owner = User::factory()->create(['name' => 'Demo Owner', 'email' => 'demo@example.com', 'timezone' => self::TZ, 'avatar_color' => '#03a9f4']);
        $workspace = app(WorkspaceCreateAction::class)($owner, 'Demo Company');
        $workspace->update(['hourly_rate' => 2500, 'currency' => 'USD']);

        $members = collect([
            ['Aisha Khan', 'aisha@example.com', Role::Admin, 4000],
            ['Bilal Ahmed', 'bilal@example.com', Role::Member, 3000],
            ['Sara Malik', 'sara@example.com', Role::Member, 3500],
            ['Omar Farooq', 'omar@example.com', Role::Member, null],
        ])->map(function (array $m) use ($workspace): User {
            $user = User::factory()->create(['name' => $m[0], 'email' => $m[1], 'timezone' => self::TZ, 'current_workspace_id' => $workspace->id]);
            $workspace->users()->attach($user->id, ['role' => $m[2]->value, 'status' => MemberStatus::Active->value, 'hourly_rate' => $m[3]]);

            return $user;
        });
        $everyone = $members->prepend($owner);

        $design = UserGroup::create(['workspace_id' => $workspace->id, 'name' => 'Design']);
        $design->users()->attach([$members[1]->id, $members[3]->id]);
        $dev = UserGroup::create(['workspace_id' => $workspace->id, 'name' => 'Development']);
        $dev->users()->attach([$members[2]->id, $members[4]->id, $owner->id]);

        $clients = collect(['Bluepeak Labs', 'Harbor & Pine', 'Medina Health', 'Orchard Retail'])
            ->map(fn (string $name) => Client::create(['workspace_id' => $workspace->id, 'name' => $name]));

        $projectSpecs = [
            ['Website Redesign', 0, '#2196f3', true, 5000, 200, ['Wireframes', 'UI design', 'Frontend build', 'QA']],
            ['Mobile App', 0, '#9c27b0', true, null, 400, ['iOS', 'Android', 'API integration']],
            ['ERP Migration', 1, '#ff9800', true, 6000, 300, ['Data mapping', 'Testing']],
            ['Patient Portal', 2, '#4caf50', true, null, 250, ['Backend', 'Accessibility audit']],
            ['Ecommerce Store', 3, '#e91e63', true, null, null, ['Catalog', 'Checkout']],
            ['Marketing', null, '#00bcd4', false, null, null, ['Social media', 'Blog posts']],
            ['Internal', null, '#607d8b', false, null, null, ['Meetings', 'Hiring', 'Training']],
        ];

        $projects = collect($projectSpecs)->map(function (array $spec) use ($workspace, $clients): Project {
            $project = Project::create([
                'workspace_id' => $workspace->id,
                'client_id' => $spec[1] !== null ? $clients[$spec[1]]->id : null,
                'name' => $spec[0],
                'color' => $spec[2],
                'billable' => $spec[3],
                'hourly_rate' => $spec[4],
                'estimate_hours' => $spec[5],
            ]);
            foreach ($spec[6] as $task) {
                Task::create(['project_id' => $project->id, 'name' => $task]);
            }

            return $project->load('tasks');
        });

        $projects[2]->update(['is_public' => false]);
        $projects[2]->members()->attach([
            $owner->id => ['is_manager' => true, 'hourly_rate' => null],
            $members[1]->id => ['is_manager' => false, 'hourly_rate' => null],
            $members[2]->id => ['is_manager' => false, 'hourly_rate' => 4500],
        ]);
        $owner->favoriteProjects()->attach([$projects[0]->id, $projects[6]->id]);

        $tags = collect(['Urgent', 'Meeting', 'Research', 'Bugfix', 'Review', 'Overtime'])
            ->map(fn (string $name) => Tag::create(['workspace_id' => $workspace->id, 'name' => $name]));

        $descriptions = ['Daily standup', 'Code review', 'Client call', 'Design review', 'Sprint planning', 'Fixing bugs', 'Writing docs',
            'Implementing feature', 'Refactoring', 'Deploy to staging', 'Research', 'Email & admin', 'Pairing session', ''];

        mt_srand(42);
        $today = CarbonImmutable::now(self::TZ)->startOfDay();
        $rows = [];
        $tagRows = [];

        foreach ($everyone as $user) {
            $memberRate = DB::table('workspace_user')->where('workspace_id', $workspace->id)->where('user_id', $user->id)->value('hourly_rate');

            for ($d = 56; $d >= 0; $d--) {
                $day = $today->subDays($d);
                if ($day->isWeekend() && mt_rand(0, 4) > 0) {
                    continue;
                }

                $cursor = $day->setTime(9, mt_rand(0, 3) * 15);
                $end = $day->setTime(17, 30);

                while ($cursor->lessThan($end)) {
                    $minutes = mt_rand(2, 10) * 15;
                    $entryEnd = $cursor->addMinutes($minutes);
                    if ($d === 0 && $entryEnd->isFuture()) {
                        break;
                    }

                    $project = mt_rand(0, 9) === 0 ? null : $projects[mt_rand(0, $projects->count() - 1)];
                    if ($project && ! $project->is_public && ! in_array($user->id, [$owner->id, $members[1]->id, $members[2]->id], true)) {
                        $project = $projects[0];
                    }
                    $billable = $project?->billable ?? false;
                    $rate = $project?->hourly_rate ?? $memberRate ?? $workspace->hourly_rate;

                    $rows[] = [
                        'workspace_id' => $workspace->id,
                        'user_id' => $user->id,
                        'project_id' => $project?->id,
                        'task_id' => $project && mt_rand(0, 1) ? $project->tasks->random()->id : null,
                        'description' => $descriptions[mt_rand(0, count($descriptions) - 1)],
                        'start' => $cursor->utc()->format('Y-m-d H:i:s'),
                        'end' => $entryEnd->utc()->format('Y-m-d H:i:s'),
                        'duration' => $minutes * 60,
                        'billable' => $billable,
                        'hourly_rate' => $rate,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ];

                    $cursor = $entryEnd->addMinutes(mt_rand(0, 2) * 15);
                }
            }
        }

        foreach (array_chunk($rows, 500) as $chunk) {
            DB::table('time_entries')->insert($chunk);
        }

        $entryIds = DB::table('time_entries')->where('workspace_id', $workspace->id)->pluck('id');
        foreach ($entryIds as $id) {
            if (mt_rand(0, 2) === 0) {
                foreach ($tags->random(mt_rand(1, 2)) as $tag) {
                    $tagRows[] = ['time_entry_id' => $id, 'tag_id' => $tag->id];
                }
            }
        }
        foreach (array_chunk($tagRows, 500) as $chunk) {
            DB::table('tag_time_entry')->insert($chunk);
        }

        // A second workspace to demonstrate switching.
        app(WorkspaceCreateAction::class)($owner, 'Side Projects');
        $owner->update(['current_workspace_id' => $workspace->id]);
    }
}
