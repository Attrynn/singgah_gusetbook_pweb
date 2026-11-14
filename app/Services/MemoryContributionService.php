<?php

namespace App\Services;

use App\Enums\MediaStatus;
use App\Enums\MemoryItemType;
use App\Enums\MessageStatus;
use App\Enums\MessageVisibility;
use App\Models\Event;
use App\Models\Guest;
use App\Models\GuestbookMessage;
use App\Models\GuestPartyMembership;
use App\Models\MediaAsset;
use App\Models\MemoryConsent;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Throwable;

class MemoryContributionService
{
    public function __construct(
        protected MemoryMediaSanitizer $sanitizer,
        protected MediaService $media,
        protected AuditService $audit,
        protected MemorySettingsService $settings,
    ) {}

    /**
     * Store a batch of guest photos as pending media assets, each with its
     * own consent record. Written files are removed again when any step
     * fails so storage never keeps objects without a database row.
     *
     * @param  array<int,UploadedFile>  $files
     * @return array<int,MediaAsset>
     */
    public function submitPhotos(Event $event, Guest $guest, array $files, array $data, ?GuestPartyMembership $member = null): array
    {
        if (! $this->settings->canSubmit($event, $guest, $member)) {
            throw ValidationException::withMessages([
                'files' => $this->settings->submissionBlockReason($event, $guest, $member)
                    ?? 'Pengiriman kenangan belum dibuka untuk acara ini.',
            ]);
        }

        $clientSubmissionId = ! empty($data['client_submission_id']) ? (string) $data['client_submission_id'] : null;

        if ($clientSubmissionId !== null) {
            $existing = MediaAsset::query()
                ->where('event_id', $event->id)
                ->where('client_submission_id', $clientSubmissionId)
                ->first();

            if ($existing !== null) {
                return [$existing];
            }
        }

        $maxPerEvent = (int) config('memory.guest_max_per_event', 50);
        $submittedCount = MediaAsset::query()
            ->where('event_id', $event->id)
            ->where('guest_id', $guest->id)
            ->count();

        if ($submittedCount + count($files) > $maxPerEvent) {
            throw ValidationException::withMessages([
                'files' => "Batas maksimal {$maxPerEvent} foto per tamu untuk acara ini telah tercapai.",
            ]);
        }

        $disk = (string) config('media.private_disk', 'local');
        $directory = str_replace('{event}', (string) $event->id, (string) config('memory.upload_path', 'events/{event}/memory'));
        $caption = array_key_exists('caption', $data) ? $data['caption'] : null;
        $writtenPaths = [];
        $staged = [];
        $isFirstFile = true;
        $committed = false;

        try {
            foreach ($files as $file) {
                $clean = $this->sanitizer->sanitize($file);
                $path = sprintf('%s/%s.%s', $directory, Str::uuid(), $clean['extension']);

                if (Storage::disk($disk)->put($path, $clean['bytes']) === false) {
                    throw new RuntimeException('Gagal menyimpan berkas foto ke penyimpanan.');
                }

                $writtenPaths[] = $path;
                $staged[] = [
                    'file' => $file,
                    'clean' => $clean,
                    'path' => $path,
                    'is_first' => $isFirstFile,
                ];
                $isFirstFile = false;
            }

            $assets = DB::transaction(function () use ($event, $guest, $member, $clientSubmissionId, $caption, $staged, $disk, $data): array {
                $assets = [];

                foreach ($staged as $entry) {
                    $clean = $entry['clean'];

                    $asset = MediaAsset::create([
                        'event_id' => $event->id,
                        'guest_id' => $guest->id,
                        'guest_party_membership_id' => $member?->id,
                        'client_submission_id' => $entry['is_first'] ? $clientSubmissionId : null,
                        'disk' => $disk,
                        'path' => $entry['path'],
                        'original_name' => $entry['file']->getClientOriginalName(),
                        'mime_type' => $clean['mime'],
                        'size_bytes' => strlen($clean['bytes']),
                        'caption' => $entry['is_first'] ? $caption : null,
                        'width' => $clean['width'],
                        'height' => $clean['height'],
                        'checksum' => $clean['checksum'],
                        'status' => MediaStatus::Pending,
                    ]);

                    $this->createConsent($event, $guest, $member, $asset->id, MemoryItemType::MediaAsset, $data);

                    $assets[] = $asset;
                }

                return $assets;
            });

            $committed = true;

            foreach ($assets as $asset) {
                app(MediaDerivativeService::class)->generate($asset);
            }

            $this->audit->guestAction(
                $event,
                $guest,
                'memory.photo.submitted',
                'Foto kenangan dikirim tamu.',
                ['asset_ids' => collect($assets)->pluck('id')->all(), 'count' => count($assets)]
            );

            if ($this->settings->for($event)->auto_approve) {
                foreach ($assets as $asset) {
                    $this->approveGuestAsset($event, $asset);
                }
            }

            return $assets;
        } catch (UniqueConstraintViolationException $exception) {
            // A concurrent retry of the same client submission won the race.
            // Discard the files written here and return the winner instead of
            // failing the guest with a server error.
            $this->discardFiles($disk, $writtenPaths);

            if ($clientSubmissionId !== null) {
                $existing = MediaAsset::query()
                    ->where('event_id', $event->id)
                    ->where('client_submission_id', $clientSubmissionId)
                    ->first();

                if ($existing !== null) {
                    return [$existing];
                }
            }

            throw $exception;
        } catch (Throwable $exception) {
            // Only remove files when the database rows were never committed;
            // once committed, deleting the bytes would orphan the rows.
            if (! $committed) {
                $this->discardFiles($disk, $writtenPaths);
            }

            throw $exception;
        }
    }

