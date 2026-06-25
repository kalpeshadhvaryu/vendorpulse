<?php

use App\Models\MonitoringCheck;
use App\Models\MonitoringLog;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('monitoring_checks', function (Blueprint $table): void {
            $table->json('last_meta')->nullable()->after('last_response_time_ms');
        });

        MonitoringCheck::query()
            ->select(['id'])
            ->orderBy('id')
            ->chunkById(100, function ($checks): void {
                foreach ($checks as $check) {
                    $log = MonitoringLog::query()
                        ->where('monitoring_check_id', $check->id)
                        ->orderByDesc('created_at')
                        ->orderByDesc('id')
                        ->first();

                    if ($log === null || ! is_array($log->meta) || $log->meta === []) {
                        continue;
                    }

                    MonitoringCheck::query()
                        ->whereKey($check->id)
                        ->update(['last_meta' => $log->meta]);
                }
            });
    }

    public function down(): void
    {
        Schema::table('monitoring_checks', function (Blueprint $table): void {
            $table->dropColumn('last_meta');
        });
    }
};
