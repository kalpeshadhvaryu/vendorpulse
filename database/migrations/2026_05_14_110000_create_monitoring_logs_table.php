<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('monitoring_logs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('monitoring_check_id')->constrained('monitoring_checks')->cascadeOnDelete();
            $table->foreignUuid('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->string('status', 32);
            $table->unsignedSmallInteger('http_status')->nullable();
            $table->unsignedInteger('response_time_ms')->nullable();
            $table->text('message')->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->index(['monitoring_check_id', 'created_at']);
            $table->index(['organization_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('monitoring_logs');
    }
};
