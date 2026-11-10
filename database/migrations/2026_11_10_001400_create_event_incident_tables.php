<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('event_incidents', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('event_id')->constrained()->cascadeOnDelete();
            $table->string('title', 180);
            $table->text('description')->nullable();
            $table->string('category', 40)->default('other');
            $table->string('severity', 20)->default('medium');
            $table->string('status', 20)->default('open');
            $table->foreignId('station_id')->nullable()
                ->constrained('event_stations')->nullOnDelete();
            $table->foreignId('reported_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reported_at')->nullable();
            $table->timestamp('acknowledged_at')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->foreignId('resolved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('resolution_notes')->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->foreignId('closed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('escalated_at')->nullable();
            $table->foreignId('escalated_to')->nullable()->constrained('users')->nullOnDelete();
            $table->string('escalation_reason', 500)->nullable();
            $table->timestamps();

            $table->index(['event_id', 'status'], 'event_incident_event_status_index');
            $table->index(['event_id', 'severity'], 'event_incident_event_severity_index');
        });

        Schema::create('event_incident_updates', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('event_incident_id')->constrained('event_incidents')->cascadeOnDelete();
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('type', 40);
            $table->string('from_status', 20)->nullable();
            $table->string('to_status', 20)->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['event_incident_id', 'created_at'], 'incident_update_created_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('event_incident_updates');
        Schema::dropIfExists('event_incidents');
    }
};
