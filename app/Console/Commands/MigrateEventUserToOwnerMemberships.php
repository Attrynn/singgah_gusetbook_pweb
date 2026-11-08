<?php

namespace App\Console\Commands;

use App\Enums\EventOwnerRole;
use App\Enums\EventUserRole;
use App\Enums\MembershipStatus;
use App\Models\EventOwnerMembership;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class MigrateEventUserToOwnerMemberships extends Command
{
    protected $signature = 'vowspace:migrate-event-owner-memberships
                            {--dry-run : Show what would be migrated without modifying data}
                            {--force : Re-process rows even if they already have a matching EventOwnerMembership}';

    protected $description = 'Migrate event_user pivot rows to event_owner_memberships. Idempotent.';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $force = (bool) $this->option('force');

        // Load all event_user rows. Use raw DB to avoid Eloquent observers/events.
        $rows = DB::table('event_user')->get();

        if ($rows->isEmpty()) {
            $this->info('No event_user rows found; nothing to migrate.');

            return self::SUCCESS;
        }

        $this->info("Found {$rows->count()} event_user row(s) to inspect.");

        $migrated = 0;
        $skipped = 0;
        $errors = 0;
        $exceptions = [];

        foreach ($rows as $row) {
            // Validate role_label is a known couple role.
            $ownerRole = $this->resolveOwnerRole($row->role_label ?? '');

            if ($ownerRole === null) {
                $exceptions[] = "  - event_user id={$row->id}: unrecognised role_label='{$row->role_label}' — skipped";
                $errors++;

                continue;
            }

            // Idempotency: skip if an active EventOwnerMembership already exists for this pair.
            if (! $force) {
                $exists = DB::table('event_owner_memberships')
                    ->where('event_id', $row->event_id)
                    ->where('user_id', $row->user_id)
                    ->exists();

                if ($exists) {
                    $this->line("  - skip event_id={$row->event_id} user_id={$row->user_id}: already migrated");
                    $skipped++;

                    continue;
                }
            }

            if ($dryRun) {
                $this->line("  - [dry-run] would migrate event_id={$row->event_id} user_id={$row->user_id} role={$ownerRole->value}");
                $migrated++;

                continue;
            }

            try {
                DB::transaction(function () use ($row, $ownerRole): void {
                    DB::table('event_owner_memberships')->updateOrInsert(
                        [
                            'event_id' => $row->event_id,
                            'user_id' => $row->user_id,
                        ],
                        [
                            'role_label' => $ownerRole->value,
                            'status' => MembershipStatus::Active->value,
                            'invited_by' => null,
                            'accepted_at' => $row->created_at ?? now(),
                            'revoked_at' => null,
                            'created_at' => $row->created_at ?? now(),
                            'updated_at' => $row->updated_at ?? now(),
                        ]
                    );
                });

                $migrated++;
            } catch (\Throwable $e) {
                $exceptions[] = "  - event_user id={$row->id}: {$e->getMessage()}";
                $errors++;
            }
        }

        if ($dryRun) {
            $this->info("[dry-run] {$migrated} row(s) would be migrated, {$skipped} already migrated, {$errors} exception(s).");
        } else {
            $this->info("Migrated: {$migrated}, Skipped (already exists): {$skipped}, Errors: {$errors}.");
        }

        if (! empty($exceptions)) {
            $this->warn('Migration exceptions (rows NOT migrated):');
            foreach ($exceptions as $msg) {
                $this->warn($msg);
            }
        }

        return $errors > 0 && $migrated === 0 ? self::FAILURE : self::SUCCESS;
    }

    /**
     * Map a role_label string from event_user to EventOwnerRole.
     * Returns null if the value is not a valid couple role.
     */
    private function resolveOwnerRole(string $rawRole): ?EventOwnerRole
    {
        $eventUserRole = EventUserRole::tryFrom($rawRole);

        if ($eventUserRole === null) {
            return null;
        }

        return EventOwnerRole::fromEventUserRole($eventUserRole);
    }
}
