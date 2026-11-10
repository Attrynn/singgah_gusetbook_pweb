<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('event_stations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('event_id')->constrained()->cascadeOnDelete();
            $table->string('name', 150);
            $table->string('slug', 150);
            $table->string('category', 40)->default('general');
            $table->text('description')->nullable();
            $table->string('location', 255)->nullable();
            $table->text('instructions')->nullable();
            $table->string('status', 30)->default('draft');
            $table->foreignId('supervisor_membership_id')->nullable()
                ->constrained('event_staff_memberships')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['event_id', 'slug']);
            $table->index(['event_id', 'status'], 'event_station_event_status_index');
        });

        Schema::create('event_station_assignments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('event_id')->constrained()->cascadeOnDelete();
            $table->foreignId('station_id')->constrained('event_stations')->cascadeOnDelete();
            $table->foreignId('staff_membership_id')
                ->constrained('event_staff_memberships')->cascadeOnDelete();
            $table->boolean('is_supervisor')->default(false);
            $table->foreignId('assigned_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('assigned_at')->nullable();
            $table->timestamp('removed_at')->nullable();
            $table->timestamps();

            $table->unique(['station_id', 'staff_membership_id'], 'station_assignment_unique');
            $table->index(['event_id', 'staff_membership_id'], 'station_assignment_event_staff_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('event_station_assignments');
        Schema::dropIfExists('event_stations');
    }
};
