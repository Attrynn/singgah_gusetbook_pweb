<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Milestone 7 — organization-scoped vendor directory and service catalog.
 *
 * All directory data is owned by an organization; an event engagement later
 * references a vendor from the same organization. Vendors are never hard
 * deleted while engagements exist (archive instead).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vendor_categories', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->string('name', 120);
            $table->string('slug', 140);
            $table->text('description')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['organization_id', 'slug'], 'vendor_category_slug_unique');
            $table->index(['organization_id', 'is_active'], 'vendor_category_active_index');
        });

        Schema::create('vendors', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('vendor_category_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name', 160);
            $table->string('legal_name', 200)->nullable();
            $table->string('type', 30)->default('company');
            $table->text('description')->nullable();
            $table->string('email', 160)->nullable();
            $table->string('phone', 40)->nullable();
            $table->string('website', 255)->nullable();
            $table->text('address')->nullable();
            $table->text('internal_notes')->nullable();
            $table->string('status', 30)->default('pending_verification');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('archived_at')->nullable();
            $table->timestamps();

            $table->index(['organization_id', 'status'], 'vendor_org_status_index');
            $table->index(['organization_id', 'name'], 'vendor_org_name_index');
            $table->index(['organization_id', 'vendor_category_id'], 'vendor_org_category_index');
            $table->index(['organization_id', 'type'], 'vendor_org_type_index');
        });

        Schema::create('vendor_contacts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('vendor_id')->constrained()->cascadeOnDelete();
            $table->string('name', 160);
            $table->string('position', 120)->nullable();
            $table->string('email', 160)->nullable();
            $table->string('phone', 40)->nullable();
            $table->string('purpose', 30)->default('business');
            $table->boolean('is_primary')->default(false);
            $table->boolean('is_active')->default(true);
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['vendor_id', 'is_active'], 'vendor_contact_active_index');
            $table->index(['vendor_id', 'purpose'], 'vendor_contact_purpose_index');
        });

        Schema::create('vendor_services', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('vendor_id')->constrained()->cascadeOnDelete();
            $table->foreignId('vendor_category_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name', 160);
            $table->text('description')->nullable();
            $table->text('scope')->nullable();
            $table->unsignedInteger('capacity')->nullable();
            $table->text('conditions')->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('version')->default(1);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['vendor_id', 'is_active'], 'vendor_service_active_index');
            $table->index(['vendor_id', 'vendor_category_id'], 'vendor_service_category_index');
        });

        Schema::create('vendor_service_packages', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('vendor_service_id')->constrained()->cascadeOnDelete();
            $table->string('name', 160);
            $table->text('description')->nullable();
            $table->decimal('price_amount', 15, 2)->nullable();
            $table->char('currency', 3)->default('IDR');
            $table->json('inclusions')->nullable();
            $table->json('exclusions')->nullable();
            $table->unsignedInteger('capacity')->nullable();
            $table->text('conditions')->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('version')->default(1);
            $table->timestamps();

            $table->index(['vendor_service_id', 'is_active'], 'vendor_package_active_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vendor_service_packages');
        Schema::dropIfExists('vendor_services');
        Schema::dropIfExists('vendor_contacts');
        Schema::dropIfExists('vendors');
        Schema::dropIfExists('vendor_categories');
    }
};
