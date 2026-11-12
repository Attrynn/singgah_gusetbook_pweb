<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Milestone 7 — vendor portal access, communications, change requests,
 * engagement revisions, issues and performance reviews.
 *
 * Portal access is engagement-scoped: a membership alone grants nothing until
 * an explicit engagement grant exists. Internal notes and commercial records
 * stay internal regardless of portal membership.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vendor_portal_memberships', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('vendor_id')->constrained()->cascadeOnDelete();
            $table->foreignId('vendor_contact_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('invited_email', 160)->nullable();
            $table->string('status', 30)->default('invited');
            $table->string('invitation_token_hash', 64)->nullable();
            $table->timestamp('invitation_expires_at')->nullable();
            $table->foreignId('invited_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('accepted_at')->nullable();
            $table->timestamp('revoked_at')->nullable();
            $table->foreignId('revoked_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('last_seen_at')->nullable();
            $table->timestamps();

            $table->unique(['vendor_id', 'user_id'], 'vendor_portal_vendor_user_unique');
            $table->index(['user_id', 'status'], 'vendor_portal_user_status_index');
            $table->index('invitation_token_hash');
            $table->index(['invitation_expires_at'], 'vendor_portal_invitation_expiry_index');
        });

        Schema::create('vendor_portal_engagement_access', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('vendor_portal_membership_id')->constrained()->cascadeOnDelete();
            $table->foreignId('event_vendor_engagement_id')->constrained()->cascadeOnDelete();
            $table->timestamp('granted_at')->nullable();
            $table->foreignId('granted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('revoked_at')->nullable();
            $table->timestamps();

            $table->unique(
                ['vendor_portal_membership_id', 'event_vendor_engagement_id'],
                'vendor_portal_access_membership_engagement_unique',
            );
            $table->index(['event_vendor_engagement_id', 'revoked_at'], 'vendor_portal_access_engagement_index');
        });

        Schema::create('event_vendor_change_requests', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('event_id')->constrained()->cascadeOnDelete();
            $table->foreignId('event_vendor_engagement_id')->constrained()->cascadeOnDelete();
            $table->foreignId('requested_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('requested_by_contact_id')->nullable()->constrained('vendor_contacts')->nullOnDelete();
            $table->string('title', 180);
            $table->text('description');
            $table->json('proposed_changes')->nullable();
            $table->text('impact')->nullable();
            $table->string('status', 30)->default('draft');
            $table->foreignId('decided_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('decided_at')->nullable();
            $table->text('decision_notes')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('withdrawn_at')->nullable();
            $table->timestamps();

            $table->index(['event_vendor_engagement_id', 'status'], 'vendor_change_engagement_status_index');
            $table->index(['event_id', 'status'], 'vendor_change_event_status_index');
        });

        Schema::create('event_vendor_engagement_revisions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('event_vendor_engagement_id')->constrained()->cascadeOnDelete();
            $table->foreignId('event_vendor_change_request_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedInteger('revision_number');
            $table->json('snapshot');
            $table->text('reason')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(
                ['event_vendor_engagement_id', 'revision_number'],
                'vendor_revision_engagement_number_unique',
            );
        });

        Schema::create('event_vendor_communications', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('event_id')->constrained()->cascadeOnDelete();
            $table->foreignId('event_vendor_engagement_id')->constrained()->cascadeOnDelete();
            $table->string('direction', 20)->default('internal');
            $table->string('visibility', 30)->default('internal_only');
            $table->string('type', 40)->default('note');
            $table->foreignId('author_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('author_contact_id')->nullable()->constrained('vendor_contacts')->nullOnDelete();
            $table->text('body');
            $table->foreignId('event_vendor_deliverable_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('event_vendor_change_request_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();

            $table->index(['event_vendor_engagement_id', 'visibility'], 'vendor_communication_visibility_index');
            $table->index(['event_vendor_engagement_id', 'created_at'], 'vendor_communication_created_index');
        });

        Schema::create('event_vendor_issues', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('event_id')->constrained()->cascadeOnDelete();
            $table->foreignId('event_vendor_engagement_id')->constrained()->cascadeOnDelete();
            $table->foreignId('event_incident_id')->nullable()->constrained()->nullOnDelete();
            $table->string('title', 180);
            $table->text('description')->nullable();
            $table->string('severity', 20)->default('medium');
            $table->string('status', 30)->default('open');
            $table->foreignId('reported_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('resolved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('resolved_at')->nullable();
            $table->text('resolution_notes')->nullable();
            $table->timestamps();

            $table->index(['event_vendor_engagement_id', 'status'], 'vendor_issue_engagement_status_index');
            $table->index(['event_id', 'severity', 'status'], 'vendor_issue_event_severity_index');
        });

        Schema::create('event_vendor_performance_reviews', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('event_id')->constrained()->cascadeOnDelete();
            $table->foreignId('event_vendor_engagement_id')->constrained()->cascadeOnDelete();
            $table->foreignId('vendor_id')->constrained()->cascadeOnDelete();
            $table->foreignId('reviewer_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedTinyInteger('overall_rating');
            $table->json('criteria')->nullable();
            $table->text('comments')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();

            $table->index(['event_vendor_engagement_id'], 'vendor_review_engagement_index');
            $table->index(['vendor_id', 'reviewed_at'], 'vendor_review_vendor_index');
            $table->index(['organization_id', 'vendor_id'], 'vendor_review_org_vendor_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('event_vendor_performance_reviews');
        Schema::dropIfExists('event_vendor_issues');
        Schema::dropIfExists('event_vendor_communications');
        Schema::dropIfExists('event_vendor_engagement_revisions');
        Schema::dropIfExists('event_vendor_change_requests');
        Schema::dropIfExists('vendor_portal_engagement_access');
        Schema::dropIfExists('vendor_portal_memberships');
    }
};
