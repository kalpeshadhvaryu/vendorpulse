<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vendors', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('company_id')->constrained('organizations')->cascadeOnDelete();
            $table->string('name');
            $table->string('vendor_type', 32);
            $table->string('billing_email')->nullable();
            $table->string('support_email')->nullable();
            $table->string('website')->nullable();
            $table->char('currency', 3)->default('USD');
            $table->decimal('expected_amount', 14, 2)->nullable();
            $table->string('billing_cycle', 32);
            $table->date('renewal_date')->nullable()->index();
            $table->boolean('auto_detect_invoices')->default(false);
            $table->boolean('monitoring_enabled')->default(false);
            $table->text('notes')->nullable();
            $table->string('status', 32)->default('active');
            $table->uuid('created_by')->nullable()->index();
            $table->uuid('updated_by')->nullable()->index();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['company_id', 'status']);
            $table->index(['company_id', 'name']);
            $table->index(['company_id', 'vendor_type']);
            $table->index(['company_id', 'billing_cycle']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vendors');
    }
};
