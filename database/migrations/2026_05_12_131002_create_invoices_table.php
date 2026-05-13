<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('invoices', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->foreignUuid('vendor_id')->nullable()->constrained('vendors')->nullOnDelete();
            $table->string('number');
            $table->string('status', 32)->default('draft');
            $table->char('currency', 3)->default('USD');
            $table->unsignedBigInteger('amount_cents')->default(0);
            $table->unsignedBigInteger('tax_cents')->default(0);
            $table->date('issued_on')->nullable();
            $table->date('due_on')->nullable()->index();
            $table->timestamp('paid_at')->nullable();
            $table->text('description')->nullable();
            $table->json('metadata')->nullable();
            $table->uuid('created_by')->nullable()->index();
            $table->uuid('updated_by')->nullable()->index();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['organization_id', 'number']);
            $table->index(['organization_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invoices');
    }
};
