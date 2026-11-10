<?php

namespace App\Models;

use App\Enums\OperationsStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EventOperation extends Model
{
    use HasFactory;

    protected $fillable = [
        'event_id',
        'status',
        'activated_at',
        'completed_at',
        'cancelled_at',
        'closed_by',
        'closure_notes',
        'closure_snapshot',
        'readiness_snapshot',
    ];

    protected function casts(): array
    {
        return [
            'status' => OperationsStatus::class,
            'activated_at' => 'datetime',
            'completed_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'closure_snapshot' => 'array',
            'readiness_snapshot' => 'array',
        ];
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function closedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'closed_by');
    }

    public function isCompleted(): bool
    {
        return $this->status === OperationsStatus::Completed;
    }
}
