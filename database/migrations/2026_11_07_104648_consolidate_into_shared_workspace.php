<?php

use App\Actions\WorkspaceConsolidateAction;
use Illuminate\Database\Migrations\Migration;

/**
 * Data migration: switches existing installs to shared-organisation mode by merging every
 * personal workspace into one (see WorkspaceConsolidateAction). Safe to run on an empty database.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (config('tracker.shared_workspace')) {
            app(WorkspaceConsolidateAction::class)();
        }
    }

    public function down(): void
    {
        // Merged workspaces cannot be split back apart.
    }
};
