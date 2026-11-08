<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('event_setup_checklist_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('event_id')->constrained('events')->cascadeOnDelete();
            $table->string('label');
            $table->text('description')->nullable();
            $table->boolean('required')->default(false);
            $table->unsignedSmallInteger('sequence')->default(0);
            $table->boolean('completed')->default(false);
            $table->timestamp('completed_at')->nullable();
            $table->string('source_key')->nullable();
            $table->timestamps();

            $table->index(['event_id', 'sequence']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('event_setup_checklist_items');
    }
};
