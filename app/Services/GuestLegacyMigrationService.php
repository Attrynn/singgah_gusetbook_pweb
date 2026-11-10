<?php

namespace App\Services;

use App\Enums\GuestPartyStatus;
use App\Enums\TokenType;
use App\Models\Checkin;
use App\Models\Event;
use App\Models\Guest;
use App\Models\GuestPartyMembership;
use App\Models\GuestToken;
use App\Models\Rsvp;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Idempotent backfill of legacy Guest records into the new Party + Individual
 * Guest and Pass foundation.
 *
 * Contracts enforced here:
 *  - Each legacy guest maps to exactly one identified individual membership.
 *  - Unidentified quota slots are counted but never fabricated as rows.
 *  - RSVP / checkin / token counts are preserved as metrics only.
 *  - Running twice is safe (existing memberships are skipped).
 */
class GuestLegacyMigrationService
{
    public function __construct(
        protected GuestPassService $passService,
        protected AuditService $audit,
    ) {}

    /**
     * @return array{
     *     dry_run: bool,
     *     total_legacy_guests: int,
     *     total_parties_mapped: int,
     *     total_members_created: int,
     *     total_passes_issued: int,
     *     already_migrated_skipped: int,
     *     unidentified_capacity_retained: int,
     *     total_rsvps_preserved: int,
     *     total_checkins_preserved: int,
     *     total_tokens_preserved: int,
     *     errors: array<int, string>
     * }
     */
    public function migrate(?Event $event = null, bool $dryRun = false, ?User $actor = null): array
    {
        $query = Guest::query()->withTrashed();
        if ($event !== null) {
            $query->where('event_id', $event->id);
        }

        $totalLegacyGuests = (clone $query)->count();
        $totalPartiesMapped = 0;
        $totalMembersCreated = 0;
        $totalPassesIssued = 0;
        $alreadyMigratedSkipped = 0;
        $unidentifiedCapacityRetained = 0;
        $errors = [];

        // Baseline counts before migration.
        $totalRsvpsPreserved = Rsvp::query()->count();
        $totalCheckinsPreserved = Checkin::query()->count();
        $totalTokensPreserved = GuestToken::query()->count();

        $guests = $query->orderBy('id')->get();

        foreach ($guests as $guest) {
            try {
                $existingMember = GuestPartyMembership::withTrashed()
                    ->where('guest_id', $guest->id)
                    ->where('name', $guest->name)
                    ->first();

                if ($existingMember) {
                    $alreadyMigratedSkipped++;
                    $totalPartiesMapped++;

                    continue;
                }

                // Each identified individual consumes one quota slot. The
                // remaining slots are unidentified capacity retained as a
                // number, never materialised as memberships.
                $unidentifiedSlots = max(0, (int) $guest->max_attendees - 1);
                $unidentifiedCapacityRetained += $unidentifiedSlots;

                if (! $dryRun) {
                    DB::transaction(function () use ($guest, $actor, &$totalMembersCreated, &$totalPassesIssued): void {
                        $member = GuestPartyMembership::create([
                            'guest_id' => $guest->id,
                            'event_id' => $guest->event_id,
                            'name' => $guest->name,
                            'email' => $guest->email,
                            'phone' => $guest->phone,
                            'status' => $guest->trashed() ? GuestPartyStatus::Removed : GuestPartyStatus::Active,
                            'created_by' => $guest->created_by,
                        ]);

                        // Only the primary identified individual gets a pass.
                        if (! $guest->trashed()) {
                            $this->passService->issue($member, TokenType::Invitation, $actor);
                            $totalPassesIssued++;
                        }

                        $totalMembersCreated++;
                    });
                } else {
                    $totalMembersCreated++;
                    if (! $guest->trashed()) {
                        $totalPassesIssued++;
                    }
                }

                $totalPartiesMapped++;
            } catch (\Throwable $e) {
                $errorMsg = "Gagal memigrasi Guest ID {$guest->id} ({$guest->name}): ".$e->getMessage();
                $errors[] = $errorMsg;
                Log::error($errorMsg, ['exception' => $e]);
            }
        }

        if (! $dryRun && $actor !== null) {
            $this->audit->record(
                'guest.legacy.migrated',
                "Migrasi data legacy guest selesai. Parties: {$totalPartiesMapped}, Anggota: {$totalMembersCreated}, Pass: {$totalPassesIssued}",
                $event,
                $actor,
                null,
                [
                    'total_legacy_guests' => $totalLegacyGuests,
                    'total_members_created' => $totalMembersCreated,
                    'total_passes_issued' => $totalPassesIssued,
                    'skipped' => $alreadyMigratedSkipped,
                ],
            );
        }

        return [
            'dry_run' => $dryRun,
            'total_legacy_guests' => $totalLegacyGuests,
            'total_parties_mapped' => $totalPartiesMapped,
            'total_members_created' => $totalMembersCreated,
            'total_passes_issued' => $totalPassesIssued,
            'already_migrated_skipped' => $alreadyMigratedSkipped,
            'unidentified_capacity_retained' => $unidentifiedCapacityRetained,
            'total_rsvps_preserved' => $totalRsvpsPreserved,
            'total_checkins_preserved' => $totalCheckinsPreserved,
            'total_tokens_preserved' => $totalTokensPreserved,
            'errors' => $errors,
        ];
    }
}
