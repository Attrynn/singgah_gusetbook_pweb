<?php

namespace App\Services;

use App\Authorization\PermissionRegistry;
use App\Enums\AnnouncementAudience;
use App\Enums\AnnouncementPriority;
use App\Enums\AnnouncementStatus;
use App\Enums\EventStaffRole;
use App\Enums\MembershipStatus;
use App\Models\Event;
use App\Models\EventAnnouncement;
use App\Models\EventAnnouncementRecipient;
use App\Models\EventStaffMembership;
use App\Models\EventStation;
use App\Models\EventStationAssignment;
use App\Models\User;
use App\Notifications\EventAnnouncementPublished;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Event-scoped internal announcements with server-resolved audiences.
 *
 * Read and acknowledgement are distinct persisted facts. A published
 * announcement is never silently rewritten: corrections require a new record.
 */
class AnnouncementService
{
    public function __construct(
        protected AuditService $audit,
        protected PermissionService $permissions,
    ) {}

    public function create(Event $event, array $data, User $actor): EventAnnouncement
    {
        $this->assertActorHas($event, $actor, PermissionRegistry::ANNOUNCEMENT_MANAGE, 'Anda tidak berwenang membuat pengumuman.');

        $title = trim((string) ($data['title'] ?? ''));
        $body = trim((string) ($data['body'] ?? ''));

        if ($title === '' || $body === '') {
            throw ValidationException::withMessages(['title' => 'Judul dan isi pengumuman wajib diisi.']);
        }

        $priority = $this->resolvePriority($data['priority'] ?? null);
        $audience = $this->resolveAudience($data['audience'] ?? null);
        $audienceRef = $this->normalizeAudienceRef($event, $audience, $data['audience_ref'] ?? []);

        $announcement = EventAnnouncement::create([
            'event_id' => $event->id,
            'title' => $title,
            'body' => $body,
            'priority' => $priority,
            'audience' => $audience,
            'audience_ref' => $audienceRef,
            'status' => AnnouncementStatus::Draft,
            'requires_acknowledgement' => array_key_exists('requires_acknowledgement', $data)
                ? (bool) $data['requires_acknowledgement']
                : $priority->requiresAcknowledgementByDefault(),
            'expires_at' => $this->toUtc($data['expires_at'] ?? null, $event),
            'created_by' => $actor->id,
        ]);

        $this->audit->record(
            'announcement.created',
            "Pengumuman dibuat: {$announcement->title}",
            $event,
            $actor,
            $announcement,
            ['audience' => $audience->value],
        );

        return $announcement->fresh();
    }

    public function update(Event $event, EventAnnouncement $announcement, array $data, User $actor): EventAnnouncement
    {
        $this->assertAnnouncementBelongsToEvent($event, $announcement);
        $this->assertActorHas($event, $actor, PermissionRegistry::ANNOUNCEMENT_MANAGE, 'Anda tidak berwenang mengubah pengumuman.');

        if ($announcement->status !== AnnouncementStatus::Draft) {
            throw ValidationException::withMessages([
                'status' => 'Pengumuman yang sudah dijadwalkan atau diterbitkan tidak dapat diubah. Terbitkan pengumuman baru untuk koreksi.',
            ]);
        }

        $payload = [];

        if (array_key_exists('title', $data)) {
            $title = trim((string) $data['title']);

            if ($title === '') {
                throw ValidationException::withMessages(['title' => 'Judul pengumuman wajib diisi.']);
            }

            $payload['title'] = $title;
        }

        if (array_key_exists('body', $data)) {
            $body = trim((string) $data['body']);

            if ($body === '') {
                throw ValidationException::withMessages(['body' => 'Isi pengumuman wajib diisi.']);
            }

            $payload['body'] = $body;
        }

        if (array_key_exists('priority', $data)) {
            $payload['priority'] = $this->resolvePriority($data['priority']);
        }

        if (array_key_exists('audience', $data)) {
            $audience = $this->resolveAudience($data['audience']);
            $payload['audience'] = $audience;
            $payload['audience_ref'] = $this->normalizeAudienceRef($event, $audience, $data['audience_ref'] ?? $announcement->audience_ref ?? []);
        }

        if (array_key_exists('requires_acknowledgement', $data)) {
            $payload['requires_acknowledgement'] = (bool) $data['requires_acknowledgement'];
        }

        if (array_key_exists('expires_at', $data)) {
            $payload['expires_at'] = $this->toUtc($data['expires_at'], $event);
        }

        $announcement->update($payload);

        $this->audit->record(
            'announcement.updated',
            "Pengumuman diperbarui: {$announcement->title}",
            $event,
            $actor,
            $announcement,
        );

        return $announcement->fresh();
    }

