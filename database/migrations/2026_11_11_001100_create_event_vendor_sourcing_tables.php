<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Milestone 7 — event vendor sourcing: requirements, shortlist candidates and
 * proposals. Requirement candidates and proposals keep their history after a
 * selection is made; nothing is deleted when another vendor wins.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('event_vendor_requirements', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('event_id')->constrained()->cascadeOnDelete();
            $table->foreignId('vendor_category_id')->nullable()->constrained()->nullOnDelete();
            $table->string('title', 180);
            $table->text('description')->nullable();
            $table->text('scope')->nullable();
            $table->unsignedInteger('quantity')->nullable();
            $table->date('needed_by')->nullable();
            $table->text('desired_availability')->nullable();
            $table->foreignId('owner_membership_id')->nullable()
                ->constrained('event_staff_memberships')->nullOnDelete();
            $table->text('notes')->nullable();
            $table->string('status', 30)->default('open');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['event_id', 'status'], 'vendor_requirement_event_status_index');
            $table->index(['event_id', 'vendor_category_id'], 'vendor_requirement_event_category_index');
        });

        Schema::create('event_vendor_requirement_candidates', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('event_vendor_requirement_id')->constrained()->cascadeOnDelete();
            $table->foreignId('vendor_id')->constrained()->cascadeOnDelete();
            $table->string('state', 30)->default('candidate');
            $table->boolean('is_preferred')->default(false);
            $table->text('notes')->nullable();
            $table->foreignId('added_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(
                ['event_vendor_requirement_id', 'vendor_id'],
                'vendor_candidate_requirement_vendor_unique',
            );
            $table->index(['event_vendor_requirement_id', 'state'], 'vendor_candidate_state_index');
        });

        Schema::create('event_vendor_proposals', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('event_id')->constrained()->cascadeOnDelete();
            $table->foreignId('event_vendor_requirement_id')->constrained()->cascadeOnDelete();
            $table->foreignId('vendor_id')->constrained()->cascadeOnDelete();
            $table->foreignId('vendor_service_package_id')->nullable()->constrained()->nullOnDelete();
            $table->string('title', 180);
            $table->text('service_description')->nullable();
            $table->decimal('quoted_amount', 15, 2)->nullable();
            $table->char('currency', 3)->default('IDR');
            $table->json('inclusions')->nullable();
            $table->json('exclusions')->nullable();
            $table->date('valid_until')->nullable();
            $table->text('proposed_schedule')->nullable();
            $table->string('status', 30)->default('draft');
            $table->text('evaluation_notes')->nullable();
            $table->unsignedTinyInteger('evaluation_rating')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['event_id', 'status'], 'vendor_proposal_event_status_index');
            $table->index(['event_vendor_requirement_id', 'vendor_id'], 'vendor_proposal_requirement_vendor_index');
            $table->index(['event_vendor_requirement_id', 'status'], 'vendor_proposal_requirement_status_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('event_vendor_proposals');
        Schema::dropIfExists('event_vendor_requirement_candidates');
        Schema::dropIfExists('event_vendor_requirements');
    }
};
