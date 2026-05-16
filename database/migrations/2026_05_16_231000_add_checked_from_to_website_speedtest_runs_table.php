<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('website_speedtest_runs', function (Blueprint $table) {
            $table->string('checked_from', 255)->nullable()->after('target_url');
        });
    }

    public function down(): void
    {
        Schema::table('website_speedtest_runs', function (Blueprint $table) {
            $table->dropColumn('checked_from');
        });
    }
};
