<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('seo_keyword_checks', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('seo_keyword_id')->constrained('seo_keywords')->cascadeOnDelete();
            $table->foreignUuid('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->timestamp('checked_at');
            $table->string('source', 16)->default('manual'); // manual|scheduled
            $table->string('provider', 64)->nullable();
            $table->unsignedSmallInteger('google_position')->nullable();
            $table->string('google_url', 2048)->nullable();
            $table->json('ai_results')->nullable();
            $table->json('meta')->nullable();
            $table->text('error')->nullable();
            $table->timestamps();

            $table->index(['seo_keyword_id', 'checked_at']);
            $table->index(['organization_id', 'checked_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('seo_keyword_checks');
    }
};
