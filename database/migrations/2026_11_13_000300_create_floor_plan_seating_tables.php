<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Milestone 8 — version-aware seating assignments and spatial references.
 *
 * Assignments point at M5 identities (`guest_party_memberships` for a named
 * individual, `guests` + headcount for unidentified quota slots). Layout objects
 * are addressed by their stable document UUID plus the owning version, because
 * objects live inside the version JSON document.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('guest_party_memberships', function (Blueprint $table): void {
            $table->unique(['event_id', 'id'], 'guest_party_memberships_event_id_unique');
        });

        Schema::create('seating_assignments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('event_id');
            $table->unsignedBigInteger('floor_plan_id');
            $table->unsignedBigInteger('floor_plan_version_id');
            $table->string('table_object_id', 64);
            $table->string('seat_object_id', 64)->nullable();
            $table->unsignedBigInteger('guest_id');
            $table->unsignedBigInteger('guest_party_membership_id')->nullable();
            $table->unsignedSmallInteger('headcount')->default(1);
            $table->string('status', 20)->default('active');
            $table->foreignId('assigned_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('assigned_at')->nullable();
            $table->timestamp('released_at')->nullable();
            $table->string('release_reason', 255)->nullable();
            $table->timestamps();

            $table->index(['floor_plan_version_id', 'table_object_id', 'status'], 'seating_assignments_table_index');
            $table->index(['floor_plan_version_id', 'seat_object_id', 'status'], 'seating_assignments_seat_index');
            $table->index(['event_id', 'guest_party_membership_id', 'status'], 'seating_assignments_member_index');
            $table->index(['event_id', 'guest_id', 'status'], 'seating_assignments_guest_index');

            $table->foreign(['event_id', 'floor_plan_version_id'], 'seating_assignments_version_fk')
                ->references(['event_id', 'id'])->on('floor_plan_versions')->cascadeOnDelete();
            $table->foreign(['event_id', 'guest_id'], 'seating_assignments_guest_fk')
                ->references(['event_id', 'id'])->on('guests')->cascadeOnDelete();
            $table->foreign(['event_id', 'guest_party_membership_id'], 'seating_assignments_member_fk')
                ->references(['event_id', 'id'])->on('guest_party_memberships')->cascadeOnDelete();
        });

        Schema::create('spatial_references', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('event_id');
            $table->unsignedBigInteger('floor_plan_id');
            $table->unsignedBigInteger('floor_plan_version_id');
            $table->string('object_id', 64);
            $table->string('object_type', 30);
            $table->string('referenceable_type', 60);
            $table->unsignedBigInteger('referenceable_id');
            $table->string('label', 160)->nullable();
            $table->string('status', 20)->default('active');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['referenceable_type', 'referenceable_id'], 'spatial_references_target_unique');
            $table->index(['floor_plan_version_id', 'object_id'], 'spatial_references_object_index');
            $table->index(['event_id', 'status'], 'spatial_references_event_status_index');

            $table->foreign(['event_id', 'floor_plan_version_id'], 'spatial_references_version_fk')
                ->references(['event_id', 'id'])->on('floor_plan_versions')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('spatial_references');
        Schema::dropIfExists('seating_assignments');

        Schema::table('guest_party_memberships', function (Blueprint $table): void {
            $table->dropUnique('guest_party_memberships_event_id_unique');
        });
    }
};
