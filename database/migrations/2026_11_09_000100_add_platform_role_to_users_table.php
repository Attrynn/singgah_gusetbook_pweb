<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            // Explicit platform-level identity, kept separate from customer roles.
            // NULL means no platform authority. Values: superadmin, admin.
            // Public registration never sets this column.
            $table->string('platform_role')->nullable()->after('role')->index();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropIndex(['platform_role']);
            $table->dropColumn('platform_role');
        });
    }
};