    public function publish(Event $event, EventAnnouncement $announcement, User $actor): EventAnnouncement
    {
        $this->assertAnnouncementBelongsToEvent($event, $announcement);
        $this->assertActorHas($event, $actor, PermissionRegistry::ANNOUNCEMENT_PUBLISH, 'Anda tidak berwenang menerbitkan pengumuman.');

        if (! in_array($announcement->status, [AnnouncementStatus::Draft, AnnouncementStatus::Scheduled], true)) {
            throw ValidationException::withMessages(['status' => 'Pengumuman ini tidak dapat diterbitkan dari status saat ini.']);
        }

        $recipients = $this->resolveRecipients($event, $announcement);

        return DB::transaction(function () use ($event, $announcement, $actor, $recipients): EventAnnouncement {
            $announcement->update([
                'status' => AnnouncementStatus::Published,
                'published_at' => now(),
                'published_by' => $actor->id,
            ]);

            foreach ($recipients as $user) {
                $recipient = EventAnnouncementRecipient::firstOrCreate(
                    [
                        'event_announcement_id' => $announcement->id,
                        'user_id' => $user->id,
                    ],
                    ['delivered_at' => now()],
                );

                if ($recipient->delivered_at === null) {
                    $recipient->update(['delivered_at' => now()]);
                }

                if ($recipient->wasRecentlyCreated) {
                    $user->notify(new EventAnnouncementPublished($announcement));
                }
            }

            $this->audit->record(
                'announcement.published',
                "Pengumuman diterbitkan: {$announcement->title}",
                $event,
                $actor,
                $announcement,
                ['recipients' => $recipients->count()],
            );

            return $announcement->fresh();
        });
    }

    public function withdraw(Event $event, EventAnnouncement $announcement, User $actor): EventAnnouncement
    {
        $this->assertAnnouncementBelongsToEvent($event, $announcement);
        $this->assertActorHas($event, $actor, PermissionRegistry::ANNOUNCEMENT_PUBLISH, 'Anda tidak berwenang menarik pengumuman.');

        if (! $announcement->status->canTransitionTo(AnnouncementStatus::Withdrawn)) {
            throw ValidationException::withMessages(['status' => 'Pengumuman ini tidak dapat ditarik dari status saat ini.']);
        }

        $announcement->update([
            'status' => AnnouncementStatus::Withdrawn,
            'withdrawn_at' => now(),
        ]);

        $this->audit->record(
            'announcement.withdrawn',
            "Pengumuman ditarik: {$announcement->title}",
            $event,
            $actor,
            $announcement,
        );

        return $announcement->fresh();
    }

    public function expireDue(Event $event): int
    {
        return EventAnnouncement::query()
            ->where('event_id', $event->id)
            ->where('status', AnnouncementStatus::Published->value)
            ->whereNotNull('expires_at')
            ->where('expires_at', '<=', now())
            ->update(['status' => AnnouncementStatus::Expired->value, 'updated_at' => now()]);
    }

    public function markRead(EventAnnouncement $announcement, User $user): EventAnnouncementRecipient
    {
        $recipient = $this->recipientFor($announcement, $user);

        if ($recipient->read_at === null) {
            $recipient->update(['read_at' => now()]);
        }

        return $recipient->fresh();
    }

