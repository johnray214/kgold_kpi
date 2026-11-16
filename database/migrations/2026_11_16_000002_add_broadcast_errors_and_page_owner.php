<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('broadcasts', function (Blueprint $t) {
            $t->unsignedInteger('blocked_count')->default(0)->after('failed');
            $t->unsignedInteger('api_limit_count')->default(0)->after('blocked_count');
            $t->jsonb('error_breakdown')->nullable()->after('failed_reason');
        });

        Schema::table('pages', function (Blueprint $t) {
            $t->string('team')->nullable()->after('product');
        });
    }

    public function down(): void
    {
        Schema::table('broadcasts', function (Blueprint $t) {
            $t->dropColumn(['blocked_count', 'api_limit_count', 'error_breakdown']);
        });
        Schema::table('pages', function (Blueprint $t) {
            $t->dropColumn('team');
        });
    }
};
