<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('event_staff_memberships', function (Blueprint $table): void {
            $table->foreignId('invited_by')->nullable()->after('assigned_by')
                ->constrained('users')->nullOnDelete();
            $table->timestamp('invited_at')->nullable()->after('invited_by');
            $table->timestamp('accepted_at')->nullable()->after('invited_at');
            $table->timestamp('declined_at')->nullable()->after('accepted_at');
            $table->timestamp('suspended_at')->nullable()->after('declined_at');
            $table->timestamp('removed_at')->nullable()->after('suspended_at');
            $table->timestamp('expires_at')->nullable()->after('removed_at');
            $table->string('notes', 500)->nullable()->after('expires_at');

            $table->index(['event_id', 'status'], 'staff_membership_event_status_index');
        });

        Schema::create('event_staff_permission_grants', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('event_id')->constrained()->cascadeOnDelete();
            $table->foreignId('event_staff_membership_id')
                ->constrained('event_staff_memberships')->cascadeOnDelete();
            $table->string('permission', 100);
            $table->foreignId('granted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(
                ['event_staff_membership_id', 'permission'],
                'staff_permission_grant_unique',
            );
            $table->index(['event_id', 'permission'], 'staff_permission_event_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('event_staff_permission_grants');

        Schema::table('event_staff_memberships', function (Blueprint $table): void {
            $table->dropIndex('staff_membership_event_status_index');
            $table->dropConstrainedForeignId('invited_by');
            $table->dropColumn([
                'invited_at',
                'accepted_at',
                'declined_at',
                'suspended_at',
                'removed_at',
                'expires_at',
                'notes',
            ]);
        });
    }
};
