<?php

namespace App\Actions;

use App\Models\TimeEntry;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Support\Facades\DB;

/**
 * Starts a timer. Any timer already running for the user in this workspace is stopped first,
 * so a user never has two running entries.
 */
class TimerStartAction
{
    public function __construct(
        private TimerStopAction $stopTimer,
        private TimeEntrySaveAction $saveEntry,
    ) {}

    /**
     * @param  array{description?:string|null, project_id?:int|null, task_id?:int|null, tag_ids?:array<int>, billable?:bool}  $data
     */
    public function __invoke(User $user, Workspace $workspace, array $data = []): TimeEntry
    {
        return DB::transaction(function () use ($user, $workspace, $data): TimeEntry {
            ($this->stopTimer)($user, $workspace, enforceProject: false);

            return ($this->saveEntry)($user, $workspace, [...$data, 'start' => now(), 'end' => null]);
        });
    }
}
