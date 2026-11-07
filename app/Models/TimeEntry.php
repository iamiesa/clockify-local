<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class TimeEntry extends Model
{
    use HasFactory;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'start' => 'datetime',
            'end' => 'datetime',
            'duration' => 'integer',
            'billable' => 'boolean',
            'hourly_rate' => 'integer',
        ];
    }

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function task(): BelongsTo
    {
        return $this->belongsTo(Task::class);
    }

    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(Tag::class, 'tag_time_entry');
    }

    public function scopeCompleted(Builder $query): Builder
    {
        return $query->whereNotNull('end');
    }

    public function scopeRunning(Builder $query): Builder
    {
        return $query->whereNull('end');
    }

    public function isRunning(): bool
    {
        return $this->end === null;
    }

    /** Billable amount in cents. */
    public function amount(): int
    {
        return $this->billable ? (int) round($this->duration / 3600 * $this->hourly_rate) : 0;
    }
}
