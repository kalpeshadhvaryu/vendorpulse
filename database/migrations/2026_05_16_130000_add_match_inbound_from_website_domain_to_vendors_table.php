<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('vendors', function (Blueprint $table): void {
            $table->boolean('match_inbound_from_website_domain')->default(false)->after('auto_fetch_email');
        });
    }

    public function down(): void
    {
        Schema::table('vendors', function (Blueprint $table): void {
            $table->dropColumn('match_inbound_from_website_domain');
        });
    }
};
