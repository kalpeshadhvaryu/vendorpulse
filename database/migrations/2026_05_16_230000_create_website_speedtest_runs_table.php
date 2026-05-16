<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('website_speedtest_runs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->uuid('created_by')->nullable()->index();
            $table->string('target_url', 2048);
            $table->string('final_url', 2048)->nullable();
            $table->unsignedSmallInteger('status_code')->nullable();
            $table->boolean('success')->default(false);
            $table->text('error')->nullable();
            $table->unsignedSmallInteger('timeout_seconds')->default(20);
            $table->decimal('total_time_ms', 12, 2)->nullable();
            $table->decimal('ttfb_ms', 12, 2)->nullable();
            $table->decimal('dns_lookup_ms', 12, 2)->nullable();
            $table->decimal('tcp_connect_ms', 12, 2)->nullable();
            $table->decimal('tls_handshake_ms', 12, 2)->nullable();
            $table->decimal('redirect_time_ms', 12, 2)->nullable();
            $table->decimal('download_speed_kbps', 14, 2)->nullable();
            $table->unsignedBigInteger('downloaded_bytes')->nullable();
            $table->json('metrics')->nullable();
            $table->timestamp('tested_at')->nullable();
            $table->timestamps();

            $table->index(['organization_id', 'created_at']);
            $table->index(['organization_id', 'target_url', 'tested_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('website_speedtest_runs');
    }
};