    /**
     * Remove staged upload files, ignoring storage failures so the original
     * exception is the one the caller sees.
     *
     * @param  array<int,string>  $paths
     */
    private function discardFiles(string $disk, array $paths): void
    {
        foreach ($paths as $path) {
            Storage::disk($disk)->delete($path);
        }
    }

    /**
     * Store a guestbook message as a memory contribution, optionally linked
     * to a photo asset, with its consent record.
     */
    public function submitMessage(Event $event, Guest $guest, array $data, ?GuestPartyMembership $member = null, ?MediaAsset $photo = null): GuestbookMessage
    {
        if (! $this->settings->canSubmit($event, $guest, $member)) {
            throw ValidationException::withMessages([
                'message' => $this->settings->submissionBlockReason($event, $guest, $member)
                    ?? 'Pengiriman kenangan belum dibuka untuk acara ini.',
            ]);
        }

        $clientSubmissionId = ! empty($data['client_submission_id']) ? (string) $data['client_submission_id'] : null;

        if ($clientSubmissionId !== null) {
            $existing = GuestbookMessage::query()
                ->where('event_id', $event->id)
                ->where('client_submission_id', $clientSubmissionId)
                ->first();

            if ($existing !== null) {
                return $existing;
            }
        }

        $allowEventDisplay = (bool) ($data['allow_event_display'] ?? true);

        // The memory gate above already validated the lifecycle (including the
        // post-event window), so the generic guest-action gate is bypassed here.
        $message = app(GuestbookService::class)->submit($guest, [
            'sender_name' => $data['display_name'] ?? $guest->name,
            'message' => trim((string) ($data['message'] ?? '')),
            'visibility' => $allowEventDisplay ? MessageVisibility::Public->value : MessageVisibility::Private->value,
            'emoji' => $data['emoji'] ?? null,
        ], false);

        $displayName = array_key_exists('display_name', $data) ? (string) $data['display_name'] : null;

        DB::transaction(function () use ($event, $guest, $member, $photo, $clientSubmissionId, $displayName, $data, $message): void {
            $update = [
                'guest_party_membership_id' => $member?->id,
                'client_submission_id' => $clientSubmissionId,
                'photo_asset_id' => $photo?->id,
            ];

            if ($displayName !== null && $displayName !== $message->sender_name) {
                // Deviation: GuestbookService::submit() hardcodes the sender
                // name to the guest account, so the requested display name is
                // applied right after creation.
                $update['sender_name'] = $displayName;
            }

            $message->update($update);

            $this->createConsent($event, $guest, $member, $message->id, MemoryItemType::GuestbookMessage, $data);
        });

        $this->audit->guestAction(
            $event,
            $guest,
            'memory.message.submitted',
            'Ucapan kenangan dikirim tamu.',
            ['message_id' => $message->id, 'visibility' => $message->visibility->value]
        );

        if ($this->settings->for($event)->auto_approve && $message->status !== MessageStatus::Approved) {
            // Deviation: GuestbookService::moderate() requires a User actor
            // which a guest request does not have, so the status is set
            // directly and audited through AuditService::record() instead.
            DB::transaction(function () use ($event, $message): void {
                $message->update([
                    'status' => MessageStatus::Approved,
                    'reviewed_at' => now(),
                ]);

                $this->audit->record(
                    'memory.message.auto_approved',
                    "Ucapan kenangan dari {$message->sender_name} otomatis disetujui.",
                    $event,
                    null,
                    $message,
                    ['status' => MessageStatus::Approved->value]
                );
            });
        }

        return $message->fresh();
    }

