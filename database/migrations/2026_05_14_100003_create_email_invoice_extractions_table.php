<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('email_invoice_extractions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('email_log_id')->constrained('email_logs')->cascadeOnDelete();
            $table->foreignUuid('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->foreignUuid('vendor_id')->nullable()->constrained('vendors')->nullOnDelete();
            $table->string('pipeline_driver', 64)->default('default');
            $table->string('status', 32)->default('pending')->index();
            $table->json('extracted_payload')->nullable();
            $table->json('field_confidence_scores')->nullable();
            $table->decimal('aggregate_confidence', 5, 4)->nullable()->index();
            $table->json('processing_notes')->nullable();
            $table->timestamps();

            $table->index(['organization_id', 'status']);
            $table->index(['email_log_id', 'pipeline_driver']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('email_invoice_extractions');
    }
};
