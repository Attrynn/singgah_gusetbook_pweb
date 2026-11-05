<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SeatingTable extends Model
{
    protected $table = 'seating_tables';

    protected $fillable = ['event_id', 'name', 'capacity', 'zone', 'notes'];

    protected function casts(): array
    {
        return ['capacity' => 'integer'];
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function assignments(): HasMany
    {
        return $this->hasMany(TableAssignment::class);
    }

    public function guests(): BelongsToMany
    {
        return $this->belongsToMany(Guest::class, 'table_assignments')
            ->withPivot('seat_number')
            ->withTimestamps();
    }

    public function occupiedCount(): int
    {
        return $this->assignments()->count();
    }

    public function remainingCapacity(): int
    {
        return max(0, $this->capacity - $this->occupiedCount());
    }

    public function isFull(): bool
    {
        return $this->remainingCapacity() === 0;
    }
}
