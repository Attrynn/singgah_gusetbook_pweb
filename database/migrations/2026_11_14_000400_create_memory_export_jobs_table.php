<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Milestone 9 — background export requests for an event's memory archive.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('memory_export_jobs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('event_id')->constrained()->cascadeOnDelete();
            $table->foreignId('requested_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status', 20)->default('queued');
            $table->json('scope')->nullable();
            $table->string('file_disk', 40)->nullable();
            $table->string('file_path', 2048)->nullable();
            $table->unsignedBigInteger('file_size')->nullable();
            $table->unsignedInteger('item_count')->default(0);
            $table->text('error')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();

            $table->index(['event_id', 'status']);
            $table->index(['event_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('memory_export_jobs');
    }
};
