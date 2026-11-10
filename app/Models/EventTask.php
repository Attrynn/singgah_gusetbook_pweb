<?php

namespace App\Models;

use App\Enums\TaskPriority;
use App\Enums\TaskStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class EventTask extends Model
{
    use HasFactory;

    protected $fillable = [
        'event_id',
        'title',
        'description',
        'category',
        'priority',
        'status',
        'assigned_membership_id',
        'station_id',
        'run_of_show_item_id',
        'due_at',
        'completed_at',
        'cancelled_at',
        'blocked_reason',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'priority' => TaskPriority::class,
            'status' => TaskStatus::class,
            'due_at' => 'datetime',
            'completed_at' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(EventStaffMembership::class, 'assigned_membership_id');
    }

    public function station(): BelongsTo
    {
        return $this->belongsTo(EventStation::class, 'station_id');
    }

    public function runOfShowItem(): BelongsTo
    {
        return $this->belongsTo(EventRunOfShowItem::class, 'run_of_show_item_id');
    }

    public function checklistItems(): HasMany
    {
        return $this->hasMany(EventTaskChecklistItem::class)->orderBy('sequence');
    }

    public function comments(): HasMany
    {
        return $this->hasMany(EventTaskComment::class)->latest();
    }

    public function dependencies(): BelongsToMany
    {
        return $this->belongsToMany(
            self::class,
            'event_task_dependencies',
            'event_task_id',
            'depends_on_task_id',
        )->withTimestamps();
    }

    public function dependents(): BelongsToMany
    {
        return $this->belongsToMany(
            self::class,
            'event_task_dependencies',
            'depends_on_task_id',
            'event_task_id',
        )->withTimestamps();
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function isOverdue(): bool
    {
        return $this->due_at !== null
            && ! $this->status->isClosed()
            && $this->due_at->isPast();
    }
}
