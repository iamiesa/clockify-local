<?php

namespace App\Models;

use App\Enums\MemberStatus;
use App\Enums\Role;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    protected $fillable = [
        'name', 'email', 'password', 'timezone', 'week_start', 'time_format', 'date_format',
        'avatar_color', 'current_workspace_id',
    ];

    protected $hidden = ['password', 'remember_token'];

    /** @var array<int, Membership|null> */
    private array $membershipCache = [];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'week_start' => 'integer',
        ];
    }

    public function workspaces(): BelongsToMany
    {
        return $this->belongsToMany(Workspace::class, 'workspace_user')
            ->using(Membership::class)
            ->withPivot(['id', 'role', 'status', 'hourly_rate'])
            ->withTimestamps();
    }

    public function activeWorkspaces(): BelongsToMany
    {
        return $this->workspaces()->wherePivot('status', MemberStatus::Active->value);
    }

    public function currentWorkspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class, 'current_workspace_id');
    }

    public function timeEntries(): HasMany
    {
        return $this->hasMany(TimeEntry::class);
    }

    public function groups(): BelongsToMany
    {
        return $this->belongsToMany(UserGroup::class, 'user_group_user');
    }

    public function favoriteProjects(): BelongsToMany
    {
        return $this->belongsToMany(Project::class, 'project_favorites');
    }

    public function membershipIn(Workspace $workspace): ?Membership
    {
        if (! array_key_exists($workspace->id, $this->membershipCache)) {
            $this->membershipCache[$workspace->id] = Membership::query()
                ->where('workspace_id', $workspace->id)
                ->where('user_id', $this->id)
                ->first();
        }

        return $this->membershipCache[$workspace->id];
    }

    public function isActiveMemberOf(Workspace $workspace): bool
    {
        return $this->membershipIn($workspace)?->status === MemberStatus::Active;
    }

    public function roleIn(Workspace $workspace): ?Role
    {
        return $this->membershipIn($workspace)?->role;
    }

    public function isAdminOf(Workspace $workspace): bool
    {
        return (bool) $this->roleIn($workspace)?->canManageWorkspace();
    }

    public function runningEntry(Workspace $workspace): ?TimeEntry
    {
        return $this->timeEntries()->where('workspace_id', $workspace->id)->running()->latest('start')->first();
    }

    public function initials(): string
    {
        return collect(preg_split('/\s+/', trim($this->name)))
            ->filter()
            ->take(2)
            ->map(fn (string $part): string => mb_strtoupper(mb_substr($part, 0, 1)))
            ->implode('');
    }

    public function forgetMemberships(): void
    {
        $this->membershipCache = [];
    }
}