    /**
     * Combined photo + message contribution. The photo is stored through the
     * batch flow and the message is linked back to the created asset.
     *
     * @return array{asset:MediaAsset,message:GuestbookMessage}
     */
    public function submitWithPhoto(Event $event, Guest $guest, UploadedFile $file, array $data, ?GuestPartyMembership $member = null): array
    {
        $assets = $this->submitPhotos($event, $guest, [$file], $data, $member);
        $message = $this->submitMessage($event, $guest, $data, $member, $assets[0]);

        return [
            'asset' => $assets[0],
            'message' => $message,
        ];
    }

    /**
     * Remaining photo slots for this guest on the event.
     */
    public function remainingQuota(Event $event, Guest $guest): int
    {
        $used = MediaAsset::query()
            ->where('event_id', $event->id)
            ->where('guest_id', $guest->id)
            ->count();

        return max(0, (int) config('memory.guest_max_per_event', 50) - $used);
    }

    /**
     * Auto-approval for guest flows. MediaService::moderate() requires a
     * User actor which a guest request does not have, so the status is
     * flipped directly and audited through AuditService::record() instead.
     * Derivatives are generated here as well because the pending assets were
     * skipped by the earlier generate() call.
     */
    protected function approveGuestAsset(Event $event, MediaAsset $asset): void
    {
        DB::transaction(function () use ($event, $asset): void {
            $asset->update([
                'status' => MediaStatus::Approved,
                'reviewed_at' => now(),
            ]);

            $this->audit->record(
                'memory.photo.auto_approved',
                "Foto kenangan {$asset->original_name} otomatis disetujui.",
                $event,
                null,
                $asset,
                ['asset_id' => $asset->id, 'status' => MediaStatus::Approved->value]
            );
        });

        app(MediaDerivativeService::class)->generate($asset->fresh());
    }

    /**
     * Record the guest's consent for a single contribution.
     */
    protected function createConsent(Event $event, Guest $guest, ?GuestPartyMembership $member, int $contributionId, MemoryItemType $type, array $data): MemoryConsent
    {
        return MemoryConsent::create([
            'event_id' => $event->id,
            'contribution_type' => $type->value,
            'contribution_id' => $contributionId,
            'guest_id' => $guest->id,
            'guest_party_membership_id' => $member?->id,
            'contributor_name' => $data['display_name'] ?? $guest->name,
            'allow_event_display' => (bool) ($data['allow_event_display'] ?? true),
            'allow_public_display' => (bool) ($data['allow_public_display'] ?? false),
            'terms_version' => config('memory.terms_version'),
            'accepted_at' => now(),
            'ip_address' => request()->ip(),
            'user_agent' => substr((string) request()->userAgent(), 0, 255),
        ]);
    }
}
