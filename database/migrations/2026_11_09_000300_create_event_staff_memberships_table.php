<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('event_staff_memberships', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('event_id')->constrained('events')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();

            // Event-scoped operational role.
            // Valid values: event_manager, guest_manager, checkin_supervisor,
            // checkin_staff, seating_usher, media_curator, gift_steward.
            $table->string('role');

            // active = can exercise role; revoked = permanently removed.
            $table->string('status')->default('active')->index();

            $table->foreignId('assigned_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->timestamp('revoked_at')->nullable();

            $table->timestamps();

            // One row per (event, user, role) — a user may hold multiple roles
            // on the same event but not the same role twice.
            $table->unique(['event_id', 'user_id', 'role']);
            $table->index('user_id');
            $table->index('event_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('event_staff_memberships');
    }
};
