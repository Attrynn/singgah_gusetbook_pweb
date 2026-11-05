<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('events', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('groom_name');
            $table->string('bride_name');
            $table->text('groom_profile')->nullable();
            $table->text('bride_profile')->nullable();
            $table->text('couple_description')->nullable();
            $table->string('cover_image')->nullable();
            $table->date('event_date');
            $table->time('start_time')->nullable();
            $table->time('end_time')->nullable();
            $table->string('venue_name');
            $table->text('venue_address');
            $table->string('google_maps_url', 2048)->nullable();
            $table->string('dress_code')->nullable();
            $table->text('description')->nullable();
            $table->enum('status', ['draft', 'published', 'live', 'completed', 'archived'])->default('draft')->index();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('rsvp_deadline')->nullable();
            $table->boolean('rsvp_enabled')->default(true);
            $table->boolean('guestbook_enabled')->default(true);
            $table->boolean('gallery_enabled')->default(true);
            $table->boolean('gift_enabled')->default(true);
            $table->boolean('live_wall_enabled')->default(false);
            $table->string('timezone', 64)->default('Asia/Jakarta');
            $table->timestamp('published_at')->nullable();
            $table->timestamp('archived_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['status', 'event_date']);
        });

        Schema::create('event_user', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->enum('role_label', ['groom', 'bride', 'partner'])->default('partner');
            $table->timestamps();

            $table->unique(['event_id', 'user_id']);
        });

        Schema::create('event_settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->constrained()->cascadeOnDelete();
            $table->string('key', 100);
            $table->text('value')->nullable();
            $table->timestamps();

            $table->unique(['event_id', 'key']);
        });

        Schema::create('event_venues', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->text('address');
            $table->string('google_maps_url', 2048)->nullable();
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->boolean('is_primary')->default(false);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('event_schedules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->text('description')->nullable();
            $table->dateTime('start_time');
            $table->dateTime('end_time')->nullable();
            $table->enum('status', ['planned', 'ongoing', 'done'])->default('planned');
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['event_id', 'start_time']);
        });

        Schema::create('gift_accounts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->constrained()->cascadeOnDelete();
            $table->string('bank_name');
            $table->string('account_number', 50);
            $table->string('account_owner');
            $table->string('qris_path')->nullable();
            $table->text('gift_address')->nullable();
            $table->text('note')->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('gift_accounts');
        Schema::dropIfExists('event_schedules');
        Schema::dropIfExists('event_venues');
        Schema::dropIfExists('event_settings');
        Schema::dropIfExists('event_user');
        Schema::dropIfExists('events');
    }
};
