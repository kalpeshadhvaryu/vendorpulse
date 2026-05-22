<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('experience_monitoring_execution_logs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('experience_monitoring_run_id')->constrained('experience_monitoring_runs')->cascadeOnDelete();
            $table->foreignUuid('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->string('source', 32)->default('system');
            $table->string('level', 16)->default('info');
            $table->text('message');
            $table->json('context')->nullable();
            $table->timestamp('occurred_at')->nullable();
            $table->timestamps();

            $table->index(['experience_monitoring_run_id', 'source']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('experience_monitoring_execution_logs');
    }
};
