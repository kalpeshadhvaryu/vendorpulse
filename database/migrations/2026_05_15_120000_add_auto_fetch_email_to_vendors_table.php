<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('vendors', function (Blueprint $table): void {
            $table->boolean('auto_fetch_email')->default(true)->after('auto_detect_invoices');
        });
    }

    public function down(): void
    {
        Schema::table('vendors', function (Blueprint $table): void {
            $table->dropColumn('auto_fetch_email');
        });
    }
};
