@extends('reports.pdf.layout')

@section('content')
    @php
        $metrics = $report['metrics'] ?? [];
        $success = (bool) ($report['success'] ?? false);
    @endphp

    <div class="section">
        <h2>Result</h2>
        <table class="cards">
            <tr>
                <td class="card">
                    <div class="label">Status</div>
                    <div class="value">
                        <span class="badge {{ $success ? 'badge-ok' : 'badge-fail' }}">{{ $success ? 'Success' : 'Failed' }}</span>
                    </div>
                </td>
                <td class="card"><div class="label">HTTP</div><div class="value">{{ $report['status_code'] ?? '—' }}</div></td>
                <td class="card"><div class="label">Total time</div><div class="value">{{ isset($metrics['total_time_ms']) ? number_format((float) $metrics['total_time_ms'], 0).' ms' : '—' }}</div></td>
                <td class="card"><div class="label">TTFB</div><div class="value">{{ isset($metrics['ttfb_ms']) ? number_format((float) $metrics['ttfb_ms'], 0).' ms' : '—' }}</div></td>
            </tr>
        </table>
        @if (!empty($report['error']))
            <div class="finding fail" style="margin-top:10px;">{{ $report['error'] }}</div>
        @endif
        <div class="meta-line" style="margin-top:8px;">
            Final URL: <span class="mono">{{ $report['final_url'] ?? $report['target_url'] ?? '—' }}</span>
        </div>
        @if (!empty($report['checked_from']))
            <div class="meta-line">Checked from: {{ $report['checked_from'] }}</div>
        @endif
    </div>

    <div class="section">
        <h2>Timing metrics</h2>
        <table class="data">
            <thead><tr><th>Metric</th><th>Value</th></tr></thead>
            <tbody>
                @foreach ([
                    'dns_lookup_ms' => 'DNS lookup',
                    'tcp_connect_ms' => 'TCP connect',
                    'tls_handshake_ms' => 'TLS handshake',
                    'redirect_time_ms' => 'Redirect time',
                    'ttfb_ms' => 'Time to first byte',
                    'total_time_ms' => 'Total time',
                    'download_speed_kbps' => 'Download speed (kbps)',
                    'downloaded_bytes' => 'Downloaded bytes',
                ] as $key => $label)
                    <tr>
                        <td>{{ $label }}</td>
                        <td class="mono">
                            @if (isset($metrics[$key]) && is_numeric($metrics[$key]))
                                {{ number_format((float) $metrics[$key], str_contains($key, 'bytes') || str_contains($key, 'kbps') ? 1 : 0) }}
                            @else
                                —
                            @endif
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
@endsection
