<?php

return [
    /*
    | Shared organisation mode: every user belongs to one workspace, sees all of its projects
    | and tags, and nobody gets a personal workspace. Set to false for multi-workspace mode.
    */
    'shared_workspace' => (bool) env('SHARED_WORKSPACE', true),

    /** Name used when the shared workspace is first created. */
    'workspace_name' => env('SHARED_WORKSPACE_NAME', 'TimeTrack'),

    /** This account is always the Owner of the shared workspace (case-insensitive). */
    'owner_email' => env('OWNER_EMAIL'),
];
