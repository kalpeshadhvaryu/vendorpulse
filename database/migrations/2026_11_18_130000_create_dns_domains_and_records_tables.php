<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dns_domains', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->string('name', 255);
            $table->string('provider', 32); // cloudflare | manual
            $table->foreignUuid('cloudflare_zone_id')->nullable()->constrained('cloudflare_zones')->nullOnDelete();
            $table->text('notes')->nullable();
            $table->foreignUuid('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['organization_id', 'name']);
            $table->index(['organization_id', 'provider']);
        });

        Schema::create('dns_records', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('dns_domain_id')->constrained('dns_domains')->cascadeOnDelete();
            $table->string('type', 16);
            $table->string('name', 255);
            $table->text('content');
            $table->unsignedInteger('ttl')->default(3600);
            $table->unsignedInteger('priority')->nullable();
            $table->boolean('proxied')->nullable();
            $table->string('cf_record_id', 64)->nullable();
            $table->string('comment', 500)->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->index(['dns_domain_id', 'type']);
            $table->unique(['dns_domain_id', 'cf_record_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dns_records');
        Schema::dropIfExists('dns_domains');
    }
};
