<?php

namespace App\Services;

use App\Enums\EventStatus;
use App\Enums\GuestStatus;
use App\Models\Event;
use App\Models\EventMemorySettings;
use App\Models\Guest;
use App\Models\GuestPartyMembership;
use App\Models\User;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class MemorySettingsService
{
    /**
     * Keys a caller may change through {@see self::update()}.
     *
     * @var array<int,string>
     */
    protected const UPDATABLE_KEYS = [
        'submissions_open', 'post_event_submissions', 'submission_opens_at',
        'submission_closes_at', 'auto_approve', 'public_sharing', 'retention_days',
    ];

    public function __construct(protected AuditService $audit) {}

    public function for(Event $event): EventMemorySettings
    {
        return EventMemorySettings::for($event);
    }

    /**
     * Apply a partial configuration update. Unknown keys are dropped, the
     * submission window is validated, and disabling sharing revokes any live
     * token so old links stop working immediately.
     */
    public function update(Event $event, array $data, User $actor): EventMemorySettings
    {
        $payload = Arr::only($data, self::UPDATABLE_KEYS);

        $settings = $this->for($event);

        $opensAt = $payload['submission_opens_at'] ?? $settings->submission_opens_at;
        $closesAt = $payload['submission_closes_at'] ?? $settings->submission_closes_at;

        if ($opensAt !== null && $closesAt !== null && Carbon::parse($closesAt)->isBefore(Carbon::parse($opensAt))) {
            throw ValidationException::withMessages([
                'submission_closes_at' => 'Waktu penutupan pengiriman tidak boleh lebih awal dari waktu pembukaan.',
            ]);
        }

        $publicSharing = array_key_exists('public_sharing', $payload)
            ? (bool) $payload['public_sharing']
            : (bool) $settings->public_sharing;

        $revokeToken = ! $publicSharing
            && (bool) $settings->public_sharing
            && $settings->share_token_hash !== null
            && $settings->share_token_revoked_at === null;

        return DB::transaction(function () use ($event, $actor, $data, $payload, $settings, $revokeToken): EventMemorySettings {
            $settings->update($payload);

            if ($revokeToken) {
                $settings->update(['share_token_revoked_at' => now()]);
            }

            $this->audit->record(
                'memory.settings.updated',
                'Pengaturan memori acara diperbarui.',
                $event,
                $actor,
                $settings,
                ['changed' => array_keys($data)]
            );

            return $settings->fresh();
        });
    }

    public function closeSubmissions(Event $event, User $actor): EventMemorySettings
    {
        return DB::transaction(function () use ($event, $actor): EventMemorySettings {
            $settings = $this->for($event);

            $settings->update([
                'submissions_open' => false,
                'closed_at' => now(),
                'closed_by' => $actor->id,
            ]);

            $this->audit->record(
                'memory.submissions.closed',
                'Pengiriman kenangan untuk acara ini ditutup.',
                $event,
                $actor,
                $settings
            );

            return $settings->fresh();
        });
    }

    public function reopenSubmissions(Event $event, User $actor): EventMemorySettings
    {
        return DB::transaction(function () use ($event, $actor): EventMemorySettings {
            $settings = $this->for($event);

            $settings->update([
                'submissions_open' => true,
                'closed_at' => null,
                'closed_by' => null,
            ]);

            $this->audit->record(
                'memory.submissions.reopened',
                'Pengiriman kenangan untuk acara ini dibuka kembali.',
                $event,
                $actor,
                $settings
            );

            return $settings->fresh();
        });
    }

    /**
     * Issue a revocable share token. The raw token is returned exactly once
     * for the caller to show; only its hash and prefix are persisted.
     */
    public function enableSharing(Event $event, User $actor): string
    {
        $rawToken = Str::random(64);
        $ttlDays = (int) config('memory.share_token_ttl_days', 0);

        DB::transaction(function () use ($event, $actor, $rawToken, $ttlDays): void {
            $settings = $this->for($event);

            $settings->update([
                'share_token_hash' => hash('sha256', $rawToken),
                'share_token_prefix' => substr($rawToken, 0, 8),
                'share_token_revoked_at' => null,
                'public_sharing' => true,
                'share_token_expires_at' => $ttlDays > 0 ? now()->addDays($ttlDays) : null,
            ]);

            $this->audit->record(
                'memory.share.enabled',
                'Tautan berbagi memori acara diaktifkan.',
                $event,
                $actor,
                $settings,
                ['prefix' => substr($rawToken, 0, 8)]
            );
        });

        return $rawToken;
    }

    public function revokeSharing(Event $event, User $actor): EventMemorySettings
    {
        return DB::transaction(function () use ($event, $actor): EventMemorySettings {
            $settings = $this->for($event);

            $settings->update([
                'public_sharing' => false,
                'share_token_revoked_at' => now(),
            ]);

            $this->audit->record(
                'memory.share.revoked',
                'Tautan berbagi memori acara dinonaktifkan.',
                $event,
                $actor,
                $settings
            );

            return $settings->fresh();
        });
    }

    /**
     * Resolve a raw share token to active settings. Returns null both when
     * the token is unknown and when it is no longer usable, so callers cannot
     * distinguish the two cases.
     */
    public function resolveShareToken(string $raw): ?EventMemorySettings
    {
        $settings = EventMemorySettings::query()
            ->where('share_token_hash', hash('sha256', $raw))
            ->first();

        if ($settings === null || ! $settings->sharingActive()) {
            return null;
        }

        return $settings;
    }

    /**
     * Gate a guest contribution. The guest must be active, the submission
     * window open, the event lifecycle permissive and any party member
     * reference valid.
     */
    public function canSubmit(Event $event, Guest $guest, ?GuestPartyMembership $member = null): bool
    {
        if ($guest->status !== GuestStatus::Active) {
            return false;
        }

        $settings = $this->for($event);

        if ($settings->submissionsClosed()) {
            return false;
        }

        $eventAllows = $event->allowsGuestActions()
            || ($event->status === EventStatus::Completed && $settings->post_event_submissions);

        if (! $eventAllows) {
            return false;
        }

        if ($member !== null && ($member->guest_id !== $guest->id || ! $member->isActive())) {
            return false;
        }

        return true;
    }

    /**
     * Indonesian explanation for the contribution gate, or null when the
     * guest is allowed to contribute.
     */
    public function submissionBlockReason(Event $event, Guest $guest, ?GuestPartyMembership $member = null): ?string
    {
        if ($guest->status !== GuestStatus::Active) {
            return 'Status undangan Anda tidak diizinkan mengirim kenangan.';
        }

        $settings = $this->for($event);

        if ($settings->submissionsClosed()) {
            return 'Pengiriman kenangan untuk acara ini sudah ditutup.';
        }

        if (! $event->allowsGuestActions() && ! ($event->status === EventStatus::Completed && $settings->post_event_submissions)) {
            return 'Acara ini belum membuka kontribusi kenangan.';
        }

        if ($member !== null && $member->guest_id !== $guest->id) {
            return 'Tamu yang dipilih tidak termasuk dalam pesta Anda.';
        }

        if ($member !== null && ! $member->isActive()) {
            return 'Anggota pesta yang dipilih tidak lagi aktif.';
        }

        return null;
    }
}
