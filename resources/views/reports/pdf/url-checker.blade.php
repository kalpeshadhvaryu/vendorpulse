@extends('reports.pdf.layout')

@section('content')
    @php
        $summary = $report['summary'] ?? [];
        $links = $report['links'] ?? $report['results'] ?? [];
        $findings = $report['findings'] ?? [];
        $pages = $report['pages'] ?? [];
    @endphp

    <div class="section">
        <h2>Summary</h2>
        <table class="cards">
            <tr>
                <td class="card"><div class="label">Links checked</div><div class="value">{{ $summary['total_links'] ?? $summary['checked'] ?? count($links) }}</div></td>
                <td class="card"><div class="label">Broken / fail</div><div class="value">{{ $summary['broken'] ?? $summary['failed'] ?? $summary['errors'] ?? '—' }}</div></td>
                <td class="card"><div class="label">Warnings</div><div class="value">{{ $summary['warnings'] ?? '—' }}</div></td>
                <td class="card"><div class="label">OK</div><div class="value">{{ $summary['ok'] ?? $summary['passed'] ?? '—' }}</div></td>
            </tr>
        </table>
        @if (!empty($report['mode']))
            <div class="meta-line" style="margin-top:8px;">Mode: <strong>{{ $report['mode'] }}</strong></div>
        @endif
    </div>

    @if (is_array($findings) && count($findings) > 0)
        <div class="section">
            <h2>Findings</h2>
            @foreach (array_slice($findings, 0, 40) as $finding)
                @php $status = strtolower((string) ($finding['status'] ?? 'info')); @endphp
                <div class="finding {{ $status }}">
                    <div class="finding-title">
                        {{ $finding['title'] ?? ($finding['key'] ?? 'Finding') }}
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

    <div class="section">
        <h2>Link sample</h2>
        <table class="data">
            <thead>
                <tr>
                    <th>URL</th>
                    <th>Status</th>
                    <th>Code</th>
                    <th>Note</th>
                </tr>
            </thead>
            <tbody>
                @forelse (array_slice($links, 0, 80) as $link)
                    <tr>
                        <td class="truncate mono">{{ $link['url'] ?? $link['href'] ?? '—' }}</td>
                        <td>{{ $link['status'] ?? $link['state'] ?? '—' }}</td>
                        <td class="mono">{{ $link['status_code'] ?? $link['http_status'] ?? '—' }}</td>
                        <td class="truncate">{{ $link['error'] ?? $link['note'] ?? $link['message'] ?? '—' }}</td>
                    </tr>
                @empty
                    <tr><td colspan="4" class="muted">No link rows in this report.</td></tr>
                @endforelse
            </tbody>
        </table>
        @if (count($links) > 80)
            <div class="muted" style="margin-top:6px;">Showing first 80 of {{ count($links) }} links.</div>
        @endif
    </div>

    @if (is_array($pages) && count($pages) > 0)
        <div class="section">
            <h2>Pages crawled</h2>
            <table class="data">
                <thead><tr><th>Page</th><th>Status</th><th>Links</th></tr></thead>
                <tbody>
                    @foreach (array_slice($pages, 0, 40) as $page)
                        <tr>
                            <td class="truncate mono">{{ $page['url'] ?? '—' }}</td>
                            <td>{{ $page['status_code'] ?? $page['status'] ?? '—' }}</td>
                            <td>{{ $page['links_count'] ?? $page['link_count'] ?? '—' }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
@endsection
