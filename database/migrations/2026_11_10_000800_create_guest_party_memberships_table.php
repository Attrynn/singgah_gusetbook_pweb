<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('guest_party_memberships', function (Blueprint $table) {
            $table->id();

            // Each membership is one identified individual inside an invitation
            // party. The party is the existing `guests` row that already carries
            // the quota (`max_attendees`) semantics. We never fabricate a row for
            // unidentified quota slots.
            $table->foreignId('guest_id')->constrained('guests')->cascadeOnDelete();
            $table->foreignId('event_id')->constrained('events')->cascadeOnDelete();

            $table->string('name');
            $table->string('email')->nullable();
            $table->string('phone', 30)->nullable();
            $table->string('status', 20)->default('active');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamps();
            $table->softDeletes();

            // An individual must be uniquely named within a single party.
            $table->unique(['guest_id', 'name']);

            // Tenant-scoped listing and search.
            $table->index(['event_id', 'status']);
            $table->index('name');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('guest_party_memberships');
    }
};
