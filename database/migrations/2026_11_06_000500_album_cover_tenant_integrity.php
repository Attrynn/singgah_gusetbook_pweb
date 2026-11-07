<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * VS-DB-001: an album cover must reference a media asset from the same event.
 *
 * Preflight reports (and clears) covers whose asset belongs to another event
 * instead of silently reassigning ownership. The composite foreign key then
 * makes cross-event covers impossible at the database level.
 */
return new class extends Migration
{
    public function up(): void
    {
        $conflicts = DB::table('media_albums as a')
            ->join('media_assets as m', 'm.id', '=', 'a.cover_media_asset_id')
            ->whereColumn('m.event_id', '!=', 'a.event_id')
            ->count();

        if ($conflicts > 0) {
            DB::table('media_albums as a')
                ->join('media_assets as m', 'm.id', '=', 'a.cover_media_asset_id')
                ->whereColumn('m.event_id', '!=', 'a.event_id')
                ->update(['a.cover_media_asset_id' => null]);

            Log::warning('Cleared cross-event album covers before enforcing tenant integrity', [
                'cleared' => $conflicts,
            ]);
        }

        // The original single-column foreign key is a strict subset of the
        // composite key. Drop it where the driver supports it; SQLite cannot
        // drop foreign keys, so there the two coexist safely.
        try {
            Schema::table('media_albums', fn (Blueprint $table) => $table->dropForeign(['cover_media_asset_id']));
        } catch (Throwable) {
            // Driver does not support dropping foreign keys (SQLite). Keep it.
        }

        Schema::table('media_assets', function (Blueprint $table): void {
            $table->unique(['event_id', 'id'], 'uq_media_assets_event_id');
        });

        Schema::table('media_albums', function (Blueprint $table): void {
            $table->foreign(['event_id', 'cover_media_asset_id'], 'fk_media_albums_event_cover')
                ->references(['event_id', 'id'])
                ->on('media_assets');
        });
    }

    public function down(): void
    {
        Schema::table('media_albums', fn (Blueprint $table) => $table->dropForeign('fk_media_albums_event_cover'));

        Schema::table('media_assets', fn (Blueprint $table) => $table->dropUnique('uq_media_assets_event_id'));

        try {
            Schema::table('media_albums', function (Blueprint $table): void {
                $table->foreign('cover_media_asset_id')
                    ->references('id')
                    ->on('media_assets')
                    ->nullOnDelete();
            });
        } catch (Throwable) {
            // Constraint may already exist on drivers that could not drop it.
        }
    }
};
