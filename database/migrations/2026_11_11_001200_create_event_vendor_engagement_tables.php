<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Milestone 7 — event vendor engagements and private documents.
 *
 * An engagement stores immutable snapshots of the selected service, package and
 * scope so later catalog edits cannot rewrite agreed terms. Documents live on
 * the private disk and are streamed through authorized controllers only.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('event_vendor_engagements', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('event_id')->constrained()->cascadeOnDelete();
            $table->foreignId('vendor_id')->constrained()->restrictOnDelete();
            $table->foreignId('event_vendor_requirement_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('event_vendor_proposal_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('vendor_service_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('vendor_service_package_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('coordinator_membership_id')->nullable()
                ->constrained('event_staff_memberships')->nullOnDelete();
            $table->foreignId('vendor_contact_id')->nullable()->constrained()->nullOnDelete();
            $table->string('title', 180);
            $table->string('status', 30)->default('draft');
            $table->json('service_snapshot')->nullable();
            $table->json('package_snapshot')->nullable();
            $table->json('scope_snapshot')->nullable();
            $table->decimal('agreed_amount', 15, 2)->nullable();
            $table->char('currency', 3)->default('IDR');
            $table->text('selection_reason')->nullable();
            $table->foreignId('selected_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('selected_at')->nullable();
            $table->foreignId('confirmed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('confirmed_at')->nullable();
            $table->foreignId('cancelled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('cancelled_at')->nullable();
            $table->text('cancel_reason')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->foreignId('closed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('closed_at')->nullable();
            $table->text('closure_notes')->nullable();
            $table->json('closure_snapshot')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['event_id', 'status'], 'vendor_engagement_event_status_index');
            $table->index(['vendor_id', 'status'], 'vendor_engagement_vendor_status_index');
            $table->index(['organization_id', 'status'], 'vendor_engagement_org_status_index');
        });

        Schema::create('event_vendor_documents', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('event_id')->constrained()->cascadeOnDelete();
            $table->foreignId('vendor_id')->constrained()->cascadeOnDelete();
            $table->foreignId('event_vendor_engagement_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('event_vendor_proposal_id')->nullable()->constrained()->nullOnDelete();
            $table->string('type', 40)->default('supporting');
            $table->string('title', 180);
            $table->string('disk', 40)->default('local');
            $table->string('path', 255);
            $table->string('original_name', 255);
            $table->string('mime_type', 120);
            $table->unsignedBigInteger('size_bytes')->default(0);
            $table->unsignedInteger('version')->default(1);
            $table->foreignId('supersedes_id')->nullable()
                ->constrained('event_vendor_documents')->nullOnDelete();
            $table->string('review_status', 30)->default('draft');
            $table->string('visibility', 30)->default('internal_only');
            $table->date('effective_date')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['event_id', 'type'], 'vendor_document_event_type_index');
            $table->index(['event_vendor_engagement_id', 'visibility'], 'vendor_document_engagement_visibility_index');
            $table->index(['vendor_id', 'review_status'], 'vendor_document_vendor_review_index');
            $table->index('expires_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('event_vendor_documents');
        Schema::dropIfExists('event_vendor_engagements');
    }
};
