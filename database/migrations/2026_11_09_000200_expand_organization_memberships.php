<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('organization_memberships', function (Blueprint $table): void {
            // Expand role from admin|member to include owner.
            // SQLite does not enforce ENUM, so we use string and validate at app level.
            // MySQL: we change the column type; existing admin|member values remain valid.
            $table->string('role')->default('member')->change();

            // Expand status lifecycle: active | suspended | revoked.
            // Existing rows have status='active' or 'inactive'.
            // 'inactive' was the M1 default but has no semantic weight yet;
            // we treat it as equivalent to 'suspended' and migrate via data command.
            $table->string('status')->default('active')->change();

            // Audit/invitation traceability columns.
            $table->foreignId('invited_by')
                ->nullable()
                ->after('status')
                ->constrained('users')
                ->nullOnDelete();

            $table->timestamp('accepted_at')->nullable()->after('invited_by');
        });
    }

    public function down(): void
    {
        Schema::table('organization_memberships', function (Blueprint $table): void {
            $table->dropForeign(['invited_by']);
            $table->dropColumn(['invited_by', 'accepted_at']);
            $table->enum('role', ['admin', 'member'])->default('member')->change();
            $table->enum('status', ['active', 'inactive'])->default('active')->change();
        });
    }
};
