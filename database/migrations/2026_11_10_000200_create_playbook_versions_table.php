<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('playbook_versions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('playbook_id')->constrained('playbooks')->cascadeOnDelete();
            $table->unsignedInteger('version')->default(1);
            $table->json('config');
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['playbook_id', 'version']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('playbook_versions');
    }
};
