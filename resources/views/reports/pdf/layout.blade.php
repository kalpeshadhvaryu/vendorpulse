<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>{{ $meta['title'] ?? 'VendorPulse Report' }}</title>
    <style>
        @page { margin: 108px 34px 54px 34px; }
        body {
            font-family: DejaVu Sans, sans-serif;
            font-size: 10.5px;
            color: #1c1917;
            line-height: 1.5;
        }
        .header-fixed {
            position: fixed;
            top: -84px;
            left: 0;
            right: 0;
            height: 74px;
        }
        .header-bar {
            background: #0f2744;
            color: #fff;
            border-radius: 10px;
            padding: 11px 14px;
        }
        .header-table { width: 100%; border-collapse: collapse; }
        .header-table td { vertical-align: middle; }
        .logo {
            height: 52px;
            width: auto;
            max-width: 52px;
            display: block;
        }
        .logo-plate {
            background: #ffffff;
            border-radius: 8px;
            padding: 4px;
            display: inline-block;
            line-height: 0;
        }
        .mark {
            width: 40px;
            height: 40px;
            border-radius: 9px;
            background: #14b8a6;
            color: #fff;
            text-align: center;
            line-height: 40px;
            font-weight: bold;
            font-size: 13px;
            letter-spacing: -0.02em;
        }
        .brand { font-size: 17px; font-weight: bold; letter-spacing: 0.02em; color: #ffffff; }
        .brand-sub { font-size: 9px; color: #cbd5e1; margin-top: 2px; }
        .header-meta { text-align: right; font-size: 9px; color: #cbd5e1; line-height: 1.45; }

        .hero {
            border: 1px solid #e2e8f0;
            border-left: 5px solid #0f766e;
            background: #f8fafc;
            border-radius: 8px;
            padding: 14px 16px;
            margin-bottom: 14px;
        }
        .report-title {
            font-size: 18px;
            font-weight: bold;
            margin: 0 0 6px;
            color: #0f2744;
        }
        .meta-line { color: #57534e; font-size: 10px; margin-bottom: 3px; }
        .accent { color: #0f766e; }
        .pill {
            display: inline-block;
            background: #ccfbf1;
            color: #115e59;
            border-radius: 999px;
            padding: 2px 8px;
            font-size: 8px;
            font-weight: bold;
            text-transform: uppercase;
            margin-right: 4px;
        }

        .badge {
            display: inline-block;
            padding: 2px 7px;
            border-radius: 999px;
            font-size: 8px;
            font-weight: bold;
            text-transform: uppercase;
            letter-spacing: 0.03em;
        }
        .badge-ok, .badge-pass, .badge-success { background: #ccfbf1; color: #115e59; }
        .badge-fail, .badge-error, .badge-risk, .badge-danger { background: #fee2e2; color: #991b1b; }
        .badge-warn, .badge-warning { background: #ffedd5; color: #9a3412; }
        .badge-info, .badge-closed, .badge-filtered { background: #e2e8f0; color: #334155; }

        .section { margin-top: 16px; page-break-inside: avoid; }
        .section h2 {
            font-size: 11px;
            margin: 0 0 8px;
            padding: 0 0 5px;
            border-bottom: 2px solid #0f766e;
            color: #0f2744;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }

        .cards { width: 100%; border-collapse: separate; border-spacing: 7px; margin: 0 -7px; }
        .card {
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            padding: 11px 9px;
            text-align: center;
        }
        .card .label { font-size: 8px; color: #64748b; text-transform: uppercase; letter-spacing: 0.04em; }
        .card .value { font-size: 18px; font-weight: bold; color: #0f2744; margin-top: 4px; }
        .card.danger { background: #fef2f2; border-color: #fecaca; }
        .card.danger .value { color: #991b1b; }
        .card.warn { background: #fff7ed; border-color: #fed7aa; }
        .card.warn .value { color: #9a3412; }
        .card.ok { background: #f0fdfa; border-color: #99f6e4; }

        table.data { width: 100%; border-collapse: collapse; margin-top: 4px; }
        table.data th {
            background: #0f2744;
            color: #fff;
            text-align: left;
            padding: 7px 8px;
            font-size: 8px;
            text-transform: uppercase;
            letter-spacing: 0.04em;
        }
        table.data td {
            border-bottom: 1px solid #e7e5e4;
            padding: 7px 8px;
            vertical-align: top;
            font-size: 9.5px;
        }
        table.data tr:nth-child(even) td { background: #f8fafc; }

        .finding {
            border: 1px solid #e7e5e4;
            border-left: 4px solid #0f766e;
            border-radius: 6px;
            padding: 10px 12px;
            margin-bottom: 8px;
            page-break-inside: avoid;
            background: #fff;
        }
        .finding.fail { border-left-color: #dc2626; background: #fffbfb; }
        .finding.warning, .finding.warn { border-left-color: #ea580c; background: #fffaf5; }
        .finding.pass { border-left-color: #0f766e; }
        .finding-title { font-weight: bold; color: #0f2744; margin-bottom: 3px; }
        .finding-meta { margin-bottom: 4px; }
        .suggestion {
            margin-top: 6px;
            padding: 6px 8px;
            background: #f8fafc;
            border-radius: 4px;
            color: #334155;
            font-size: 9.5px;
        }
        .priority {
            border: 1px solid #fecaca;
            background: #fff7f7;
            border-radius: 8px;
            padding: 10px 12px;
            margin-bottom: 10px;
        }
        .priority-item {
            padding: 5px 0;
            border-bottom: 1px solid #fee2e2;
            font-size: 9.5px;
        }
        .priority-item:last-child { border-bottom: none; }
        .num {
            display: inline-block;
            width: 16px;
            height: 16px;
            line-height: 16px;
            text-align: center;
            border-radius: 50%;
            background: #dc2626;
            color: #fff;
            font-size: 8px;
            font-weight: bold;
            margin-right: 6px;
        }

        .muted { color: #78716c; }
        .mono { font-family: DejaVu Sans Mono, monospace; font-size: 8.5px; }
        .truncate { max-width: 280px; word-break: break-all; }

        .footer {
            position: fixed;
            bottom: -36px;
            left: 0;
            right: 0;
            font-size: 8px;
            color: #78716c;
            border-top: 1px solid #e7e5e4;
            padding-top: 6px;
        }
        .footer-table { width: 100%; border-collapse: collapse; }
        .footer-table td { font-size: 8px; color: #78716c; }
    </style>
</head>
<body>
    <div class="header-fixed">
        <div class="header-bar">
            <table class="header-table">
                <tr>
                    <td style="width:{{ !empty($meta['logo_data_uri']) || !empty($meta['logo_path']) ? '64' : '52' }}px;">
                        @if (!empty($meta['logo_data_uri']))
                            <div class="logo-plate">
                                <img class="logo" src="{{ $meta['logo_data_uri'] }}" alt="VendorPulse">
                            </div>
                        @elseif (!empty($meta['logo_path']))
                            <div class="logo-plate">
                                <img class="logo" src="{{ $meta['logo_path'] }}" alt="VendorPulse">
                            </div>
                        @else
                            <div class="mark">VP</div>
                        @endif
                    </td>
                    <td>
                        @if (!empty($meta['logo_is_wordmark']))
                            <div class="brand-sub" style="color:#e2e8f0;">Security · Web Health · Operations</div>
                            <div class="brand-sub" style="color:#94a3b8;margin-top:3px;">Authorized assessment report</div>
                        @else
                            <div class="brand">{{ $meta['app_name'] ?? 'VendorPulse' }}</div>
                            <div class="brand-sub">Security · Web Health · Operations</div>
                        @endif
                    </td>
                    <td class="header-meta">
                        Confidential<br>
                        {{ $meta['generated_at'] ?? '' }}
                    </td>
                </tr>
            </table>
        </div>
    </div>

    <div class="hero">
        <h1 class="report-title">{{ $meta['title'] ?? 'Report' }}</h1>
        @if (!empty($meta['subtitle']))
            <div class="meta-line">{{ $meta['subtitle'] }}</div>
        @endif
        @if (!empty($meta['target']))
            <div class="meta-line">Target: <strong class="accent mono">{{ $meta['target'] }}</strong></div>
        @endif
        @if (!empty($meta['organization']))
            <div class="meta-line">Organization: <strong>{{ $meta['organization'] }}</strong></div>
        @endif
        <div style="margin-top:8px;">
            <span class="pill">VendorPulse</span>
            <span class="pill">Assessment report</span>
        </div>
    </div>

    @yield('content')

    <div class="footer">
        <table class="footer-table">
            <tr>
                <td>Generated by {{ $meta['app_name'] ?? 'VendorPulse' }}@if (!empty($meta['organization'])) · {{ $meta['organization'] }}@endif</td>
                <td style="text-align:right;">Authorized use only · {{ $meta['generated_at'] ?? '' }}</td>
            </tr>
        </table>
    </div>
</body>
</html>
