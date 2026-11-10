<?php

namespace App\Models;

use App\Enums\RunOfShowStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class EventRunOfShowItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'event_id',
        'event_session_id',
        'phase_id',
        'station_id',
        'depends_on_item_id',
        'title',
        'internal_description',
        'planned_start',
        'planned_end',
        'actual_start',
        'actual_end',
        'status',
        'status_changed_at',
        'status_changed_by',
        'sequence',
        'pic_staff_membership_id',
        'internal_notes',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'planned_start' => 'datetime',
            'planned_end' => 'datetime',
            'actual_start' => 'datetime',
            'actual_end' => 'datetime',
            'status' => RunOfShowStatus::class,
            'status_changed_at' => 'datetime',
            'sequence' => 'integer',
        ];
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function session(): BelongsTo
    {
        return $this->belongsTo(EventSession::class, 'event_session_id');
    }

    public function phase(): BelongsTo
    {
        return $this->belongsTo(EventPhase::class, 'phase_id');
    }

    public function station(): BelongsTo
    {
        return $this->belongsTo(EventStation::class, 'station_id');
    }

    public function dependsOn(): BelongsTo
    {
        return $this->belongsTo(self::class, 'depends_on_item_id');
    }

    public function dependents(): HasMany
    {
        return $this->hasMany(self::class, 'depends_on_item_id');
    }

    public function pic(): BelongsTo
    {
        return $this->belongsTo(EventStaffMembership::class, 'pic_staff_membership_id');
    }

    public function statusChangedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'status_changed_by');
    }

    public function assignees(): BelongsToMany
    {
        return $this->belongsToMany(
            EventStaffMembership::class,
            'event_run_of_show_assignees',
            'run_of_show_item_id',
            'staff_membership_id',
        )->withTimestamps();
    }

    public function tasks(): HasMany
    {
        return $this->hasMany(EventTask::class, 'run_of_show_item_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
