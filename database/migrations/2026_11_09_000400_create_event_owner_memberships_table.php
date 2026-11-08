<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('event_owner_memberships', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('event_id')->constrained('events')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();

            // Couple relationship role. Enum values: groom, bride, partner.
            $table->string('role_label');

            // active = can access event as owner; revoked = access removed.
            $table->string('status')->default('active')->index();

            $table->foreignId('invited_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->timestamp('accepted_at')->nullable();
            $table->timestamp('revoked_at')->nullable();

            $table->timestamps();

            // One couple-ownership row per (event, user).
            // A user can only hold one couple role per event (groom+bride on
            // same event would be two different users).
            $table->unique(['event_id', 'user_id']);
            $table->index('user_id');
            $table->index('event_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('event_owner_memberships');
    }
};
