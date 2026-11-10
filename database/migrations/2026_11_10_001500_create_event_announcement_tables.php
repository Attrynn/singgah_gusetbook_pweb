<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('event_announcements', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('event_id')->constrained()->cascadeOnDelete();
            $table->string('title', 180);
            $table->text('body');
            $table->string('priority', 20)->default('normal');
            $table->string('audience', 30)->default('all_staff');
            $table->json('audience_ref')->nullable();
            $table->string('status', 20)->default('draft');
            $table->boolean('requires_acknowledgement')->default(false);
            $table->timestamp('published_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('withdrawn_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('published_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['event_id', 'status'], 'event_announcement_event_status_index');
            $table->index(['event_id', 'published_at'], 'event_announcement_event_published_index');
        });

        Schema::create('event_announcement_recipients', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('event_announcement_id')
                ->constrained('event_announcements')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamp('read_at')->nullable();
            $table->timestamp('acknowledged_at')->nullable();
            $table->timestamps();

            $table->unique(
                ['event_announcement_id', 'user_id'],
                'announcement_recipient_unique',
            );
            $table->index(['user_id', 'read_at'], 'announcement_recipient_user_read_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('event_announcement_recipients');
        Schema::dropIfExists('event_announcements');
    }
};