    public function acknowledge(EventAnnouncement $announcement, User $user): EventAnnouncementRecipient
    {
        if (! $announcement->requires_acknowledgement) {
            throw ValidationException::withMessages(['acknowledgement' => 'Pengumuman ini tidak memerlukan konfirmasi.']);
        }

        $recipient = $this->recipientFor($announcement, $user);

        $recipient->update([
            'acknowledged_at' => $recipient->acknowledged_at ?? now(),
            'read_at' => $recipient->read_at ?? now(),
        ]);

        return $recipient->fresh();
    }

    /**
     * @return array{total:int,read:int,acknowledged:int}
     */
    public function progress(EventAnnouncement $announcement): array
    {
        $query = $announcement->recipients();

        return [
            'total' => (clone $query)->count(),
            'read' => (clone $query)->whereNotNull('read_at')->count(),
            'acknowledged' => (clone $query)->whereNotNull('acknowledged_at')->count(),
        ];
    }

    /**
     * Server-side audience resolution. Client-supplied recipient lists are ignored.
     *
     * @return Collection<int, User>
     */
    public function resolveRecipients(Event $event, EventAnnouncement $announcement): Collection
    {
        $memberships = EventStaffMembership::query()
            ->where('event_id', $event->id)
            ->where('status', MembershipStatus::Active->value)
            ->with('user')
            ->get();

        $memberships = match ($announcement->audience) {
            AnnouncementAudience::AllStaff => $memberships,
            AnnouncementAudience::Station => $memberships->whereIn(
                'id',
                $this->membershipIdsForStations($event, $announcement->audience_ref['station_ids'] ?? []),
            ),
            AnnouncementAudience::SelectedStaff => $memberships->whereIn(
                'id',
                $announcement->audience_ref['membership_ids'] ?? [],
            ),
            AnnouncementAudience::Profile => $memberships->whereIn(
                'role',
                array_map(
                    fn (string $role) => EventStaffRole::from($role),
                    $announcement->audience_ref['roles'] ?? [],
                ),
            ),
        };

        return $memberships
            ->map(fn (EventStaffMembership $membership) => $membership->user)
            ->filter(fn (?User $user) => $user !== null && $user->canAccessEvent($event))
            ->unique('id')
            ->values();
    }

    /**
     * @return array<string, mixed>
     */
    protected function normalizeAudienceRef(Event $event, AnnouncementAudience $audience, mixed $ref): array
    {
        $ref = is_array($ref) ? $ref : [];

        return match ($audience) {
            AnnouncementAudience::AllStaff => [],
            AnnouncementAudience::Station => [
                'station_ids' => $this->validateStationIds($event, $ref['station_ids'] ?? []),
            ],
            AnnouncementAudience::SelectedStaff => [
                'membership_ids' => $this->validateMembershipIds($event, $ref['membership_ids'] ?? []),
            ],
            AnnouncementAudience::Profile => [
                'roles' => $this->validateRoles($ref['roles'] ?? []),
            ],
        };
    }

    /**
     * @return array<int, int>
     */
    protected function validateStationIds(Event $event, mixed $ids): array
    {
        $ids = collect(is_array($ids) ? $ids : [])->map(fn ($id) => (int) $id)->filter()->unique()->values();

        if ($ids->isEmpty()) {
            throw ValidationException::withMessages(['audience_ref' => 'Pilih setidaknya satu stasiun.']);
        }

        $valid = EventStation::query()
            ->where('event_id', $event->id)
            ->whereIn('id', $ids)
            ->pluck('id')
            ->all();

        if (count($valid) !== $ids->count()) {
            throw ValidationException::withMessages(['audience_ref' => 'Stasiun tidak valid untuk event ini.']);
        }

        return array_values($valid);
    }

