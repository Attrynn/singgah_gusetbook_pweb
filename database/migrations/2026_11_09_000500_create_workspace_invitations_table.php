<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('workspace_invitations', function (Blueprint $table): void {
            $table->id();

            // The organization this invitation belongs to.
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();

            // Who sent the invitation.
            $table->foreignId('invited_by')->constrained('users')->cascadeOnDelete();

            // Target recipient email. Not necessarily a registered user yet.
            $table->string('email');

            // 'staff' = org member + event role assignments.
            // 'couple' = EventOwnerMembership (groom/bride/partner).
            $table->string('type');

            // Workspace-level role to assign on acceptance.
            // NULL for couple invitations.
            $table->string('organization_role')->nullable();

            // JSON array of {event_id, role} objects for staff,
            // or {event_id, role_label} for couple.
            $table->json('event_assignments')->nullable();

            // SHA-256 hash of the raw token. Raw token is only shown once (in email).
            $table->string('token_hash')->unique();

            // Status: pending | accepted | expired | revoked.
            $table->string('status')->default('pending')->index();

            $table->timestamp('expires_at');
            $table->timestamp('accepted_at')->nullable();
            $table->timestamp('revoked_at')->nullable();

            // The user who accepted (resolved after acceptance).
            $table->foreignId('accepted_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->timestamps();

            $table->index(['email', 'status']);
            $table->index(['organization_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('workspace_invitations');
    }
};
