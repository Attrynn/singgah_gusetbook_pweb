<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Milestone 9 — curated memory collections and their ordered items.
 *
 * Items reference either a media asset or a guestbook message through an
 * explicit `item_type` discriminator rather than a polymorphic relation, so
 * tenant integrity stays enforceable at the database level.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('memory_collections', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('event_id')->constrained()->cascadeOnDelete();
            $table->string('name', 120);
            $table->text('description')->nullable();
            $table->foreignId('cover_media_asset_id')->nullable()->constrained('media_assets')->nullOnDelete();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('is_visible_to_guests')->default(false);
            $table->timestamp('archived_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['event_id', 'name'], 'memory_collections_event_name_unique');
            $table->index(['event_id', 'archived_at']);
        });

        Schema::create('memory_collection_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('event_id')->constrained()->cascadeOnDelete();
            $table->foreignId('memory_collection_id')->constrained('memory_collections')->cascadeOnDelete();
            $table->string('item_type', 20);
            $table->unsignedBigInteger('item_id');
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->foreignId('added_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['memory_collection_id', 'item_type', 'item_id'], 'memory_collection_items_unique');
            $table->index(['event_id', 'item_type', 'item_id']);
            $table->index(['memory_collection_id', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('memory_collection_items');
        Schema::dropIfExists('memory_collections');
    }
};
