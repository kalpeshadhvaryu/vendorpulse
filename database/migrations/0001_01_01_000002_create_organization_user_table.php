<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('organization_user', function (Blueprint $table) {
            $table->uuid('organization_id');
            $table->uuid('user_id');
            $table->string('role', 64)->default('member');
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->primary(['organization_id', 'user_id']);
            $table->foreign('organization_id')->references('id')->on('organizations')->cascadeOnDelete();
            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('organization_user');
    }
};
