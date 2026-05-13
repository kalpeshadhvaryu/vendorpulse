<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('email_logs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->foreignUuid('email_mailbox_id')->nullable()->constrained('email_mailboxes')->nullOnDelete();
            $table->string('external_message_id')->index();
            $table->string('in_reply_to')->nullable()->index();
            $table->string('subject')->nullable();
            $table->string('from_email')->nullable()->index();
            $table->json('to_recipients')->nullable();
            $table->json('cc_recipients')->nullable();
            $table->timestamp('received_at')->nullable()->index();
            $table->string('processing_status', 32)->default('pending')->index();
            $table->foreignUuid('vendor_id')->nullable()->constrained('vendors')->nullOnDelete();
            $table->foreignUuid('vendor_email_id')->nullable()->constrained('vendor_emails')->nullOnDelete();
            $table->decimal('vendor_match_confidence', 5, 4)->nullable();
            $table->longText('body_text')->nullable();
            $table->longText('body_html')->nullable();
            $table->json('raw_envelope')->nullable();
            $table->json('normalized_payload')->nullable();
            $table->json('processing_meta')->nullable();
            $table->string('failure_reason', 512)->nullable();
            $table->uuid('created_by')->nullable()->index();
            $table->uuid('updated_by')->nullable()->index();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['organization_id', 'email_mailbox_id', 'external_message_id'], 'email_logs_org_mailbox_msg_unique');
            $table->index(['organization_id', 'processing_status']);
            $table->index(['organization_id', 'received_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('email_logs');
    }
};
