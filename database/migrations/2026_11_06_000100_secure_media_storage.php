<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('media_assets', function (Blueprint $table) {
            $table->string('disk', 40)->default('public')->after('event_id');
            $table->unsignedSmallInteger('sort_order')->default(0)->after('caption');
        });

        // Consolidate the album relation onto a single source of truth:
        // media_assets.media_album_id. Backfill from the legacy pivot first.
        if (Schema::hasTable('media_album_items')) {
            DB::table('media_album_items')
                ->whereNotNull('media_asset_id')
                ->orderBy('sort_order')
                ->get()
                ->each(function ($item): void {
                    DB::table('media_assets')
                        ->where('id', $item->media_asset_id)
                        ->whereNull('media_album_id')
                        ->update([
                            'media_album_id' => $item->media_album_id,
                            'sort_order' => $item->sort_order,
                        ]);
                });

            Schema::drop('media_album_items');
        }

        Schema::table('media_assets', function (Blueprint $table) {
            $table->index(['media_album_id', 'sort_order']);
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('media_album_items')) {
            Schema::create('media_album_items', function (Blueprint $table) {
                $table->id();
                $table->foreignId('media_album_id')->constrained()->cascadeOnDelete();
                $table->foreignId('media_asset_id')->constrained()->cascadeOnDelete();
                $table->unsignedSmallInteger('sort_order')->default(0);
                $table->timestamps();

                $table->unique(['media_album_id', 'media_asset_id']);
            });
        }

        Schema::table('media_assets', function (Blueprint $table) {
            $table->dropIndex(['media_album_id', 'sort_order']);
            $table->dropColumn(['disk', 'sort_order']);
        });
    }
};
