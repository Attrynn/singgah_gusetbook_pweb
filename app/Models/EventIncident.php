<?php

namespace App\Models;

use App\Enums\IncidentCategory;
use App\Enums\IncidentSeverity;
use App\Enums\IncidentStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class EventIncident extends Model
{
    use HasFactory;

    protected $fillable = [
        'event_id',
        'title',
        'description',
        'category',
        'severity',
        'status',
        'station_id',
        'reported_by',
        'assigned_to',
        'reported_at',
        'acknowledged_at',
        'resolved_at',
        'resolved_by',
        'resolution_notes',
        'closed_at',
        'closed_by',
        'escalated_at',
        'escalated_to',
        'escalation_reason',
    ];

    protected function casts(): array
    {
        return [
            'category' => IncidentCategory::class,
            'severity' => IncidentSeverity::class,
            'status' => IncidentStatus::class,
            'reported_at' => 'datetime',
            'acknowledged_at' => 'datetime',
            'resolved_at' => 'datetime',
            'closed_at' => 'datetime',
            'escalated_at' => 'datetime',
        ];
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function station(): BelongsTo
    {
        return $this->belongsTo(EventStation::class, 'station_id');
    }

    public function reporter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reported_by');
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function resolver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'resolved_by');
    }

    public function closer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'closed_by');
    }

    public function escalatedTo(): BelongsTo
    {
        return $this->belongsTo(User::class, 'escalated_to');
    }

    public function updates(): HasMany
    {
        return $this->hasMany(EventIncidentUpdate::class)->latest();
    }

    public function isOpen(): bool
    {
        return $this->status->isOpen();
    }
}
