<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('event_tasks', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('event_id')->constrained()->cascadeOnDelete();
            $table->string('title', 180);
            $table->text('description')->nullable();
            $table->string('category', 40)->default('general');
            $table->string('priority', 20)->default('medium');
            $table->string('status', 20)->default('open');
            $table->foreignId('assigned_membership_id')->nullable()
                ->constrained('event_staff_memberships')->nullOnDelete();
            $table->foreignId('station_id')->nullable()
                ->constrained('event_stations')->nullOnDelete();
            $table->foreignId('run_of_show_item_id')->nullable()
                ->constrained('event_run_of_show_items')->nullOnDelete();
            $table->timestamp('due_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->string('blocked_reason', 500)->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['event_id', 'status'], 'event_task_event_status_index');
            $table->index(['event_id', 'priority'], 'event_task_event_priority_index');
            $table->index(['event_id', 'due_at'], 'event_task_event_due_index');
        });

        Schema::create('event_task_checklist_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('event_task_id')->constrained('event_tasks')->cascadeOnDelete();
            $table->string('label', 255);
            $table->unsignedSmallInteger('sequence')->default(0);
            $table->boolean('completed')->default(false);
            $table->timestamp('completed_at')->nullable();
            $table->foreignId('completed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['event_task_id', 'sequence'], 'task_checklist_sequence_index');
        });

        Schema::create('event_task_dependencies', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('event_task_id')->constrained('event_tasks')->cascadeOnDelete();
            $table->foreignId('depends_on_task_id')->constrained('event_tasks')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['event_task_id', 'depends_on_task_id'], 'task_dependency_unique');
        });

        Schema::create('event_task_comments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('event_task_id')->constrained('event_tasks')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('body');
            $table->timestamps();

            $table->index(['event_task_id', 'created_at'], 'task_comment_created_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('event_task_comments');
        Schema::dropIfExists('event_task_dependencies');
        Schema::dropIfExists('event_task_checklist_items');
        Schema::dropIfExists('event_tasks');
    }
};
