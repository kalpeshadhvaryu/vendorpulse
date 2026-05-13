<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('vendors')) {
            return;
        }

        if (! Schema::hasColumn('vendors', 'organization_id')) {
            return;
        }

        Schema::table('vendors', function (Blueprint $table): void {
            $table->dropForeign(['organization_id']);
        });

        Schema::table('vendors', function (Blueprint $table): void {
            $table->renameColumn('organization_id', 'company_id');
        });

        Schema::table('vendors', function (Blueprint $table): void {
            $table->foreign('company_id')->references('id')->on('organizations')->cascadeOnDelete();
        });

        Schema::table('vendors', function (Blueprint $table): void {
            $table->dropIndex(['organization_id', 'status']);
            $table->dropIndex(['organization_id', 'name']);
        });

        Schema::table('vendors', function (Blueprint $table): void {
            if (Schema::hasColumn('vendors', 'legal_name')) {
                $table->dropColumn(['legal_name', 'category', 'metadata']);
            }
        });

        Schema::table('vendors', function (Blueprint $table): void {
            $table->string('vendor_type', 32)->default('other');
            $table->string('billing_email')->nullable();
            $table->string('support_email')->nullable();
            $table->char('currency', 3)->default('USD');
            $table->decimal('expected_amount', 14, 2)->nullable();
            $table->string('billing_cycle', 32)->default('monthly');
            $table->boolean('auto_detect_invoices')->default(false);
            $table->boolean('monitoring_enabled')->default(false);
        });

        Schema::table('vendors', function (Blueprint $table): void {
            $table->index(['company_id', 'status']);
            $table->index(['company_id', 'name']);
            $table->index(['company_id', 'vendor_type']);
            $table->index(['company_id', 'billing_cycle']);
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('vendors') || ! Schema::hasColumn('vendors', 'company_id')) {
            return;
        }

        if (Schema::hasColumn('vendors', 'organization_id')) {
            return;
        }

        Schema::table('vendors', function (Blueprint $table): void {
            $table->dropIndex(['company_id', 'status']);
            $table->dropIndex(['company_id', 'name']);
            $table->dropIndex(['company_id', 'vendor_type']);
            $table->dropIndex(['company_id', 'billing_cycle']);
        });

        Schema::table('vendors', function (Blueprint $table): void {
            $table->dropForeign(['company_id']);
        });

        Schema::table('vendors', function (Blueprint $table): void {
            $table->dropColumn([
                'vendor_type',
                'billing_email',
                'support_email',
                'currency',
                'expected_amount',
                'billing_cycle',
                'auto_detect_invoices',
                'monitoring_enabled',
            ]);
        });

        Schema::table('vendors', function (Blueprint $table): void {
            $table->string('legal_name')->nullable();
            $table->string('category', 128)->nullable();
            $table->json('metadata')->nullable();
        });

        Schema::table('vendors', function (Blueprint $table): void {
            $table->renameColumn('company_id', 'organization_id');
        });

        Schema::table('vendors', function (Blueprint $table): void {
            $table->foreign('organization_id')->references('id')->on('organizations')->cascadeOnDelete();
            $table->index(['organization_id', 'status']);
            $table->index(['organization_id', 'name']);
        });
    }
};
