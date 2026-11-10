<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('event_run_of_show_items', function (Blueprint $table): void {
            $table->foreignId('station_id')->nullable()->after('phase_id')
                ->constrained('event_stations')->nullOnDelete();
            $table->foreignId('depends_on_item_id')->nullable()->after('station_id')
                ->constrained('event_run_of_show_items')->nullOnDelete();
            $table->timestamp('status_changed_at')->nullable()->after('status');
            $table->foreignId('status_changed_by')->nullable()->after('status_changed_at')
                ->constrained('users')->nullOnDelete();

            $table->index(['event_id', 'station_id'], 'run_of_show_event_station_index');
        });

        Schema::create('event_run_of_show_assignees', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('event_id')->constrained()->cascadeOnDelete();
            $table->foreignId('run_of_show_item_id')
                ->constrained('event_run_of_show_items')->cascadeOnDelete();
            $table->foreignId('staff_membership_id')
                ->constrained('event_staff_memberships')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(
                ['run_of_show_item_id', 'staff_membership_id'],
                'run_of_show_assignee_unique',
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('event_run_of_show_assignees');

        Schema::table('event_run_of_show_items', function (Blueprint $table): void {
            $table->dropIndex('run_of_show_event_station_index');
            $table->dropConstrainedForeignId('station_id');
            $table->dropConstrainedForeignId('depends_on_item_id');
            $table->dropConstrainedForeignId('status_changed_by');
            $table->dropColumn('status_changed_at');
        });
    }
};
