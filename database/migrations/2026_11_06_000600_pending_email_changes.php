<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * VS-ID-001: a new email address is only stored as pending until the owner of
 * that address proves control. The login identity never changes silently.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->string('pending_email', 190)->nullable()->after('email');
            $table->string('email_change_token_hash', 64)->nullable()->after('pending_email');
            $table->timestamp('email_change_expires_at')->nullable()->after('email_change_token_hash');
            // Unique index preflight: fail fast if duplicates exist.
            $table->unique('pending_email', 'users_unique_pending_email');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn(['pending_email', 'email_change_token_hash', 'email_change_expires_at']);
        });
    }
};
