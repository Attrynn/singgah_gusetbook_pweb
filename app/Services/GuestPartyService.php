<?php

namespace App\Services;

use App\Enums\GuestPartyStatus;
use App\Enums\GuestStatus;
use App\Enums\RsvpStatus;
use App\Enums\TokenType;
use App\Models\Event;
use App\Models\Guest;
use App\Models\GuestPartyMembership;
use App\Models\GuestPass;
use App\Models\Rsvp;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Orchestrates invitation parties (legacy `guests` rows) and their identified
 * individuals (`guest_party_memberships`).
 *
 * Quota semantics are preserved: `guests.max_attendees` remains the party quota,
 * distinct from the identified roster and from `rsvps.attendance_count`.
 */
class GuestPartyService
{
    public function __construct(
        protected GuestPassService $passService,
        protected InvitationService $invitationService,
        protected AuditService $audit,
    ) {}

    /**
     * Create an invitation party with quota, optional grouping, and optional
     * initial identified members.
     *
     * @param  array{
     *     name: string,
     *     max_attendees?: int,
     *     guest_category_id?: ?int,
     *     guest_group_id?: ?int,
     *     email?: ?string,
     *     phone?: ?string,
     *     notes?: ?string,
     *     status?: string,
     *     members?: array<int, array{name: string, email?: ?string, phone?: ?string}>
     * }  $data
     */
    public function createParty(Event $event, array $data, ?User $actor = null): Guest
    {
        $quota = (int) ($data['max_attendees'] ?? 2);
        if ($quota < 1) {
            throw ValidationException::withMessages([
                'max_attendees' => ['Kuota undangan minimal 1 orang.'],
            ]);
        }

        $initialMembers = array_values(array_filter(
            $data['members'] ?? [],
            fn ($member) => is_array($member) && ! empty($member['name']),
        ));

        if (count($initialMembers) > $quota) {
            throw ValidationException::withMessages([
                'members' => ['Jumlah individu terdaftar melebihi kuota undangan ('.$quota.' orang).'],
            ]);
        }

        return DB::transaction(function () use ($event, $data, $quota, $initialMembers, $actor): Guest {
            $party = Guest::create([
                'event_id' => $event->id,
                'guest_category_id' => $data['guest_category_id'] ?? null,
                'guest_group_id' => $data['guest_group_id'] ?? null,
                'name' => $data['name'],
                'email' => $data['email'] ?? null,
                'phone' => $data['phone'] ?? null,
                'notes' => $data['notes'] ?? null,
                'status' => $data['status'] ?? GuestStatus::Active->value,
                'max_attendees' => $quota,
                'rsvp_status' => RsvpStatus::Pending->value,
                'created_by' => $actor?->id,
            ]);

            // Issue the legacy party-level invitation token so existing invitation
            // and QR flows keep working unchanged.
            $this->invitationService->issueForGuest($party);

            foreach ($initialMembers as $memberData) {
                $member = GuestPartyMembership::create([
                    'guest_id' => $party->id,
                    'event_id' => $event->id,
                    'name' => trim((string) $memberData['name']),
                    'email' => $memberData['email'] ?? null,
                    'phone' => $memberData['phone'] ?? null,
                    'status' => GuestPartyStatus::Active->value,
                    'created_by' => $actor?->id,
                ]);

                $this->passService->issue($member, TokenType::Invitation, $actor);
            }

            $this->audit->record(
                'guest.party.created',
                "Invitation party dibuat: {$party->name} (Kuota: {$party->max_attendees})",
                $event,
                $actor,
                $party,
                ['quota' => $party->max_attendees, 'initial_members' => count($initialMembers)],
            );

            return $party->load(['members', 'category', 'household']);
        });
    }

