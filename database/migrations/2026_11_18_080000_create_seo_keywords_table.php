<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('seo_keywords', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->foreignUuid('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('phrase', 255);
            $table->string('priority', 16)->default('medium'); // high|medium|low
            $table->string('target_url', 2048)->nullable();
            $table->string('target_domain', 255)->nullable();
            $table->string('country', 2)->default('in');
            $table->string('language', 16)->default('en');
            $table->string('device', 16)->default('desktop'); // mobile|desktop
            $table->json('tags')->nullable();
            $table->text('notes')->nullable();
            $table->string('status', 16)->default('active'); // active|paused
            $table->unsignedSmallInteger('current_google_position')->nullable();
            $table->string('current_google_url', 2048)->nullable();
            $table->unsignedSmallInteger('previous_google_position')->nullable();
            $table->boolean('current_ai_cited')->nullable();
            $table->timestamp('last_checked_at')->nullable();
            $table->timestamp('next_check_at')->nullable();
            $table->timestamps();

            $table->unique(['organization_id', 'phrase', 'country', 'language', 'device'], 'seo_keywords_unique_phrase_locale');
            $table->index(['organization_id', 'status', 'priority']);
            $table->index(['organization_id', 'next_check_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('seo_keywords');
    }
};
