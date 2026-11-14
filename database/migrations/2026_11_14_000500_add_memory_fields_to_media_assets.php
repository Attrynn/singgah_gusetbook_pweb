<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Milestone 9 — media asset fields for guest memory contributions.
 *
 * Adds the identified-contributor link, an idempotency key for retried guest
 * submissions, intrinsic image metadata, and moderation visibility.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('media_assets', function (Blueprint $table): void {
            $table->foreignId('guest_party_membership_id')->nullable()->after('guest_id')
                ->constrained('guest_party_memberships')->nullOnDelete();
            $table->uuid('client_submission_id')->nullable()->after('caption');
            $table->unsignedInteger('width')->nullable();
            $table->unsignedInteger('height')->nullable();
            $table->string('checksum', 64)->nullable();
            $table->timestamp('hidden_at')->nullable();

            // uq_media_assets_event_id already exists from 2026_11_06_000500 and is
            // the composite FK target for guestbook_messages.photo_asset_id.
            $table->unique(['event_id', 'client_submission_id'], 'uq_media_assets_event_submission');
        });
    }

    public function down(): void
    {
        Schema::table('media_assets', function (Blueprint $table): void {
            $table->dropForeign(['guest_party_membership_id']);
            $table->dropUnique('uq_media_assets_event_submission');
            $table->dropColumn([
                'guest_party_membership_id',
                'client_submission_id',
                'width',
                'height',
                'checksum',
                'hidden_at',
            ]);
        });
    }
};