    /**
     * Update an invitation party while preserving quota integrity.
     *
     * @param  array{
     *     name?: string,
     *     max_attendees?: int,
     *     guest_category_id?: ?int,
     *     guest_group_id?: ?int,
     *     email?: ?string,
     *     phone?: ?string,
     *     notes?: ?string,
     *     status?: string
     * }  $data
     */
    public function updateParty(Guest $party, array $data, ?User $actor = null): Guest
    {
        $identifiedCount = $party->members()->where('status', GuestPartyStatus::Active->value)->count();

        if (isset($data['max_attendees'])) {
            $newQuota = (int) $data['max_attendees'];

            if ($newQuota < 1) {
                throw ValidationException::withMessages([
                    'max_attendees' => ['Kuota undangan minimal 1 orang.'],
                ]);
            }

            if ($newQuota < $identifiedCount) {
                throw ValidationException::withMessages([
                    'max_attendees' => [
                        "Kuota baru ({$newQuota}) tidak boleh lebih kecil dari jumlah individu terdaftar ({$identifiedCount} orang). Hapus individu terlebih dahulu.",
                    ],
                ]);
            }
        }

        return DB::transaction(function () use ($party, $data, $actor): Guest {
            $oldQuota = $party->max_attendees;
            $party->update($data);

            $this->audit->record(
                'guest.party.updated',
                "Invitation party diperbarui: {$party->name}",
                $party->event,
                $actor,
                $party,
                ['old_quota' => $oldQuota, 'new_quota' => $party->max_attendees],
            );

            return $party->fresh(['members', 'category', 'household']);
        });
    }

    /**
     * Add an identified individual to an invitation party.
     *
     * @param  array{name: string, email?: ?string, phone?: ?string}  $data
     */
    public function addMember(Guest $party, array $data, ?User $actor = null): GuestPartyMembership
    {
        $activeCount = $party->members()->where('status', GuestPartyStatus::Active->value)->count();

        if ($activeCount >= $party->max_attendees) {
            throw ValidationException::withMessages([
                'name' => [
                    "Kuota undangan party '{$party->name}' telah penuh ({$party->max_attendees}/{$party->max_attendees}). Tingkatkan kuota terlebih dahulu.",
                ],
            ]);
        }

        return DB::transaction(function () use ($party, $data, $actor): GuestPartyMembership {
            $name = trim((string) $data['name']);

            // Restore a previously removed individual with the same name instead
            // of tripping the (guest_id, name) unique constraint.
            $existing = GuestPartyMembership::withTrashed()
                ->where('guest_id', $party->id)
                ->where('name', $name)
                ->first();

            if ($existing) {
                $existing->restore();
                $existing->update([
                    'event_id' => $party->event_id,
                    'email' => $data['email'] ?? null,
                    'phone' => $data['phone'] ?? null,
                    'status' => GuestPartyStatus::Active->value,
                ]);
                $member = $existing;
            } else {
                $member = GuestPartyMembership::create([
                    'guest_id' => $party->id,
                    'event_id' => $party->event_id,
                    'name' => $name,
                    'email' => $data['email'] ?? null,
                    'phone' => $data['phone'] ?? null,
                    'status' => GuestPartyStatus::Active->value,
                    'created_by' => $actor?->id,
                ]);
            }

            $this->passService->issue($member, TokenType::Invitation, $actor);

            $this->audit->record(
                'guest.member.created',
                "Individu ditambahkan ke party '{$party->name}': {$member->name}",
                $party->event,
                $actor,
                $member,
            );

            return $member->load('passes');
        });
    }

    /**
     * Update an identified individual's details.
     *
     * @param  array{name?: string, email?: ?string, phone?: ?string, status?: string}  $data
     */
    public function updateMember(GuestPartyMembership $member, array $data, ?User $actor = null): GuestPartyMembership
    {
        return DB::transaction(function () use ($member, $data, $actor): GuestPartyMembership {
            $member->update($data);

            $this->audit->record(
                'guest.member.updated',
                "Data individu diperbarui: {$member->name}",
                $member->event,
                $actor,
                $member,
            );

            return $member->fresh(['passes', 'guest']);
        });
    }

    /**
     * Remove an individual from a party: revoke passes, mark removed, soft delete.
     */
    public function removeMember(GuestPartyMembership $member, ?User $actor = null): void
    {
        DB::transaction(function () use ($member, $actor): void {
            $party = $member->guest;

            foreach ($this->passService->activePassesForMember($member) as $pass) {
                $this->passService->revoke($pass, $actor, 'Individu dihapus dari invitation party');
            }

            $member->update(['status' => GuestPartyStatus::Removed->value]);
            $member->delete();

            $this->audit->record(
                'guest.member.removed',
                "Individu dihapus dari party '{$party?->name}': {$member->name}",
                $member->event,
                $actor,
                $member,
            );
        });
    }

