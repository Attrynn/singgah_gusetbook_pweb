<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Milestone 9 — guestbook message fields for memory contributions.
 *
 * Links a message to the identified contributor, records an idempotency key,
 * and optionally attaches a photo asset. Both links are tenant-scoped.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('guestbook_messages', function (Blueprint $table): void {
            $table->foreignId('guest_party_membership_id')->nullable()->after('guest_id')
                ->constrained('guest_party_memberships')->nullOnDelete();
            $table->uuid('client_submission_id')->nullable()->after('emoji');
            $table->foreignId('photo_asset_id')->nullable()->after('guest_party_membership_id')
                ->constrained('media_assets')->nullOnDelete();

            $table->unique(['event_id', 'client_submission_id'], 'uq_guestbook_event_submission');

            $table->index(['event_id', 'guest_party_membership_id']);
        });
    }

    public function down(): void
    {
        Schema::table('guestbook_messages', function (Blueprint $table): void {
            $table->dropForeign(['guest_party_membership_id']);
            $table->dropForeign(['photo_asset_id']);
            $table->dropIndex(['event_id', 'guest_party_membership_id']);
            $table->dropUnique('uq_guestbook_event_submission');
            $table->dropColumn([
                'guest_party_membership_id',
                'client_submission_id',
                'photo_asset_id',
            ]);
        });
    }
};
