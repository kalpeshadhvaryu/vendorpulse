@extends('reports.pdf.layout')

@section('content')
    @php
        $totals = $report['totals'] ?? [];
        $passed = $report['passed_audits'] ?? [];
        $warnings = $report['warnings'] ?? [];
        $critical = $report['critical_fixes'] ?? [];
        $extracted = $report['extracted_values'] ?? [];
    @endphp

    <div class="section">
        <h2>Score overview</h2>
        <table class="cards">
            <tr>
                <td class="card"><div class="label">Overall score</div><div class="value">{{ $report['overall_score'] ?? '—' }}</div></td>
                <td class="card"><div class="label">Passed</div><div class="value">{{ $totals['passed'] ?? count($passed) }}</div></td>
                <td class="card"><div class="label">Warnings</div><div class="value">{{ $totals['warnings'] ?? count($warnings) }}</div></td>
                <td class="card"><div class="label">Critical</div><div class="value">{{ $totals['critical'] ?? count($critical) }}</div></td>
            </tr>
        </table>
        <div class="meta-line" style="margin-top:8px;">Mode: <strong>{{ $report['mode'] ?? 'quick' }}</strong></div>
    </div>

    @if (!empty($extracted))
        <div class="section">
            <h2>Extracted values</h2>
            <table class="data">
                <thead><tr><th>Field</th><th>Value</th></tr></thead>
                <tbody>
                    <tr><td>Title</td><td>{{ $extracted['title'] ?? '—' }}</td></tr>
                    <tr><td>Meta description</td><td>{{ $extracted['meta_description'] ?? '—' }}</td></tr>
                    <tr><td>Canonical</td><td class="mono truncate">{{ $extracted['canonical'] ?? '—' }}</td></tr>
                    <tr><td>Robots</td><td class="mono">{{ $extracted['robots'] ?? '—' }}</td></tr>
                </tbody>
            </table>
        </div>
    @endif

    @foreach ([
        'Critical fixes' => $critical,
        'Warnings' => $warnings,
        'Passed audits' => $passed,
    ] as $heading => $items)
        @if (is_array($items) && count($items) > 0)
            <div class="section">
                <h2>{{ $heading }}</h2>
                <table class="data">
                    <thead><tr><th>Check</th><th>Detail</th></tr></thead>
                    <tbody>
                        @foreach (array_slice($items, 0, 50) as $item)
                            <tr>
                                <td>{{ $item['title'] ?? $item['name'] ?? $item['key'] ?? 'Item' }}</td>
                                <td class="truncate">{{ $item['message'] ?? $item['detail'] ?? $item['suggestion'] ?? '' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    @endforeach
@endsection