    /**
     * Move an individual to another party within the same event.
     */
    public function moveMember(GuestPartyMembership $member, Guest $targetParty, ?User $actor = null): GuestPartyMembership
    {
        if ((int) $member->event_id !== (int) $targetParty->event_id) {
            throw ValidationException::withMessages([
                'target_party_id' => ['Individu hanya dapat dipindahkan ke party dalam event yang sama.'],
            ]);
        }

        $activeTargetCount = $targetParty->members()->where('status', GuestPartyStatus::Active->value)->count();

        if ($activeTargetCount >= $targetParty->max_attendees) {
            throw ValidationException::withMessages([
                'target_party_id' => [
                    "Kuota party tujuan '{$targetParty->name}' telah penuh ({$targetParty->max_attendees}/{$targetParty->max_attendees}).",
                ],
            ]);
        }

        return DB::transaction(function () use ($member, $targetParty, $actor): GuestPartyMembership {
            $sourceParty = $member->guest;
            $member->update(['guest_id' => $targetParty->id]);

            $this->audit->record(
                'guest.member.moved',
                "Individu {$member->name} dipindahkan dari '{$sourceParty?->name}' ke '{$targetParty->name}'",
                $targetParty->event,
                $actor,
                $member,
                ['source_party_id' => $sourceParty?->id, 'target_party_id' => $targetParty->id],
            );

            return $member->fresh(['guest', 'passes']);
        });
    }

    /**
     * Archive an invitation party and revoke its members' active passes.
     */
    public function archiveParty(Guest $party, ?User $actor = null): void
    {
        DB::transaction(function () use ($party, $actor): void {
            foreach ($party->members()->get() as $member) {
                foreach ($this->passService->activePassesForMember($member) as $pass) {
                    $this->passService->revoke($pass, $actor, 'Party diarsipkan');
                }
            }

            $party->update(['status' => GuestStatus::Inactive->value]);
            $party->delete();

            $this->audit->record(
                'guest.party.archived',
                "Invitation party diarsipkan: {$party->name}",
                $party->event,
                $actor,
                $party,
            );
        });
    }

    /**
     * Import parties from a CSV file. One row creates one party with the named
     * individual as its first identified member.
     *
     * @return array{imported: int, skipped: int, errors: array<int, string>}
     */
    public function importCsv(Event $event, string $path, ?User $actor = null): array
    {
        $handle = fopen($path, 'r');

        if (! $handle) {
            return ['imported' => 0, 'skipped' => 0, 'errors' => ['File tidak dapat dibaca.']];
        }

        try {
            $header = fgetcsv($handle);

            if (! is_array($header) || $header === [null]) {
                return ['imported' => 0, 'skipped' => 0, 'errors' => ['CSV kosong atau header tidak ditemukan.']];
            }

            $header[0] = preg_replace('/^\xEF\xBB\xBF/', '', (string) $header[0]);
            $normalized = array_map(fn ($value) => strtolower(trim((string) $value)), $header);

            if (! in_array('name', $normalized, true) && ! in_array('nama', $normalized, true)) {
                return ['imported' => 0, 'skipped' => 0, 'errors' => ['CSV harus memiliki kolom "name" atau "nama".']];
            }

            $imported = 0;
            $skipped = 0;
            $errors = [];
            $lineNumber = 1;

            while (($row = fgetcsv($handle)) !== false) {
                $lineNumber++;

                if ($row === [null] || implode('', array_map('trim', $row)) === '') {
                    continue;
                }

                if (count($row) !== count($normalized)) {
                    $errors[] = "Baris {$lineNumber}: jumlah kolom tidak sesuai header.";
                    $skipped++;

                    continue;
                }

                $data = array_combine($normalized, $row);
                $name = trim((string) ($data['name'] ?? $data['nama'] ?? ''));

                if ($name === '') {
                    $errors[] = "Baris {$lineNumber}: nama party kosong.";
                    $skipped++;

                    continue;
                }

                try {
                    $this->createParty($event, [
                        'name' => $name,
                        'max_attendees' => max(1, (int) ($data['max_attendees'] ?? $data['kuota'] ?? $data['quota'] ?? 2)),
                        'email' => $this->nullable($data['email'] ?? null),
                        'phone' => $this->nullable($data['phone'] ?? $data['telepon'] ?? null),
                        'notes' => $this->nullable($data['notes'] ?? $data['catatan'] ?? null),
                        'members' => [
                            [
                                'name' => $name,
                                'email' => $this->nullable($data['email'] ?? null),
                                'phone' => $this->nullable($data['phone'] ?? $data['telepon'] ?? null),
                            ],
                        ],
                    ], $actor);
                    $imported++;
                } catch (ValidationException $exception) {
                    $errors[] = "Baris {$lineNumber}: ".implode(' ', $exception->errors()['max_attendees'] ?? $exception->errors()['members'] ?? ['Data tidak valid.']);
                    $skipped++;
                }
            }

            return ['imported' => $imported, 'skipped' => $skipped, 'errors' => $errors];
        } finally {
            fclose($handle);
        }
    }

