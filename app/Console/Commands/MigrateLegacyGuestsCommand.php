<?php

namespace App\Console\Commands;

use App\Models\Event;
use App\Services\GuestLegacyMigrationService;
use Illuminate\Console\Command;

class MigrateLegacyGuestsCommand extends Command
{
    protected $signature = 'guests:migrate-m4 {--event= : Specific Event ID to migrate} {--dry-run : Simulate without writing changes}';

    protected $description = 'Migrate legacy Guest records to the Milestone 4 Party + Individual Guest and Pass foundation (idempotent, non-destructive, dry-run supported).';

    public function handle(GuestLegacyMigrationService $service): int
    {
        $eventId = $this->option('event');
        $dryRun = (bool) $this->option('dry-run');

        $event = $eventId ? Event::find($eventId) : null;
        if ($eventId && ! $event) {
            $this->error("Event ID {$eventId} tidak ditemukan.");

            return self::FAILURE;
        }

        $mode = $dryRun ? 'DRY-RUN (Simulasi)' : 'LIVE EXECUTION';
        $this->info("Memulai migrasi legacy guest Milestone 4 [{$mode}]...");

        $result = $service->migrate($event, $dryRun);

        $this->table(
            ['Metrik', 'Nilai'],
            [
                ['Mode', $result['dry_run'] ? 'Simulasi (Dry-Run)' : 'Aktif (Written)'],
                ['Total Legacy Guests', $result['total_legacy_guests']],
                ['Total Parties Mapped', $result['total_parties_mapped']],
                ['Total Individu Dibuat', $result['total_members_created']],
                ['Total Pass Diterbitkan', $result['total_passes_issued']],
                ['Sudah Dimigrasi (Dilewati)', $result['already_migrated_skipped']],
                ['Kapasitas Unidentified Tersimpan', $result['unidentified_capacity_retained']],
                ['Total RSVP Preserved', $result['total_rsvps_preserved']],
                ['Total Check-in Preserved', $result['total_checkins_preserved']],
                ['Total Token Preserved', $result['total_tokens_preserved']],
                ['Jumlah Error', count($result['errors'])],
            ]
        );

        if (! empty($result['errors'])) {
            $this->warn('Daftar error:');
            foreach ($result['errors'] as $error) {
                $this->error("- {$error}");
            }

            return self::FAILURE;
        }

        $this->info('Migrasi legacy guest berhasil diselesaikan.');

        return self::SUCCESS;
    }
}
