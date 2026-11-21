<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('guest_tokens', function (Blueprint $table) {
            $table->string('short_code', 12)->nullable()->unique()->after('token_prefix');
        });

        $alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';

        DB::table('guest_tokens')
            ->where('type', 'qr')
            ->whereNull('short_code')
            ->orderBy('id')
            ->each(function (object $token) use ($alphabet): void {
                do {
                    $code = '';
                    for ($i = 0; $i < 8; $i++) {
                        $code .= $alphabet[random_int(0, strlen($alphabet) - 1)];
                    }
                } while (DB::table('guest_tokens')->where('short_code', $code)->exists());

                DB::table('guest_tokens')->where('id', $token->id)->update(['short_code' => $code]);
            });
    }

    public function down(): void
    {
        Schema::table('guest_tokens', function (Blueprint $table) {
            $table->dropUnique(['short_code']);
            $table->dropColumn('short_code');
        });
    }
};
