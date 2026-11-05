<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('guestbook_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->constrained()->cascadeOnDelete();
            $table->foreignId('guest_id')->nullable()->constrained()->nullOnDelete();
            $table->string('sender_name');
            $table->text('message');
            $table->string('emoji', 16)->nullable();
            $table->enum('visibility', ['public', 'private'])->default('public');
            $table->enum('status', ['pending', 'approved', 'rejected', 'hidden'])->default('pending');
            $table->boolean('is_pinned')->default(false);
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['event_id', 'status', 'visibility']);
            $table->index(['event_id', 'is_pinned']);
        });

        Schema::create('guestbook_favorites', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('guestbook_message_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['user_id', 'guestbook_message_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('guestbook_favorites');
        Schema::dropIfExists('guestbook_messages');
    }
};
