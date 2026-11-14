<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Milestone 8 — organization-scoped venue catalog.
 *
 * `venues` and `venue_spaces` are reusable across events inside one
 * organization. `event_venues` stays the event-level anchor; `venue_id` only
 * links it to the catalog and never replaces the legacy name/address columns.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('venues', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->string('name', 120);
            $table->text('description')->nullable();
            $table->text('address')->nullable();
            $table->string('google_maps_url', 2048)->nullable();
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->string('timezone', 64)->nullable();
            $table->string('status', 20)->default('active');
            $table->timestamp('archived_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['organization_id', 'name'], 'venues_org_name_unique');
            $table->unique(['organization_id', 'id'], 'venues_org_id_unique');
            $table->index(['organization_id', 'status'], 'venues_org_status_index');
        });

        Schema::create('venue_spaces', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('venue_id');
            $table->string('name', 120);
            $table->string('type', 30)->default('other');
            $table->string('floor_label', 60)->nullable();
            $table->text('description')->nullable();
            $table->unsignedInteger('capacity')->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->string('status', 20)->default('active');
            $table->timestamp('archived_at')->nullable();
            $table->timestamps();

            $table->unique(['venue_id', 'name'], 'venue_spaces_venue_name_unique');
            $table->unique(['organization_id', 'id'], 'venue_spaces_org_id_unique');
            $table->index(['venue_id', 'status'], 'venue_spaces_venue_status_index');

            $table->foreign(['organization_id', 'venue_id'], 'venue_spaces_org_venue_fk')
                ->references(['organization_id', 'id'])
                ->on('venues')
                ->cascadeOnDelete();
        });

        Schema::table('event_venues', function (Blueprint $table): void {
            $table->unsignedBigInteger('venue_id')->nullable();
            $table->index(['venue_id'], 'event_venues_venue_index');
            $table->foreign('venue_id', 'event_venues_venue_fk')
                ->references('id')
                ->on('venues')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('event_venues', function (Blueprint $table): void {
            $table->dropForeign('event_venues_venue_fk');
            $table->dropIndex('event_venues_venue_index');
            $table->dropColumn('venue_id');
        });

        Schema::dropIfExists('venue_spaces');
        Schema::dropIfExists('venues');
    }
};
