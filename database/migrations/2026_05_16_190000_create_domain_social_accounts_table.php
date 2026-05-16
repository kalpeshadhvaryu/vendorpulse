<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('domain_social_accounts', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('monitoring_check_id')->constrained('monitoring_checks')->cascadeOnDelete();
            $table->foreignUuid('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->string('platform_name', 64);
            $table->string('social_handle_or_url', 2048);
            $table->integer('last_follower_count')->default(0);
            $table->timestamp('updated_at')->useCurrent()->useCurrentOnUpdate();

            $table->unique(['organization_id', 'monitoring_check_id', 'platform_name'], 'domain_social_accounts_unique_platform_per_domain');
            $table->index(['organization_id', 'monitoring_check_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('domain_social_accounts');
    }
};
