<?php

namespace App\Console\Commands;

use App\Models\Event;
use App\Models\Organization;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class BackfillLegacyWorkspace extends Command
{
    protected $signature = 'vowspace:backfill-legacy-workspace
                            {--dry-run : Report what would be backfilled without modifying data}';

    protected $description = 'Associate every existing event with the Legacy Workspace organization. Idempotent.';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');

        $legacySlug = config('vowspace.organization.legacy_workspace_slug', 'legacy-workspace');
        $legacyName = config('vowspace.organization.legacy_workspace_name', 'Legacy Workspace');

        $orphanCount = Event::whereNull('organization_id')->count();

        if ($dryRun) {
            $legacy = Organization::where('slug', $legacySlug)->first();
            $legacyInfo = $legacy
                ? "Legacy Workspace exists (id={$legacy->id})"
                : "Legacy Workspace does not exist yet — would create it (slug={$legacySlug})";

            $this->info("[dry-run] {$legacyInfo}");
            $this->info("[dry-run] {$orphanCount} event(s) would be assigned to Legacy Workspace.");

            Event::whereNull('organization_id')->each(function (Event $event) use ($legacyName): void {
                $this->line("  - would assign event #{$event->id} \"{$event->name}\" → {$legacyName}");
            });

            return self::SUCCESS;
        }

        if ($orphanCount === 0) {
            $this->info('No orphaned events found; backfill is already complete.');

            return self::SUCCESS;
        }

        DB::transaction(function () use ($legacySlug, $legacyName): void {
            $legacy = Organization::firstOrCreate(
                ['slug' => $legacySlug],
                ['name' => $legacyName, 'status' => 'active']
            );

            $updated = Event::whereNull('organization_id')
                ->update(['organization_id' => $legacy->id]);

            $this->info("Legacy Workspace id={$legacy->id}.");
            $this->info("Backfilled {$updated} event(s) into Legacy Workspace.");
        });

        return self::SUCCESS;
    }
}
