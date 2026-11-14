<?php

namespace App\Models;

use App\Enums\MemoryItemType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One ordered entry inside a memory collection. The target is resolved through
 * the explicit {@see MemoryItemType} map rather than a polymorphic relation.
 */
class MemoryCollectionItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'event_id', 'memory_collection_id', 'item_type', 'item_id', 'sort_order', 'added_by',
    ];

    protected function casts(): array
    {
        return [
            'sort_order' => 'integer',
        ];
    }

    public function collection(): BelongsTo
    {
        return $this->belongsTo(MemoryCollection::class, 'memory_collection_id');
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function adder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'added_by');
    }

    /**
     * Typed view of the `item_type` discriminator.
     */
    public function type(): ?MemoryItemType
    {
        return MemoryItemType::tryFrom((string) $this->item_type);
    }

    /**
     * Resolve the referenced media asset or guestbook message.
     *
     * Callers that hydrate a whole page (see MemoryCollectionService) set the
     * `item` relation first, so this returns the preloaded model instead of
     * issuing a query per row.
     */
    public function item(): ?Model
    {
        if ($this->relationLoaded('item')) {
            return $this->getRelation('item');
        }

        $type = $this->type();

        if ($type === null) {
            return null;
        }

        $class = $type->modelClass();

        return $class::query()->find($this->item_id);
    }
}
