<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\Invoice;
use App\Models\MonitoringLog;
use App\Support\ApiResponse;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DashboardAnalyticsController extends BaseApiController
{
    public function trends(Request $request): JsonResponse
    {
        $days = max(7, min((int) $request->query('days', 30), 90));
        $to = now()->endOfDay();
        $from = now()->subDays($days - 1)->startOfDay();
        $organizationId = $this->organizationId();

        $monitoringRows = MonitoringLog::query()
            ->selectRaw('DATE(created_at) as day')
            ->selectRaw('COUNT(*) as total_runs')
            ->selectRaw("SUM(CASE WHEN status IN ('failed','error','degraded') THEN 1 ELSE 0 END) as downtime_runs")
            ->where('organization_id', $organizationId)
            ->whereBetween('created_at', [$from, $to])
            ->groupByRaw('DATE(created_at)')
            ->orderByRaw('DATE(created_at)')
            ->get();

        $monitoringByDay = [];
        foreach ($monitoringRows as $row) {
            $day = (string) $row->day;
            $totalRuns = (int) $row->total_runs;
            $downtimeRuns = (int) $row->downtime_runs;
            $monitoringByDay[$day] = [
                'total_runs' => $totalRuns,
                'downtime_runs' => $downtimeRuns,
                'availability_ratio' => $totalRuns > 0 ? round(($totalRuns - $downtimeRuns) / $totalRuns, 4) : null,
            ];
        }

        $issuedRows = Invoice::query()
            ->selectRaw('DATE(created_at) as day')
            ->selectRaw('COUNT(*) as issued_count')
            ->selectRaw('COALESCE(SUM(amount_cents), 0) as issued_amount_cents')
            ->where('organization_id', $organizationId)
            ->whereBetween('created_at', [$from, $to])
            ->groupByRaw('DATE(created_at)')
            ->orderByRaw('DATE(created_at)')
            ->get();

        $issuedByDay = [];
        foreach ($issuedRows as $row) {
            $issuedByDay[(string) $row->day] = [
                'issued_count' => (int) $row->issued_count,
                'issued_amount_cents' => (int) $row->issued_amount_cents,
            ];
        }

        $paidRows = Invoice::query()
            ->selectRaw('DATE(paid_at) as day')
            ->selectRaw('COUNT(*) as paid_count')
            ->selectRaw('COALESCE(SUM(amount_cents), 0) as paid_amount_cents')
            ->where('organization_id', $organizationId)
            ->whereNotNull('paid_at')
            ->whereBetween('paid_at', [$from, $to])
            ->groupByRaw('DATE(paid_at)')
            ->orderByRaw('DATE(paid_at)')
            ->get();

        $paidByDay = [];
        foreach ($paidRows as $row) {
            $paidByDay[(string) $row->day] = [
                'paid_count' => (int) $row->paid_count,
                'paid_amount_cents' => (int) $row->paid_amount_cents,
            ];
        }

        $monitoring = [];
        $invoices = [];

        foreach (CarbonPeriod::create($from, '1 day', $to) as $date) {
            /** @var Carbon $date */
            $day = $date->toDateString();
            $monitoringPoint = $monitoringByDay[$day] ?? [
                'total_runs' => 0,
                'downtime_runs' => 0,
                'availability_ratio' => null,
            ];

            $issued = $issuedByDay[$day] ?? ['issued_count' => 0, 'issued_amount_cents' => 0];
            $paid = $paidByDay[$day] ?? ['paid_count' => 0, 'paid_amount_cents' => 0];

            $monitoring[] = [
                'date' => $day,
                'total_runs' => $monitoringPoint['total_runs'],
                'downtime_runs' => $monitoringPoint['downtime_runs'],
                'availability_ratio' => $monitoringPoint['availability_ratio'],
            ];

            $invoices[] = [
                'date' => $day,
                'issued_count' => $issued['issued_count'],
                'issued_amount_cents' => $issued['issued_amount_cents'],
                'paid_count' => $paid['paid_count'],
                'paid_amount_cents' => $paid['paid_amount_cents'],
            ];
        }

        return ApiResponse::success([
            'range' => [
                'from' => $from->toIso8601String(),
                'to' => $to->toIso8601String(),
                'days' => $days,
            ],
            'monitoring' => $monitoring,
            'invoices' => $invoices,
        ]);
    }
}
