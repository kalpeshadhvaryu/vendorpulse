<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('experience_monitoring_run_metrics', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('experience_monitoring_run_id')->constrained('experience_monitoring_runs')->cascadeOnDelete();
            $table->foreignUuid('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->string('metric_key', 120)->index();
            $table->decimal('metric_value', 12, 3)->nullable();
            $table->string('unit', 20)->nullable();
            $table->json('meta')->nullable();
            $table->timestamp('recorded_at')->nullable();
            $table->timestamps();

            $table->index(['experience_monitoring_run_id', 'metric_key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('experience_monitoring_run_metrics');
    }
};
