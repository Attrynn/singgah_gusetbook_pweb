<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('guest_categories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->constrained()->cascadeOnDelete();
            $table->string('name', 80);
            $table->string('slug', 80);
            $table->string('color', 20)->default('#8EB4D3');
            $table->string('description')->nullable();
            $table->timestamps();

            $table->unique(['event_id', 'slug']);
        });

        Schema::create('guest_groups', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->constrained()->cascadeOnDelete();
            $table->string('name', 100);
            $table->string('description')->nullable();
            $table->timestamps();

            $table->unique(['event_id', 'name']);
        });

        Schema::create('guests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->constrained()->cascadeOnDelete();
            $table->foreignId('guest_category_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('guest_group_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name');
            $table->string('email')->nullable();
            $table->string('phone', 30)->nullable();
            $table->text('notes')->nullable();
            $table->enum('status', ['active', 'inactive', 'blocked'])->default('active');
            $table->unsignedTinyInteger('max_attendees')->default(2);
            $table->enum('rsvp_status', ['pending', 'confirmed', 'declined', 'maybe'])->default('pending');
            $table->timestamp('checked_in_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['event_id', 'rsvp_status']);
            $table->index(['event_id', 'status']);
            $table->index(['event_id', 'name']);
            $table->index('email');
            $table->index('phone');
        });

        Schema::create('guest_tokens', function (Blueprint $table) {
            $table->id();
            $table->foreignId('guest_id')->constrained()->cascadeOnDelete();
            $table->foreignId('event_id')->constrained()->cascadeOnDelete();
            $table->enum('type', ['invitation', 'qr'])->default('invitation');
            $table->char('token_hash', 64)->unique();
            $table->text('token_ciphertext');
            $table->string('token_prefix', 16);
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('revoked_at')->nullable();
            $table->timestamp('last_used_at')->nullable();
            $table->timestamps();

            $table->index(['guest_id', 'type']);
            $table->index(['event_id', 'type']);
        });

        Schema::create('invitations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('guest_id')->unique()->constrained()->cascadeOnDelete();
            $table->foreignId('event_id')->constrained()->cascadeOnDelete();
            $table->foreignId('guest_token_id')->nullable()->constrained('guest_tokens')->nullOnDelete();
            $table->enum('status', ['not_sent', 'link_opened', 'shared_manually', 'sent'])->default('not_sent');
            $table->string('template', 50)->default('classic');
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('opened_at')->nullable();
            $table->unsignedInteger('open_count')->default(0);
            $table->timestamp('last_shared_at')->nullable();
            $table->timestamps();

            $table->index(['event_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invitations');
        Schema::dropIfExists('guest_tokens');
        Schema::dropIfExists('guests');
        Schema::dropIfExists('guest_groups');
        Schema::dropIfExists('guest_categories');
    }
};
