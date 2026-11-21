@extends('reports.pdf.layout')

@section('content')
    @php
        $records = $report['records'] ?? $report['dns'] ?? [];
        $findings = $report['findings'] ?? $report['checks'] ?? [];
        $email = $report['email_security'] ?? $report['email'] ?? null;
        $summary = $report['summary'] ?? [];
    @endphp

    <div class="section">
        <h2>Summary</h2>
        <table class="cards">
            <tr>
                <td class="card"><div class="label">Records</div><div class="value">{{ $summary['total_records'] ?? (is_array($records) ? count($records) : '—') }}</div></td>
                <td class="card"><div class="label">Findings</div><div class="value">{{ is_array($findings) ? count($findings) : '—' }}</div></td>
                <td class="card"><div class="label">Email security</div><div class="value">{{ is_array($email) ? 'Included' : '—' }}</div></td>
            </tr>
        </table>
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
                    <div>{{ $finding['explanation'] ?? $finding['message'] ?? '' }}</div>
                    @if (!empty($finding['suggestion']))
                        <div class="muted" style="margin-top:4px;"><strong>Suggestion:</strong> {{ $finding['suggestion'] }}</div>
                    @endif
                </div>
            @endforeach
        </div>
    @endif

    @if (is_array($records) && count($records) > 0)
        <div class="section">
            <h2>DNS records</h2>
            <table class="data">
                <thead><tr><th>Type</th><th>Name</th><th>Value</th></tr></thead>
                <tbody>
                    @foreach (array_slice($records, 0, 100) as $row)
                        @if (isset($row['type']) || isset($row['name']))
                            <tr>
                                <td class="mono">{{ $row['type'] ?? '—' }}</td>
                                <td class="mono truncate">{{ $row['name'] ?? $row['host'] ?? '—' }}</td>
                                <td class="mono truncate">{{ $row['value'] ?? $row['content'] ?? $row['data'] ?? json_encode($row) }}</td>
                            </tr>
                        @else
                            @foreach ($row as $type => $values)
                                @if (is_array($values))
                                    @foreach ($values as $value)
                                        <tr>
                                            <td class="mono">{{ $type }}</td>
                                            <td class="mono">{{ $report['host'] ?? $report['target'] ?? '—' }}</td>
                                            <td class="mono truncate">{{ is_scalar($value) ? $value : json_encode($value) }}</td>
                                        </tr>
                                    @endforeach
                                @endif
                            @endforeach
                        @endif
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif

    @if (is_array($email))
        <div class="section">
            <h2>Email security</h2>
            <pre class="mono" style="white-space:pre-wrap;background:#f8fafc;border:1px solid #e2e8f0;padding:10px;border-radius:6px;">{{ json_encode($email, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) }}</pre>
        </div>
    @endif
@endsection
