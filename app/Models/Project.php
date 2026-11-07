<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Project extends Model
{
    use HasFactory;

    /** Palette offered in the project colour picker. */
    public const COLORS = [
        '#f44336', '#e91e63', '#9c27b0', '#673ab7', '#3f51b5', '#2196f3', '#03a9f4', '#00bcd4',
        '#009688', '#4caf50', '#8bc34a', '#cddc39', '#ffc107', '#ff9800', '#ff5722', '#795548', '#607d8b',
    ];

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'billable' => 'boolean',
            'is_public' => 'boolean',
            'archived' => 'boolean',
            'hourly_rate' => 'integer',
            'estimate_hours' => 'integer',
        ];
    }

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function members(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'project_user')->withPivot(['is_manager', 'hourly_rate'])->withTimestamps();
    }

    public function favoritedBy(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'project_favorites');
    }

    public function tasks(): HasMany
    {
        return $this->hasMany(Task::class);
    }

    public function timeEntries(): HasMany
    {
        return $this->hasMany(TimeEntry::class);
    }

    /**
     * Projects the given user may track time on / see in pickers.
     */
    public function scopeVisibleTo(Builder $query, User $user, Workspace $workspace): Builder
    {
        // Shared organisation: every member sees every project.
        if (config('tracker.shared_workspace') || $user->isAdminOf($workspace)) {
            return $query;
        }

        return $query->where(fn (Builder $q) => $q
            ->where('is_public', true)
            ->orWhereHas('members', fn (Builder $m) => $m->where('users.id', $user->id)));
    }

    public function fullName(): string
    {
        return $this->client ? "{$this->name} · {$this->client->name}" : $this->name;
    }
}
