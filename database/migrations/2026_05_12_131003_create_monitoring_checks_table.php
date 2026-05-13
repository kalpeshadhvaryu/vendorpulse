<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('monitoring_checks', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->foreignUuid('vendor_id')->nullable()->constrained('vendors')->nullOnDelete();
            $table->string('name');
            $table->string('type', 32);
            $table->string('endpoint')->nullable();
            $table->json('configuration')->nullable();
            $table->unsignedInteger('interval_seconds')->default(300);
            $table->boolean('enabled')->default(true);
            $table->string('last_status', 32)->nullable();
            $table->text('last_message')->nullable();
            $table->timestamp('last_run_at')->nullable();
            $table->timestamp('next_run_at')->nullable()->index();
            $table->uuid('created_by')->nullable()->index();
            $table->uuid('updated_by')->nullable()->index();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['organization_id', 'enabled']);
            $table->index(['organization_id', 'type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('monitoring_checks');
    }
};
