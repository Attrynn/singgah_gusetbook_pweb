<?php

namespace App\Enums;

use App\Models\GuestbookMessage;
use App\Models\MediaAsset;

/**
 * Explicit contribution type discriminator used by memory collections and
 * consents. The map is deliberately closed so tenant integrity can be enforced
 * without polymorphic relations.
 */
enum MemoryItemType: string
{
    case MediaAsset = 'media_asset';
    case GuestbookMessage = 'guestbook_message';

    public function label(): string
    {
        return match ($this) {
            self::MediaAsset => 'Foto',
            self::GuestbookMessage => 'Ucapan',
        };
    }

    /**
     * Fully-qualified model class that stores this contribution type.
     */
    public function modelClass(): string
    {
        return match ($this) {
            self::MediaAsset => MediaAsset::class,
            self::GuestbookMessage => GuestbookMessage::class,
        };
    }

    /**
     * Canonical key stored in `contribution_type` / `item_type` columns.
     */
    public function contributionKey(): string
    {
        return $this->value;
    }

    /**
     * @return array<int,array{value:string,label:string}>
     */
    public static function options(): array
    {
        return array_map(
            fn (self $case): array => ['value' => $case->value, 'label' => $case->label()],
            self::cases(),
        );
    }
}