    /**
     * @return array<int, int>
     */
    protected function validateMembershipIds(Event $event, mixed $ids): array
    {
        $ids = collect(is_array($ids) ? $ids : [])->map(fn ($id) => (int) $id)->filter()->unique()->values();

        if ($ids->isEmpty()) {
            throw ValidationException::withMessages(['audience_ref' => 'Pilih setidaknya satu anggota staf.']);
        }

        $valid = EventStaffMembership::query()
            ->where('event_id', $event->id)
            ->where('status', MembershipStatus::Active->value)
            ->whereIn('id', $ids)
            ->pluck('id')
            ->all();

        if (count($valid) !== $ids->count()) {
            throw ValidationException::withMessages(['audience_ref' => 'Anggota staf tidak valid untuk event ini.']);
        }

        return array_values($valid);
    }

    /**
     * @return array<int, string>
     */
    protected function validateRoles(mixed $roles): array
    {
        $roles = collect(is_array($roles) ? $roles : [])->filter()->unique()->values();

        if ($roles->isEmpty()) {
            throw ValidationException::withMessages(['audience_ref' => 'Pilih setidaknya satu profil.']);
        }

        $valid = [];

        foreach ($roles as $role) {
            $resolved = EventStaffRole::tryFrom((string) $role);

            if ($resolved === null) {
                throw ValidationException::withMessages(['audience_ref' => 'Profil staf tidak dikenal.']);
            }

            $valid[] = $resolved->value;
        }

        return array_values(array_unique($valid));
    }

    /**
     * @param  array<int, int>  $stationIds
     * @return array<int, int>
     */
    protected function membershipIdsForStations(Event $event, array $stationIds): array
    {
        return EventStationAssignment::query()
            ->where('event_id', $event->id)
            ->whereIn('station_id', $stationIds)
            ->whereNull('removed_at')
            ->pluck('staff_membership_id')
            ->all();
    }

    protected function recipientFor(EventAnnouncement $announcement, User $user): EventAnnouncementRecipient
    {
        $recipient = EventAnnouncementRecipient::query()
            ->where('event_announcement_id', $announcement->id)
            ->where('user_id', $user->id)
            ->first();

        if ($recipient === null) {
            abort(403, 'Anda bukan penerima pengumuman ini.');
        }

        return $recipient;
    }

    protected function resolvePriority(mixed $priority): AnnouncementPriority
    {
        if ($priority instanceof AnnouncementPriority) {
            return $priority;
        }

        if (blank($priority)) {
            return AnnouncementPriority::Normal;
        }

        $resolved = AnnouncementPriority::tryFrom((string) $priority);

        if ($resolved === null) {
            throw ValidationException::withMessages(['priority' => 'Prioritas pengumuman tidak dikenal.']);
        }

        return $resolved;
    }

    protected function resolveAudience(mixed $audience): AnnouncementAudience
    {
        if ($audience instanceof AnnouncementAudience) {
            return $audience;
        }

        if (blank($audience)) {
            return AnnouncementAudience::AllStaff;
        }

        $resolved = AnnouncementAudience::tryFrom((string) $audience);

        if ($resolved === null) {
            throw ValidationException::withMessages(['audience' => 'Target audiens tidak dikenal.']);
        }

        return $resolved;
    }

    protected function toUtc(mixed $value, Event $event): ?Carbon
    {
        if (blank($value)) {
            return null;
        }

        try {
            return Carbon::parse((string) $value, $event->timezoneName())->utc();
        } catch (\Throwable) {
            throw ValidationException::withMessages(['expires_at' => 'Format waktu tidak valid.']);
        }
    }

    protected function assertActorHas(Event $event, User $actor, string $permission, string $message): void
    {
        if (! $this->permissions->hasEventPermission($actor, $event, $permission)) {
            abort(403, $message);
        }
    }

    protected function assertAnnouncementBelongsToEvent(Event $event, EventAnnouncement $announcement): void
    {
        abort_unless((int) $announcement->event_id === (int) $event->id, 404);
    }
}
