<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Milestone 7 — deliverables, submissions, on-site schedule references and
 * logistics requirements. Internal follow-up work links to M6 tasks; vendor
 * arrival stays a vendor operational record, never a guest check-in.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('event_vendor_deliverables', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('event_id')->constrained()->cascadeOnDelete();
            $table->foreignId('event_vendor_engagement_id')->constrained()->cascadeOnDelete();
            $table->string('title', 180);
            $table->text('description')->nullable();
            $table->foreignId('vendor_contact_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('reviewer_membership_id')->nullable()
                ->constrained('event_staff_memberships')->nullOnDelete();
            $table->foreignId('internal_task_id')->nullable()->constrained('event_tasks')->nullOnDelete();
            $table->timestamp('due_at')->nullable();
            $table->string('status', 30)->default('not_started');
            $table->unsignedInteger('sequence')->default(0);
            $table->foreignId('accepted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('accepted_at')->nullable();
            $table->text('acceptance_notes')->nullable();
            $table->timestamps();

            $table->index(['event_vendor_engagement_id', 'status'], 'vendor_deliverable_engagement_status_index');
            $table->index(['event_id', 'status'], 'vendor_deliverable_event_status_index');
            $table->index('due_at');
        });

        Schema::create('event_vendor_deliverable_submissions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('event_vendor_deliverable_id')->constrained('event_vendor_deliverables', 'id', 'fk_ev_deliv_submissions_deliverable')->cascadeOnDelete();
            $table->foreignId('submitted_by_user_id')->nullable()->constrained('users', 'id', 'fk_ev_deliv_submissions_user')->nullOnDelete();
            $table->foreignId('submitted_by_contact_id')->nullable()->constrained('vendor_contacts', 'id', 'fk_ev_deliv_submissions_contact')->nullOnDelete();
            $table->text('notes')->nullable();
            $table->foreignId('event_vendor_document_id')->nullable()->constrained('event_vendor_documents', 'id', 'fk_ev_deliv_submissions_document')->nullOnDelete();
            $table->string('status', 30)->default('submitted');
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->text('review_notes')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamps();

            $table->index(['event_vendor_deliverable_id', 'submitted_at'], 'vendor_submission_deliverable_index');
        });

        Schema::create('event_vendor_schedule_references', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('event_id')->constrained()->cascadeOnDelete();
            $table->foreignId('event_vendor_engagement_id')->constrained('event_vendor_engagements', 'id', 'fk_ev_schedule_refs_engagement')->cascadeOnDelete();
            $table->foreignId('event_run_of_show_item_id')->nullable()->constrained('event_run_of_show_items', 'id', 'fk_ev_schedule_refs_ros_item')->nullOnDelete();
            $table->foreignId('event_station_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('vendor_contact_id')->nullable()->constrained()->nullOnDelete();
            $table->string('arrival_status', 30)->default('expected');
            $table->timestamp('expected_arrival_at')->nullable();
            $table->timestamp('arrival_at')->nullable();
            $table->timestamp('setup_start_at')->nullable();
            $table->timestamp('setup_end_at')->nullable();
            $table->timestamp('delivery_start_at')->nullable();
            $table->timestamp('delivery_end_at')->nullable();
            $table->timestamp('service_start_at')->nullable();
            $table->timestamp('service_end_at')->nullable();
            $table->timestamp('teardown_start_at')->nullable();
            $table->timestamp('teardown_end_at')->nullable();
            $table->timestamp('ready_at')->nullable();
            $table->timestamp('departed_at')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['event_vendor_engagement_id'], 'vendor_schedule_engagement_index');
            $table->index(['event_id', 'expected_arrival_at'], 'vendor_schedule_event_arrival_index');
        });

        Schema::create('event_vendor_logistics_requirements', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('event_id')->constrained()->cascadeOnDelete();
            $table->foreignId('event_vendor_engagement_id')->constrained('event_vendor_engagements', 'id', 'fk_ev_logistics_reqs_engagement')->cascadeOnDelete();
            $table->foreignId('event_station_id')->nullable()->constrained()->nullOnDelete();
            $table->string('type', 30)->default('other');
            $table->text('description');
            $table->string('responsibility', 30)->default('vendor');
            $table->timestamp('due_at')->nullable();
            $table->string('status', 30)->default('requested');
            $table->text('resolution_note')->nullable();
            $table->foreignId('fulfilled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('fulfilled_at')->nullable();
            $table->timestamps();

            $table->index(['event_vendor_engagement_id', 'status'], 'vendor_logistics_engagement_status_index');
            $table->index(['event_id', 'status'], 'vendor_logistics_event_status_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('event_vendor_logistics_requirements');
        Schema::dropIfExists('event_vendor_schedule_references');
        Schema::dropIfExists('event_vendor_deliverable_submissions');
        Schema::dropIfExists('event_vendor_deliverables');
    }
};
