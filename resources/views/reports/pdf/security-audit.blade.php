@extends('reports.pdf.layout')

@section('content')
    @php
        $summary = $report['summary'] ?? [];
        $findings = $report['findings'] ?? [];
        $categories = $report['categories'] ?? [];
        $failed = (int) ($summary['failed'] ?? 0);
        $warnings = (int) ($summary['warnings'] ?? 0);
        $passed = (int) ($summary['passed'] ?? 0);
        $severity = strtoupper((string) ($report['severity'] ?? '—'));
        $categoryLabels = [
            'security_misconfiguration' => 'Security misconfiguration',
            'broken_authentication' => 'Broken authentication signals',
            'csrf' => 'CSRF defenses',
            'sensitive_data_exposure' => 'Sensitive data exposure',
            'injection' => 'Injection surface',
            'xss_surface' => 'XSS surface',
        ];
        $failFindings = array_values(array_filter($findings, static fn ($f) => ($f['status'] ?? '') === 'fail'));
        $warnFindings = array_values(array_filter($findings, static fn ($f) => ($f['status'] ?? '') === 'warning'));
        $passFindings = array_values(array_filter($findings, static fn ($f) => ($f['status'] ?? '') === 'pass'));
        $priority = array_slice($failFindings, 0, 5);
        if (count($priority) < 5) {
            $priority = array_merge($priority, array_slice($warnFindings, 0, 5 - count($priority)));
        }
    @endphp

    <div class="section">
        <h2>Executive summary</h2>
        <table class="cards">
            <tr>
                <td class="card {{ in_array($severity, ['S0','S1'], true) ? 'danger' : ($severity === 'S2' ? 'warn' : 'ok') }}">
                    <div class="label">Severity</div>
                    <div class="value">{{ $severity }}</div>
                </td>
                <td class="card">
                    <div class="label">Risk score</div>
                    <div class="value">{{ $report['risk_score'] ?? '—' }}</div>
                </td>
                <td class="card danger">
                    <div class="label">Failed</div>
                    <div class="value">{{ $failed }}</div>
                </td>
                <td class="card warn">
                    <div class="label">Warnings</div>
                    <div class="value">{{ $warnings }}</div>
                </td>
                <td class="card ok">
                    <div class="label">Passed</div>
                    <div class="value">{{ $passed }}</div>
                </td>
            </tr>
        </table>
        <div class="meta-line" style="margin-top:6px;">
            Depth: <strong>{{ $report['depth'] ?? 'standard' }}</strong>
            · HTTP status: <strong>{{ $report['http_status'] ?? '—' }}</strong>
            · Host: <strong class="mono">{{ $report['host'] ?? '—' }}</strong>
        </div>
        @if (!empty($report['final_url']))
            <div class="meta-line">Final URL: <span class="mono">{{ $report['final_url'] }}</span></div>
        @endif
    </div>

    @if (count($priority) > 0)
        <div class="section">
            <h2>Priority remediation</h2>
            <div class="priority">
                @foreach ($priority as $i => $finding)
                    <div class="priority-item">
                        <span class="num">{{ $i + 1 }}</span>
                        <strong>{{ $finding['title'] ?? 'Finding' }}</strong>
                        <span class="badge badge-{{ ($finding['status'] ?? '') === 'fail' ? 'fail' : 'warning' }}">{{ $finding['status'] ?? '' }}</span>
                        @if (!empty($finding['suggestion']))
                            <div class="muted" style="margin:3px 0 0 22px;">{{ $finding['suggestion'] }}</div>
                        @endif
                    </div>
                @endforeach
            </div>
        </div>
    @endif

    @if (is_array($categories) && count($categories) > 0)
        <div class="section">
            <h2>Coverage by category</h2>
            <table class="data">
                <thead>
                    <tr>
                        <th style="width:42%;">Category</th>
                        <th>Pass</th>
                        <th>Warning</th>
                        <th>Fail</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($categories as $name => $counts)
                        @php
                            $f = (int) ($counts['fail'] ?? 0);
                            $w = (int) ($counts['warning'] ?? 0);
                            $status = $f > 0 ? 'fail' : ($w > 0 ? 'warning' : 'pass');
                        @endphp
                        <tr>
                            <td>{{ $categoryLabels[$name] ?? str_replace('_', ' ', (string) $name) }}</td>
                            <td>{{ $counts['pass'] ?? 0 }}</td>
                            <td>{{ $w }}</td>
                            <td>{{ $f }}</td>
                            <td><span class="badge badge-{{ $status }}">{{ $status }}</span></td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif

    @if (count($failFindings) > 0)
        <div class="section">
            <h2>Critical / failed findings</h2>
            @foreach ($failFindings as $finding)
                <div class="finding fail">
                    <div class="finding-meta">
                        <span class="badge badge-fail">{{ $finding['status'] ?? 'fail' }}</span>
                        <span class="badge badge-info">{{ $finding['severity'] ?? '—' }}</span>
                        <span class="muted">{{ $categoryLabels[$finding['category'] ?? ''] ?? str_replace('_', ' ', (string) ($finding['category'] ?? '')) }}</span>
                    </div>
                    <div class="finding-title">{{ $finding['title'] ?? 'Finding' }}</div>
                    <div>{{ $finding['explanation'] ?? '' }}</div>
                    @if (!empty($finding['suggestion']))
                        <div class="suggestion"><strong>Recommendation:</strong> {{ $finding['suggestion'] }}</div>
                    @endif
                </div>
            @endforeach
        </div>
    @endif

    @if (count($warnFindings) > 0)
        <div class="section">
            <h2>Warnings</h2>
            @foreach ($warnFindings as $finding)
                <div class="finding warning">
                    <div class="finding-meta">
                        <span class="badge badge-warning">warning</span>
                        <span class="badge badge-info">{{ $finding['severity'] ?? '—' }}</span>
                        <span class="muted">{{ $categoryLabels[$finding['category'] ?? ''] ?? str_replace('_', ' ', (string) ($finding['category'] ?? '')) }}</span>
                    </div>
                    <div class="finding-title">{{ $finding['title'] ?? 'Finding' }}</div>
                    <div>{{ $finding['explanation'] ?? '' }}</div>
                    @if (!empty($finding['suggestion']))
                        <div class="suggestion"><strong>Recommendation:</strong> {{ $finding['suggestion'] }}</div>
                    @endif
                </div>
            @endforeach
        </div>
    @endif

    @if (count($passFindings) > 0)
        <div class="section">
            <h2>Passed checks</h2>
            <table class="data">
                <thead>
                    <tr>
                        <th>Check</th>
                        <th>Category</th>
                        <th>Notes</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($passFindings as $finding)
                        <tr>
                            <td>{{ $finding['title'] ?? 'Check' }}</td>
                            <td>{{ $categoryLabels[$finding['category'] ?? ''] ?? str_replace('_', ' ', (string) ($finding['category'] ?? '')) }}</td>
                            <td>{{ \Illuminate\Support\Str::limit((string) ($finding['explanation'] ?? ''), 120) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif

    @if (!empty($report['scope_note']))
        <div class="section">
            <h2>Scope</h2>
            <div class="muted">{{ $report['scope_note'] }}</div>
        </div>
    @endif
@endsection