    /**
     * Aggregate guest workspace metrics for one event.
     *
     * Distinguishes parties, identified individuals, quota, legacy RSVP
     * attendance, and unidentified capacity.
     *
     * @return array{
     *     total_parties: int,
     *     total_identified: int,
     *     total_quota: int,
     *     total_rsvp_attendance: int,
     *     unidentified_capacity: int,
     *     passes_issued: int,
     *     parties_with_pending_roster: int,
     *     category_distribution: array<string, int>,
     *     household_distribution: array<string, int>
     * }
     */
    public function getOverviewStats(Event $event): array
    {
        $partiesQuery = Guest::query()->where('guests.event_id', $event->id);

        $totalParties = (clone $partiesQuery)->count();
        $totalQuota = (int) (clone $partiesQuery)->sum('max_attendees');

        $totalIdentified = GuestPartyMembership::where('event_id', $event->id)
            ->where('status', GuestPartyStatus::Active->value)
            ->count();

        $totalRsvpAttendance = (int) Rsvp::where('event_id', $event->id)
            ->where('status', RsvpStatus::Confirmed->value)
            ->sum('attendance_count');

        $unidentifiedCapacity = max(0, $totalQuota - $totalIdentified);

        $passesIssued = GuestPass::where('event_id', $event->id)
            ->where('status', 'active')
            ->whereNull('revoked_at')
            ->count();

        $partiesWithPendingRoster = (clone $partiesQuery)
            ->where('status', GuestStatus::Active->value)
            ->withCount(['members as active_members_count' => fn ($query) => $query->where('status', GuestPartyStatus::Active->value)])
            ->get()
            ->filter(fn ($party) => (int) $party->active_members_count < (int) $party->max_attendees)
            ->count();

        $categoryDistribution = (clone $partiesQuery)
            ->join('guest_categories', 'guests.guest_category_id', '=', 'guest_categories.id')
            ->select('guest_categories.name', DB::raw('count(*) as count'))
            ->groupBy('guest_categories.name')
            ->pluck('count', 'name')
            ->toArray();

        $householdDistribution = (clone $partiesQuery)
            ->join('guest_groups', 'guests.guest_group_id', '=', 'guest_groups.id')
            ->select('guest_groups.name', DB::raw('count(*) as count'))
            ->groupBy('guest_groups.name')
            ->pluck('count', 'name')
            ->toArray();

        return [
            'total_parties' => $totalParties,
            'total_identified' => $totalIdentified,
            'total_quota' => $totalQuota,
            'total_rsvp_attendance' => $totalRsvpAttendance,
            'unidentified_capacity' => $unidentifiedCapacity,
            'passes_issued' => $passesIssued,
            'parties_with_pending_roster' => $partiesWithPendingRoster,
            'category_distribution' => $categoryDistribution,
            'household_distribution' => $householdDistribution,
        ];
    }

    protected function nullable(mixed $value): ?string
    {
        $value = is_string($value) ? trim($value) : $value;

        return ($value === null || $value === '') ? null : (string) $value;
    }
}
