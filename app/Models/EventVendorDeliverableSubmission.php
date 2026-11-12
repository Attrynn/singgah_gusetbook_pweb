<?php

namespace App\Models;

use App\Enums\VendorSubmissionStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EventVendorDeliverableSubmission extends Model
{
    use HasFactory;

    protected $fillable = [
        'event_vendor_deliverable_id',
        'submitted_by_user_id',
        'submitted_by_contact_id',
        'notes',
        'event_vendor_document_id',
        'status',
        'reviewed_by',
        'reviewed_at',
        'review_notes',
        'submitted_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => VendorSubmissionStatus::class,
            'reviewed_at' => 'datetime',
            'submitted_at' => 'datetime',
        ];
    }

    public function deliverable(): BelongsTo
    {
        return $this->belongsTo(EventVendorDeliverable::class, 'event_vendor_deliverable_id');
    }

    public function submittedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'submitted_by_user_id');
    }

    public function submittedByContact(): BelongsTo
    {
        return $this->belongsTo(VendorContact::class, 'submitted_by_contact_id');
    }

    public function document(): BelongsTo
    {
        return $this->belongsTo(EventVendorDocument::class, 'event_vendor_document_id');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }
}
