<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cloudflare_zones', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('cf_zone_id', 64)->unique();
            $table->string('name', 255);
            $table->string('status', 32)->nullable();
            $table->boolean('paused')->default(false);
            $table->string('plan_name', 128)->nullable();
            $table->json('name_servers')->nullable();
            $table->string('ssl_mode', 64)->nullable();
            $table->string('ssl_status', 64)->nullable();
            $table->json('meta')->nullable();
            $table->foreignUuid('organization_id')->nullable()->constrained('organizations')->nullOnDelete();
            $table->foreignUuid('assigned_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('assigned_at')->nullable();
            $table->timestamp('last_synced_at')->nullable();
            $table->timestamps();

            $table->index(['organization_id', 'name']);
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cloudflare_zones');
    }
};
