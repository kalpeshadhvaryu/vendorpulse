<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('monitoring_checks', function (Blueprint $table) {
            $table->unsignedSmallInteger('last_http_status')->nullable()->after('last_message');
            $table->unsignedInteger('last_response_time_ms')->nullable()->after('last_http_status');
            $table->unsignedSmallInteger('consecutive_failures')->default(0)->after('last_response_time_ms');
        });
    }

    public function down(): void
    {
        Schema::table('monitoring_checks', function (Blueprint $table) {
            $table->dropColumn([
                'last_http_status',
                'last_response_time_ms',
                'consecutive_failures',
            ]);
        });
    }
};
