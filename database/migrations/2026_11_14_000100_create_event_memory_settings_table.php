<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Milestone 9 — per-event memory configuration.
 *
 * One settings row per event controls whether guests may contribute, whether
 * contributions are auto-approved, and the lifecycle of the public share link.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('event_memory_settings', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('event_id')->unique()->constrained()->cascadeOnDelete();
            $table->boolean('submissions_open')->default(true);
            $table->boolean('post_event_submissions')->default(false);
            $table->timestamp('submission_opens_at')->nullable();
            $table->timestamp('submission_closes_at')->nullable();
            $table->boolean('auto_approve')->default(false);
            $table->boolean('public_sharing')->default(false);
            $table->string('share_token_hash', 64)->nullable()->unique();
            $table->string('share_token_prefix', 8)->nullable();
            $table->timestamp('share_token_expires_at')->nullable();
            $table->timestamp('share_token_revoked_at')->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->foreignId('closed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('archived_at')->nullable();
            $table->unsignedSmallInteger('retention_days')->nullable();
            $table->timestamps();

            $table->index(['archived_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('event_memory_settings');
    }
};
