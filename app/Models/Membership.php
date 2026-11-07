<?php

namespace App\Models;

use App\Enums\MemberStatus;
use App\Enums\Role;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\Pivot;

/**
 * A user's seat in a workspace (the workspace_user pivot).
 */
class Membership extends Pivot
{
    protected $table = 'workspace_user';

    public $incrementing = true;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'role' => Role::class,
            'status' => MemberStatus::class,
            'hourly_rate' => 'integer',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }
}
