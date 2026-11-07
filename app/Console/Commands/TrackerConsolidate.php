<?php

namespace App\Console\Commands;

use App\Actions\WorkspaceConsolidateAction;
use Illuminate\Console\Command;

class TrackerConsolidate extends Command
{
    protected $signature = 'tracker:consolidate';

    protected $description = 'Merge all workspaces into the shared organisation workspace and apply OWNER_EMAIL';

    public function handle(WorkspaceConsolidateAction $consolidate): int
    {
        if (! config('tracker.shared_workspace')) {
            $this->warn('SHARED_WORKSPACE is disabled; nothing to do.');

            return self::SUCCESS;
        }

        $result = $consolidate();
        $this->info("Shared workspace #{$result['workspace']}: merged {$result['merged']} workspace(s), {$result['users']} user(s).");

        return self::SUCCESS;
    }
}
