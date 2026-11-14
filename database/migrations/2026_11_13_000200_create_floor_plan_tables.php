<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Milestone 8 — event-scoped floor plan documents and their immutable versions.
 *
 * The canvas scene lives in `floor_plan_versions.document` (validated JSON).
 * Derived counters are stored alongside so listing and version comparison never
 * need to parse the document.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('floor_plans', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('event_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('venue_id')->nullable();
            $table->unsignedBigInteger('venue_space_id')->nullable();
            $table->string('name', 120);
            $table->text('description')->nullable();
            $table->string('status', 20)->default('active');
            $table->timestamp('archived_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['event_id', 'name'], 'floor_plans_event_name_unique');
            $table->unique(['event_id', 'id'], 'floor_plans_event_id_unique');
            $table->index(['event_id', 'status'], 'floor_plans_event_status_index');
            $table->index(['organization_id'], 'floor_plans_org_index');

            $table->foreign('venue_id', 'floor_plans_venue_fk')
                ->references('id')->on('venues')->nullOnDelete();
            $table->foreign('venue_space_id', 'floor_plans_venue_space_fk')
                ->references('id')->on('venue_spaces')->nullOnDelete();
        });

        Schema::create('floor_plan_versions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('event_id');
            $table->unsignedBigInteger('floor_plan_id');
            $table->unsignedInteger('version_number');
            $table->string('status', 20)->default('draft');
            $table->unsignedSmallInteger('schema_version')->default(1);

            $table->decimal('canvas_width', 10, 2)->default(2000);
            $table->decimal('canvas_height', 10, 2)->default(1500);
            $table->string('unit', 10)->default('cm');
            $table->decimal('grid_size', 8, 2)->default(50);
            $table->boolean('grid_visible')->default(true);
            $table->boolean('snap_enabled')->default(true);
            $table->string('background_color', 20)->nullable();
            $table->string('background_image_path', 2048)->nullable();
            $table->string('background_image_disk', 40)->nullable();

            $table->json('document');
            $table->unsignedInteger('object_count')->default(0);
            $table->unsignedInteger('table_count')->default(0);
            $table->unsignedInteger('chair_count')->default(0);
            $table->unsignedInteger('zone_count')->default(0);
            $table->unsignedInteger('seat_capacity')->default(0);

            $table->unsignedInteger('lock_version')->default(0);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('published_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('published_at')->nullable();
            $table->foreignId('archived_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('archived_at')->nullable();
            $table->timestamps();

            $table->unique(['floor_plan_id', 'version_number'], 'floor_plan_versions_number_unique');
            $table->unique(['event_id', 'id'], 'floor_plan_versions_event_id_unique');
            $table->index(['floor_plan_id', 'status'], 'floor_plan_versions_plan_status_index');
            $table->index(['event_id', 'status'], 'floor_plan_versions_event_status_index');

            $table->foreign(['event_id', 'floor_plan_id'], 'floor_plan_versions_plan_fk')
                ->references(['event_id', 'id'])
                ->on('floor_plans')
                ->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('floor_plan_versions');
        Schema::dropIfExists('floor_plans');
    }
};
