<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Milestone 5 enrichment of existing check-in history with individual
        // attribution so a successful scan can record which member and which
        // guest pass drove the admission. Legacy party-level records keep their
        // event_id + guest_id key; new rows additionally record membership /
        // pass so the scan can be tied back to an identified individual.
        Schema::table('checkins', function (Blueprint $table) {
            $table->foreignId('guest_party_membership_id')->nullable()
                ->constrained('guest_party_memberships')->nullOnDelete();
            $table->foreignId('guest_pass_id')->nullable()
                ->constrained('guest_passes')->nullOnDelete();

            $table->index(['event_id', 'guest_party_membership_id']);
        });
    }

    public function down(): void
    {
        Schema::table('checkins', function (Blueprint $table) {
            $table->dropIndex(['event_id', 'guest_party_membership_id']);
            $table->dropColumn([
                'guest_party_membership_id',
                'guest_pass_id',
            ]);
        });
    }
};
