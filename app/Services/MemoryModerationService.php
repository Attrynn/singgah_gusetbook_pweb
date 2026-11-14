<?php

namespace App\Services;

use App\Enums\MediaStatus;
use App\Enums\MemoryItemType;
use App\Enums\MemoryState;
use App\Enums\MessageStatus;
use App\Models\AuditLog;
use App\Models\Event;
use App\Models\GuestbookMessage;
use App\Models\MediaAsset;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Moderation entry point for memory contributions.
 *
 * Delegates the actual state transition to the owning service (media or
 * guestbook) so moderation rules stay in one place, then mirrors the
 * transition into the memory audit stream.
 */
class MemoryModerationService
{
    private const ACTIONS = ['approve', 'reject', 'hide', 'unhide', 'reopen'];

    private const BULK_LIMIT = 100;

    private const HISTORY_LIMIT = 50;

    public function __construct(
        protected MediaService $media,
        protected GuestbookService $guestbook,
        protected AuditService $audit,
    ) {}

    /**
     * Apply a moderation action to a single contribution scoped to the event.
     */
    public function apply(string $type, int $id, string $action, Event $event, User $actor, ?string $note = null): MemoryState
    {
        $itemType = $this->requireItemType($type);
        $this->requireAction($action);

        $class = $itemType->modelClass();
        $model = $class::query()->where('event_id', $event->id)->findOrFail($id);

        $before = $this->resolveState($model);

        return DB::transaction(function () use ($event, $actor, $itemType, $model, $action, $note, $before): MemoryState {
            if ($itemType === MemoryItemType::MediaAsset) {
                $this->moderateMedia($model, $action, $actor);
            } else {
                $this->moderateMessage($model, $action, $actor);
            }

            $fresh = $model->fresh();
            $after = $this->resolveState($fresh);

            $this->audit->record(
                'memory.'.$itemType->value.'.'.$action,
                $this->describe($itemType, $fresh, $action),
                $event,
                $actor,
                $fresh,
                ['from' => $before->value, 'to' => $after->value, 'note' => $note],
            );

            return $after;
        });
    }

    /**
     * Apply one action to many contributions. Identifiers that no longer
     * resolve inside the event are skipped; every success keeps its own
     * audit row.
     */
    public function bulk(string $type, array $ids, string $action, Event $event, User $actor): int
    {
        if (count($ids) > self::BULK_LIMIT) {
            throw ValidationException::withMessages([
                'ids' => 'Maksimal '.self::BULK_LIMIT.' item dapat diproses sekaligus.',
            ]);
        }

        $applied = 0;

        foreach ($ids as $id) {
            try {
                $this->apply($type, (int) $id, $action, $event, $actor);
                $applied++;
            } catch (ModelNotFoundException) {
                // Stale identifier from another event or a deleted record.
                continue;
            }
        }

        return $applied;
    }

    /**
     * Audit trail for one contribution, newest first.
     */
    public function history(Event $event, string $type, int $id): Collection
    {
        $itemType = $this->requireItemType($type);

        return AuditLog::query()
            ->where('event_id', $event->id)
            ->where('target_type', $itemType->modelClass())
            ->where('target_id', $id)
            ->latest()
            ->limit(self::HISTORY_LIMIT)
            ->get();
    }

    protected function moderateMedia(MediaAsset $asset, string $action, User $actor): void
    {
        if ($action === 'hide') {
            $asset->update(['hidden_at' => now()]);

            return;
        }

        if ($action === 'unhide') {
            $asset->update(['hidden_at' => null]);

            return;
        }

        $serviceAction = match ($action) {
            'approve' => 'approve',
            'reject' => 'reject',
            'reopen' => 'pending',
        };

        $asset = $this->media->moderate($asset, $serviceAction, $actor);
        $asset->forceFill(['hidden_at' => null])->save();
    }

    protected function moderateMessage(GuestbookMessage $message, string $action, User $actor): void
    {
        if ($action === 'unhide') {
            // GuestbookService has no unhide action; restore approval directly.
            $message->update(['status' => MessageStatus::Approved]);

            return;
        }

        $serviceAction = match ($action) {
            'approve' => 'approve',
            'reject' => 'reject',
            'hide' => 'hide',
            'reopen' => 'pending',
        };

        $this->guestbook->moderate($message, $serviceAction, $actor);
    }

    protected function resolveState(Model $model): MemoryState
    {
        if ($model instanceof MediaAsset) {
            if ($model->hidden_at !== null) {
                return MemoryState::Hidden;
            }

            return match ($model->status) {
                MediaStatus::Approved => MemoryState::Approved,
                MediaStatus::Rejected => MemoryState::Rejected,
                default => MemoryState::Pending,
            };
        }

        return match ($model->status) {
            MessageStatus::Approved => MemoryState::Approved,
            MessageStatus::Rejected => MemoryState::Rejected,
            MessageStatus::Hidden => MemoryState::Hidden,
            default => MemoryState::Pending,
        };
    }

    protected function describe(MemoryItemType $type, Model $model, string $action): string
    {
        $label = match ($action) {
            'approve' => 'disetujui',
            'reject' => 'ditolak',
            'hide' => 'disembunyikan',
            'unhide' => 'ditampilkan kembali',
            'reopen' => 'dibuka kembali',
            default => 'diperbarui',
        };

        if ($type === MemoryItemType::MediaAsset) {
            return "Foto {$model->original_name} {$label}";
        }

        return "Ucapan dari {$model->sender_name} {$label}";
    }

    protected function requireItemType(string $type): MemoryItemType
    {
        $itemType = MemoryItemType::tryFrom($type);

        if ($itemType === null) {
            throw ValidationException::withMessages(['type' => 'Tipe kontribusi memori tidak dikenal.']);
        }

        return $itemType;
    }

    protected function requireAction(string $action): void
    {
        if (! in_array($action, self::ACTIONS, true)) {
            throw ValidationException::withMessages(['action' => 'Aksi moderasi tidak dikenal.']);
        }
    }
}
