<?php

namespace App\Actions;

use App\Models\TimeEntry;
use App\Models\User;
use App\Models\Workspace;

class TimerStopAction
{
    public function __construct(private TimeEntrySaveAction $saveEntry) {}

    public function __invoke(User $user, Workspace $workspace, bool $enforceProject = true): ?TimeEntry
    {
        $running = $user->runningEntry($workspace);

        if (! $running) {
            return null;
        }

        $end = now();
        if ($end->lessThan($running->start)) {
            $end = $running->start->copy();
        }

        if (! $enforceProject && $workspace->force_project && $running->project_id === null) {
            $running->forceFill(['end' => $end, 'duration' => (int) $running->start->diffInSeconds($end)])->save();

            return $running;
        }

        return ($this->saveEntry)($user, $workspace, ['end' => $end], $running);
    }
}
