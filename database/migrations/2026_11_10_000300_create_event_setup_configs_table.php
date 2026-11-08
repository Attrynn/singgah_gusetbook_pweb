<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('event_setup_configs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('event_id')->unique()->constrained('events')->cascadeOnDelete();
            $table->foreignId('playbook_id')->nullable()->constrained('playbooks')->nullOnDelete();
            $table->foreignId('playbook_version_id')->nullable()->constrained('playbook_versions')->nullOnDelete();
            $table->foreignId('source_event_id')->nullable()->constrained('events')->nullOnDelete();
            $table->json('config_snapshot')->nullable();
            $table->string('setup_status')->default('not_started')->index();
            $table->foreignId('primary_pic_staff_membership_id')
                ->nullable()
                ->constrained('event_staff_memberships')
                ->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('event_setup_configs');
    }
};
