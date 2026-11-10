<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EventRunOfShowAssignee extends Model
{
    use HasFactory;

    protected $fillable = [
        'event_id',
        'run_of_show_item_id',
        'staff_membership_id',
    ];

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(EventRunOfShowItem::class, 'run_of_show_item_id');
    }

    public function membership(): BelongsTo
    {
        return $this->belongsTo(EventStaffMembership::class, 'staff_membership_id');
    }
}
