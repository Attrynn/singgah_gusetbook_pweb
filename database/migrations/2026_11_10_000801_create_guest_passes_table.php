<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('guest_passes', function (Blueprint $table) {
            $table->id();

            // The pass belongs to an identified individual within a party.
            // guest_passes -> guest_party_memberships -> guests (party) -> events.
            $table->foreignId('guest_party_membership_id')
                ->constrained('guest_party_memberships')
                ->cascadeOnDelete();

            // Event scope for tenant isolation and aggregate queries.
            $table->foreignId('event_id')->constrained('events')->cascadeOnDelete();

            // Invitation or QR admission credential.
            $table->string('type', 20)->default('invitation');

            // Hashed credential. The raw token is never stored.
            $table->char('token_hash', 64)->unique();

            // Encrypted raw token, only decrypted for delivery to the guest.
            $table->text('token_ciphertext');

            // First characters of the raw token for display / disambiguation.
            $table->string('token_prefix', 16);

            $table->timestamp('expires_at')->nullable();
            $table->timestamp('revoked_at')->nullable();
            $table->timestamp('last_used_at')->nullable();
            $table->string('status', 20)->default('active');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamps();

            // Credential verification and tenant-scoped listing.
            $table->index(['guest_party_membership_id', 'type', 'status']);
            $table->index(['event_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('guest_passes');
    }
};
