<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Clear dangling cover references before adding referential integrity.
        DB::table('media_albums')
            ->whereNotNull('cover_media_asset_id')
            ->whereNotIn('cover_media_asset_id', fn ($query) => $query->select('id')->from('media_assets'))
            ->update(['cover_media_asset_id' => null]);

        Schema::table('media_albums', function (Blueprint $table): void {
            $table->foreign('cover_media_asset_id')
                ->references('id')
                ->on('media_assets')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('media_albums', function (Blueprint $table): void {
            $table->dropForeign(['cover_media_asset_id']);
        });
    }
};
