<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('email_deliverability_tests', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('token', 32)->unique();
            $table->foreignUuid('organization_id')->nullable()->constrained('organizations')->nullOnDelete();
            $table->uuid('created_by')->nullable()->index();
            $table->string('source', 16)->default('public');
            $table->string('status', 16)->default('waiting');
            $table->string('requester_ip', 45)->nullable();
            $table->string('from_address')->nullable();
            $table->string('subject', 512)->nullable();
            $table->unsignedTinyInteger('score')->nullable();
            $table->string('grade', 2)->nullable();
            $table->json('result')->nullable();
            $table->timestamp('expires_at');
            $table->timestamp('received_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'expires_at']);
            $table->index(['organization_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('email_deliverability_tests');
    }
};
