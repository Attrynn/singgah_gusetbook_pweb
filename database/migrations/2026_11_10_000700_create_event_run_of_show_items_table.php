<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('event_run_of_show_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('event_id')->constrained('events')->cascadeOnDelete();
            $table->foreignId('event_session_id')->nullable()->constrained('event_sessions')->nullOnDelete();
            $table->foreignId('phase_id')->nullable()->constrained('event_phases')->nullOnDelete();
            $table->string('title');
            $table->text('internal_description')->nullable();
            $table->dateTime('planned_start')->nullable();
            $table->dateTime('planned_end')->nullable();
            $table->dateTime('actual_start')->nullable();
            $table->dateTime('actual_end')->nullable();
            $table->string('status')->default('pending')->index();
            $table->unsignedSmallInteger('sequence')->default(0);
            $table->foreignId('pic_staff_membership_id')
                ->nullable()
                ->constrained('event_staff_memberships')
                ->nullOnDelete();
            $table->text('internal_notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['event_id', 'sequence']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('event_run_of_show_items');
    }
};
