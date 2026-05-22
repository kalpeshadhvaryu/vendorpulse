<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('experience_monitoring_screenshots', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('experience_monitoring_run_id')->constrained('experience_monitoring_runs')->cascadeOnDelete();
            $table->foreignUuid('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->string('path', 2048);
            $table->string('mime_type', 100)->default('image/png');
            $table->unsignedInteger('size_bytes')->nullable();
            $table->unsignedInteger('width')->nullable();
            $table->unsignedInteger('height')->nullable();
            $table->timestamp('captured_at')->nullable();
            $table->timestamps();

            $table->index(['experience_monitoring_run_id', 'captured_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('experience_monitoring_screenshots');
    }
};
