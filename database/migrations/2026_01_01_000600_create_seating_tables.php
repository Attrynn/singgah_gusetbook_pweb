<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('seating_tables', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->constrained()->cascadeOnDelete();
            $table->string('name', 60);
            $table->unsignedSmallInteger('capacity')->default(8);
            $table->string('zone', 60)->nullable();
            $table->string('notes')->nullable();
            $table->timestamps();

            $table->unique(['event_id', 'name']);
        });

        Schema::create('table_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->constrained()->cascadeOnDelete();
            $table->foreignId('seating_table_id')->constrained()->cascadeOnDelete();
            $table->foreignId('guest_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('seat_number')->nullable();
            $table->timestamps();

            $table->unique(['event_id', 'guest_id']);
            $table->unique(['seating_table_id', 'seat_number']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('table_assignments');
        Schema::dropIfExists('seating_tables');
    }
};
