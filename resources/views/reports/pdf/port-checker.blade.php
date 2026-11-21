@extends('reports.pdf.layout')

@section('content')
    @php
        $summary = $report['summary'] ?? [];
        $ports = $report['ports'] ?? [];
        $findings = $report['findings'] ?? [];
        $edge = $report['edge_provider'] ?? [];
    @endphp

    <div class="section">
        <h2>Summary</h2>
        <table class="cards">
            <tr>
                <td class="card"><div class="label">Total ports</div><div class="value">{{ $summary['total_ports'] ?? count($ports) }}</div></td>
                <td class="card"><div class="label">Open</div><div class="value">{{ $summary['open_ports'] ?? '—' }}</div></td>
                <td class="card"><div class="label">Closed</div><div class="value">{{ $summary['closed_ports'] ?? '—' }}</div></td>
                <td class="card"><div class="label">Risky open</div><div class="value">{{ $summary['risky_open_ports'] ?? '—' }}</div></td>
            </tr>
        </table>
        <div class="meta-line" style="margin-top:8px;">
            Mode: <strong>{{ $report['mode'] ?? '—' }}</strong>
            · Edge: <strong>{{ $edge['name'] ?? 'Unknown' }}</strong>
            @if (!empty($report['scanned_at'])) · Scanned: {{ $report['scanned_at'] }} @endif
        </div>
    </div>

    @if (is_array($findings) && count($findings) > 0)
        <div class="section">
            <h2>Findings</h2>
            @foreach ($findings as $finding)
                @php $status = strtolower((string) ($finding['status'] ?? 'info')); @endphp
                <div class="finding {{ $status }}">
                    <div class="finding-title">
                        {{ $finding['title'] ?? 'Finding' }}
                        <span class="badge badge-{{ $status }}">{{ $status }}</span>
                    </div>
                    <div>{{ $finding['explanation'] ?? '' }}</div>
                    @if (!empty($finding['suggestion']))
                        <div class="muted" style="margin-top:4px;"><strong>Suggestion:</strong> {{ $finding['suggestion'] }}</div>
                    @endif
                </div>
            @endforeach
        </div>
    @endif

    <div class="section">
        <h2>Port results</h2>
        <table class="data">
            <thead>
                <tr>
                    <th>Port</th>
                    <th>State</th>
                    <th>Latency</th>
                    <th>Service</th>
                    <th>Risk</th>
                    <th>Banner / Error</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($ports as $port)
                    <tr>
                        <td class="mono">{{ $port['port'] ?? '—' }}</td>
                        <td><span class="badge badge-{{ strtolower((string) ($port['state'] ?? 'info')) }}">{{ $port['state'] ?? '—' }}</span></td>
                        <td>{{ isset($port['latency_ms']) ? number_format((float) $port['latency_ms'], 1).' ms' : '—' }}</td>
                        <td>{{ $port['service_hint'] ?? '—' }}</td>
                        <td>{{ $port['risk'] ?? '—' }}</td>
                        <td class="truncate mono">{{ $port['banner'] ?? $port['error'] ?? '—' }}</td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="muted">No port results.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
@endsection
