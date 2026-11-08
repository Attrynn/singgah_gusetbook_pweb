<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('event_phases', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('event_id')->constrained('events')->cascadeOnDelete();
            $table->string('name');
            $table->unsignedSmallInteger('sequence')->default(0);
            $table->timestamps();

            $table->index(['event_id', 'sequence']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('event_phases');
    }
};
