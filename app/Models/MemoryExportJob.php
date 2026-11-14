<?php

namespace App\Models;

use App\Enums\MemoryExportStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

/**
 * Background export of an event's memory archive.
 */
class MemoryExportJob extends Model
{
    use HasFactory;

    protected $fillable = [
        'event_id', 'requested_by', 'status', 'scope', 'file_disk', 'file_path',
        'file_size', 'item_count', 'error', 'started_at', 'completed_at', 'expires_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => MemoryExportStatus::class,
            'scope' => 'array',
            'file_size' => 'integer',
            'item_count' => 'integer',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
            'expires_at' => 'datetime',
        ];
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function isTerminal(): bool
    {
        return $this->status instanceof MemoryExportStatus && $this->status->isTerminal();
    }

    public function isExpired(): bool
    {
        return $this->expires_at !== null && $this->expires_at->isPast();
    }

    /**
     * Whether the generated archive still exists on its storage disk.
     */
    public function fileExists(): bool
    {
        if (blank($this->file_disk) || blank($this->file_path)) {
            return false;
        }

        return Storage::disk($this->file_disk)->exists($this->file_path);
    }
}
