@extends('reports.pdf.layout')

@section('content')
    @php
        $journey = $report['journey'] ?? [];
        $network = $report['network'] ?? [];
        $dns = $network['dns'] ?? [];
        $tls = $network['tls'] ?? [];
        $headers = $network['security_headers'] ?? [];
        $speed = $network['latest_speedtest'] ?? [];
        $findings = $report['findings'] ?? [];
        $suggestions = $report['suggestions'] ?? [];
    @endphp

    <div class="section">
        <h2>Executive summary</h2>
        <table class="cards">
            <tr>
                <td class="card"><div class="label">Severity</div><div class="value">{{ $report['severity'] ?? '—' }}</div></td>
                <td class="card"><div class="label">Risk score</div><div class="value">{{ $report['risk_score'] ?? '—' }}</div></td>
                <td class="card"><div class="label">Success rate</div><div class="value">{{ isset($journey['success_rate_pct']) ? $journey['success_rate_pct'].'%' : '—' }}</div></td>
                <td class="card"><div class="label">Samples</div><div class="value">{{ $report['sample_count'] ?? '—' }}</div></td>
            </tr>
        </table>
        @if (!empty($report['period']['from']) || !empty($report['from']))
            <div class="meta-line" style="margin-top:8px;">
                Period: {{ $report['period']['from'] ?? $report['from'] ?? '—' }}
                → {{ $report['period']['to'] ?? $report['to'] ?? '—' }}
            </div>
        @endif
    </div>

    <div class="section">
        <h2>Technical diagnostics</h2>
        <table class="data">
            <thead><tr><th>Check</th><th>Value</th></tr></thead>
            <tbody>
                <tr><td>DNS host</td><td class="mono">{{ $dns['host'] ?? '—' }}</td></tr>
                <tr><td>Resolvers</td><td class="mono">{{ isset($dns['resolver_nameservers']) ? implode(', ', (array) $dns['resolver_nameservers']) : '—' }}</td></tr>
                <tr><td>DNS lookup</td><td>{{ isset($dns['lookup_latency_ms']) ? $dns['lookup_latency_ms'].' ms' : '—' }}</td></tr>
                <tr><td>TLS handshake</td><td>{{ isset($tls['handshake_ms']) ? $tls['handshake_ms'].' ms' : '—' }}</td></tr>
                <tr><td>TLS protocol</td><td>{{ $tls['protocol'] ?? '—' }}</td></tr>
                <tr><td>Certificate days remaining</td><td>{{ $tls['certificate_days_remaining'] ?? '—' }}</td></tr>
                <tr><td>Server header</td><td class="mono">{{ $headers['server'] ?? '—' }}</td></tr>
                <tr><td>Missing security headers</td><td>{{ isset($headers['missing_critical']) && is_array($headers['missing_critical']) && count($headers['missing_critical']) ? implode(', ', $headers['missing_critical']) : 'None' }}</td></tr>
                <tr><td>Latest speedtest total</td><td>{{ isset($speed['total_time_ms']) ? $speed['total_time_ms'].' ms' : '—' }}</td></tr>
                <tr><td>Latest speedtest TTFB</td><td>{{ isset($speed['ttfb_ms']) ? $speed['ttfb_ms'].' ms' : '—' }}</td></tr>
            </tbody>
        </table>
    </div>

    @if (is_array($findings) && count($findings) > 0)
        <div class="section">
            <h2>Findings</h2>
            @foreach ($findings as $finding)
                <div class="finding {{ strtolower((string) ($finding['severity'] ?? '')) === 's0' || strtolower((string) ($finding['severity'] ?? '')) === 's1' ? 'fail' : 'warning' }}">
                    <div class="finding-title">
                        [{{ $finding['severity'] ?? '—' }}] {{ $finding['title'] ?? 'Finding' }}
                    </div>
                    <div>{{ $finding['detail'] ?? $finding['explanation'] ?? '' }}</div>
                </div>
            @endforeach
        </div>
    @endif

    @if (is_array($suggestions) && count($suggestions) > 0)
        <div class="section">
            <h2>Suggested actions</h2>
            <ol>
                @foreach ($suggestions as $suggestion)
                    <li style="margin-bottom:4px;">{{ is_scalar($suggestion) ? $suggestion : json_encode($suggestion) }}</li>
                @endforeach
            </ol>
        </div>
    @endif
@endsection
