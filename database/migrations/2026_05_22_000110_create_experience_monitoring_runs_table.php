<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('experience_monitoring_runs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('experience_monitoring_test_id')->constrained('experience_monitoring_tests')->cascadeOnDelete();
            $table->foreignUuid('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->unsignedInteger('session_index')->default(1);
            $table->string('status', 32)->index();
            $table->unsignedInteger('login_duration_ms')->nullable();
            $table->unsignedInteger('dashboard_load_duration_ms')->nullable();
            $table->unsignedInteger('total_duration_ms')->nullable();
            $table->unsignedSmallInteger('http_status')->nullable();
            $table->unsignedInteger('failed_requests_count')->default(0);
            $table->unsignedInteger('js_errors_count')->default(0);
            $table->string('screenshot_path', 2048)->nullable();
            $table->json('http_status_codes')->nullable();
            $table->json('response_times')->nullable();
            $table->json('browser_logs')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();

            $table->index(['experience_monitoring_test_id', 'created_at']);
            $table->index(['organization_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('experience_monitoring_runs');
    }
};
