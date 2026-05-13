<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('email_log_attachments', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('email_log_id')->constrained('email_logs')->cascadeOnDelete();
            $table->string('original_filename');
            $table->string('mime_type', 191)->nullable();
            $table->unsignedBigInteger('size_bytes')->default(0);
            $table->string('storage_disk', 32)->default('local');
            $table->string('storage_path')->nullable();
            $table->string('content_id')->nullable()->index();
            $table->string('sha256', 64)->nullable()->index();
            $table->string('ocr_status', 32)->default('pending')->index();
            $table->longText('ocr_text')->nullable();
            $table->json('ocr_meta')->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->index(['email_log_id', 'ocr_status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('email_log_attachments');
    }
};
