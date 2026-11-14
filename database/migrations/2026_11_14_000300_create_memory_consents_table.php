<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Milestone 9 — consent record for every guest contribution.
 *
 * A contribution is identified by its explicit type plus id (media asset or
 * guestbook message). The optional party membership is tenant-scoped through a
 * composite foreign key so a member can never be attached across events.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('memory_consents', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('event_id')->constrained()->cascadeOnDelete();
            $table->string('contribution_type', 20);
            $table->unsignedBigInteger('contribution_id');
            $table->foreignId('guest_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('guest_party_membership_id')->nullable();
            $table->string('contributor_name', 120)->nullable();
            $table->boolean('allow_event_display')->default(true);
            $table->boolean('allow_public_display')->default(false);
            $table->string('terms_version', 20);
            $table->timestamp('accepted_at');
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent', 255)->nullable();
            $table->timestamps();

            $table->unique(['contribution_type', 'contribution_id'], 'memory_consents_contribution_unique');
            $table->index(['event_id', 'accepted_at']);

            $table->foreign(['event_id', 'guest_party_membership_id'], 'fk_memory_consents_event_member')
                ->references(['event_id', 'id'])->on('guest_party_memberships')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('memory_consents');
    }
};
