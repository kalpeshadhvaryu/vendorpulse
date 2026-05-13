<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vendor_emails', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->foreignUuid('vendor_id')->constrained('vendors')->cascadeOnDelete();
            $table->string('email');
            $table->string('label')->nullable();
            $table->string('purpose', 32)->default('general');
            $table->boolean('is_monitored')->default(true);
            $table->timestamp('last_checked_at')->nullable();
            $table->string('last_check_status', 32)->nullable();
            $table->json('metadata')->nullable();
            $table->uuid('created_by')->nullable()->index();
            $table->uuid('updated_by')->nullable()->index();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['organization_id', 'email']);
            $table->index(['vendor_id', 'is_monitored']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vendor_emails');
    }
};
