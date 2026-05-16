<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('organizations', function (Blueprint $table): void {
            $table->string('phone_country_code', 8)->nullable()->after('slug');
            $table->string('phone_number', 20)->nullable()->after('phone_country_code');
        });
    }

    public function down(): void
    {
        Schema::table('organizations', function (Blueprint $table): void {
            $table->dropColumn(['phone_country_code', 'phone_number']);
        });
    }
};
