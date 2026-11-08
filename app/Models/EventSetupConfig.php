<?php

namespace App\Models;

use App\Enums\SetupStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class EventSetupConfig extends Model
{
    use HasFactory;

    protected $fillable = [
        'event_id',
        'playbook_id',
        'playbook_version_id',
        'source_event_id',
        'config_snapshot',
        'setup_status',
        'primary_pic_staff_membership_id',
    ];

    protected function casts(): array
    {
        return [
            'config_snapshot' => 'array',
            'setup_status' => SetupStatus::class,
        ];
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function playbook(): BelongsTo
    {
        return $this->belongsTo(Playbook::class);
    }

    public function playbookVersion(): BelongsTo
    {
        return $this->belongsTo(PlaybookVersion::class);
    }

    public function sourceEvent(): BelongsTo
    {
        return $this->belongsTo(Event::class, 'source_event_id');
    }

    public function primaryPic(): BelongsTo
    {
        return $this->belongsTo(EventStaffMembership::class, 'primary_pic_staff_membership_id');
    }

    public function checklistItems(): HasMany
    {
        return $this->hasMany(EventSetupChecklistItem::class, 'event_id', 'event_id');
    }
}
