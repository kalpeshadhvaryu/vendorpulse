<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('email_mailboxes', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->string('name');
            $table->string('driver', 32);
            $table->boolean('is_enabled')->default(true);
            $table->json('connection_config')->nullable();
            $table->json('sync_state')->nullable();
            $table->timestamp('last_polled_at')->nullable();
            $table->timestamp('last_successful_sync_at')->nullable();
            $table->text('last_error')->nullable();
            $table->uuid('created_by')->nullable()->index();
            $table->uuid('updated_by')->nullable()->index();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['organization_id', 'driver', 'is_enabled']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('email_mailboxes');
    }
};
