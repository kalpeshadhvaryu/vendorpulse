<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('experience_monitoring_tests', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->string('name');
            $table->string('login_url', 2048);
            $table->string('login_username', 255);
            $table->text('login_password');
            $table->string('dashboard_url', 2048);
            $table->unsignedInteger('interval_seconds')->default(300);
            $table->string('browser_type', 24)->default('chromium');
            $table->unsignedInteger('timeout_ms')->default(30000);
            $table->unsignedSmallInteger('concurrent_sessions')->nullable();
            $table->json('configuration')->nullable();
            $table->json('thresholds')->nullable();
            $table->boolean('enabled')->default(true);
            $table->string('last_status', 32)->nullable();
            $table->text('last_error')->nullable();
            $table->timestamp('last_run_at')->nullable();
            $table->timestamp('next_run_at')->nullable()->index();
            $table->uuid('created_by')->nullable()->index();
            $table->uuid('updated_by')->nullable()->index();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['organization_id', 'enabled']);
            $table->index(['organization_id', 'browser_type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('experience_monitoring_tests');
    }
};
