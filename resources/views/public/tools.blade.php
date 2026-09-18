<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>VendorPulse — Free DNS, Email &amp; WHOIS Check</title>
    <meta name="description" content="Free DNS, MX, SPF, DMARC, and WHOIS/RDAP lookup for any public domain. Powered by VendorPulse.">
    <link rel="icon" type="image/png" href="/favicon.png">
    <link rel="apple-touch-icon" href="/vendorpulse-logo.png">
    <style>
        :root {
            color-scheme: light;
            --bg: #f8fafc;
            --card: #ffffff;
            --text: #0f172a;
            --muted: #64748b;
            --border: #e2e8f0;
            --primary: #4f46e5;
            --primary-hover: #4338ca;
            --pass: #059669;
            --warn: #d97706;
            --fail: #dc2626;
        }

        * { box-sizing: border-box; }

        body {
            margin: 0;
            font-family: ui-sans-serif, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
            background: linear-gradient(180deg, #eef2ff 0%, var(--bg) 220px);
            color: var(--text);
            line-height: 1.5;
        }

        a { color: var(--primary); text-decoration: none; }
        a:hover { text-decoration: underline; }

        .wrap { max-width: 960px; margin: 0 auto; padding: 24px 20px 64px; }

        header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 16px;
            margin-bottom: 40px;
        }

        .brand {
            display: inline-flex;
            align-items: center;
            gap: 12px;
            font-size: 1.125rem;
            font-weight: 700;
            letter-spacing: -0.02em;
            color: var(--text);
            text-decoration: none;
        }
        .brand:hover { text-decoration: none; }
        .brand img {
            width: auto;
            height: 56px;
            padding: 0;
            background: transparent;
            border-radius: 0;
            display: block;
            object-fit: contain;
        }
        .brand-text { display: none; }
        .brand span { color: var(--primary); }

        .nav { display: flex; gap: 10px; flex-wrap: wrap; }

        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            border-radius: 8px;
            border: 1px solid transparent;
            padding: 10px 16px;
            font-size: 0.875rem;
            font-weight: 600;
            cursor: pointer;
            text-decoration: none;
        }

        .btn-primary { background: var(--primary); color: #fff; }
        .btn-primary:hover { background: var(--primary-hover); text-decoration: none; }
        .btn-primary:disabled { opacity: 0.6; cursor: not-allowed; }

        .btn-outline {
            background: #fff;
            border-color: var(--border);
            color: var(--text);
        }

        .btn-outline:hover { background: #f8fafc; text-decoration: none; }

        .hero { text-align: center; margin-bottom: 28px; }
        .hero h1 {
            margin: 0 0 10px;
            font-size: clamp(1.75rem, 4vw, 2.35rem);
            letter-spacing: -0.03em;
        }
        .hero p { margin: 0; color: var(--muted); max-width: 620px; margin-inline: auto; }

        .card {
            background: var(--card);
            border: 1px solid var(--border);
            border-radius: 14px;
            box-shadow: 0 10px 30px rgba(15, 23, 42, 0.06);
            padding: 20px;
        }

        .scan-form {
            display: grid;
            grid-template-columns: 1fr auto auto;
            gap: 12px;
        }

        .btn-secondary {
            background: #fff;
            border: 1px solid var(--border);
            color: var(--text);
        }
        .btn-secondary:hover { background: #f8fafc; text-decoration: none; }
        .btn-secondary:disabled { opacity: 0.6; cursor: not-allowed; }

        .whois-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 12px;
            margin-top: 8px;
        }
        .whois-item {
            border: 1px solid var(--border);
            border-radius: 10px;
            padding: 12px 14px;
            background: #fafafa;
        }
        .whois-item dt {
            font-size: 0.75rem;
            color: var(--muted);
            text-transform: uppercase;
            letter-spacing: 0.04em;
            margin: 0 0 4px;
        }
        .whois-item dd {
            margin: 0;
            font-size: 0.9375rem;
            font-weight: 600;
            word-break: break-word;
        }
        .chip-list { display: flex; flex-wrap: wrap; gap: 6px; }
        .chip {
            display: inline-block;
            border-radius: 999px;
            padding: 2px 8px;
            font-size: 0.75rem;
            font-weight: 600;
            background: #eef2ff;
            color: var(--primary);
        }

        input[type="text"] {
            width: 100%;
            border: 1px solid var(--border);
            border-radius: 8px;
            padding: 12px 14px;
            font-size: 1rem;
        }

        input[type="text"]:focus {
            outline: 2px solid rgba(79, 70, 229, 0.25);
            border-color: var(--primary);
        }

        .note {
            margin-top: 14px;
            font-size: 0.8125rem;
            color: var(--muted);
        }

        .hidden { display: none !important; }

        .summary-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 12px;
            margin: 20px 0;
        }

        .stat {
            border: 1px solid var(--border);
            border-radius: 10px;
            padding: 14px;
            background: #fafafa;
        }

        .stat-label { font-size: 0.75rem; color: var(--muted); text-transform: uppercase; letter-spacing: 0.04em; }
        .stat-value { font-size: 1.5rem; font-weight: 700; margin-top: 4px; }

        .tabs { display: flex; gap: 8px; flex-wrap: wrap; margin-bottom: 16px; }
        .tab {
            border: 1px solid var(--border);
            background: #fff;
            border-radius: 999px;
            padding: 6px 14px;
            font-size: 0.8125rem;
            cursor: pointer;
        }
        .tab.active { background: #eef2ff; border-color: #c7d2fe; color: var(--primary); font-weight: 600; }

        .panel { display: none; }
        .panel.active { display: block; }

        table { width: 100%; border-collapse: collapse; font-size: 0.875rem; }
        th, td { text-align: left; padding: 10px 8px; border-bottom: 1px solid var(--border); vertical-align: top; }
        th { color: var(--muted); font-size: 0.75rem; text-transform: uppercase; letter-spacing: 0.04em; }

        .badge {
            display: inline-block;
            border-radius: 999px;
            padding: 2px 8px;
            font-size: 0.75rem;
            font-weight: 600;
        }
        .badge-pass { background: #d1fae5; color: var(--pass); }
        .badge-warning { background: #fef3c7; color: var(--warn); }
        .badge-fail { background: #fee2e2; color: var(--fail); }

        .finding {
            border: 1px solid var(--border);
            border-radius: 10px;
            padding: 12px 14px;
            margin-bottom: 10px;
            border-left: 4px solid var(--border);
            background: #fff;
        }
        .finding.status-pass { border-left-color: var(--pass); }
        .finding.status-warning { border-left-color: var(--warn); }
        .finding.status-fail { border-left-color: var(--fail); }
        .finding h3 { margin: 0 0 4px; font-size: 0.9375rem; }
        .finding p { margin: 0; font-size: 0.8125rem; color: var(--muted); }
        .finding-meta {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
            align-items: center;
            margin-bottom: 6px;
        }
        .finding-key {
            font-size: 0.6875rem;
            color: var(--muted);
            font-family: ui-monospace, SFMono-Regular, Menlo, monospace;
        }
        .finding-fix {
            margin-top: 10px;
            padding-top: 10px;
            border-top: 1px solid var(--border);
            font-size: 0.8125rem;
            color: #0f172a;
        }
        .evidence {
            margin: 10px 0 0;
            padding: 0;
            list-style: none;
        }
        .evidence li {
            font-size: 0.75rem;
            color: var(--muted);
            font-family: ui-monospace, SFMono-Regular, Menlo, monospace;
            padding: 4px 0;
            word-break: break-word;
            border-bottom: 1px dashed #f1f5f9;
        }
        .evidence li:last-child { border-bottom: none; }

        .filter-bar {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
            margin-bottom: 14px;
            align-items: center;
        }
        .filter-btn {
            border: 1px solid var(--border);
            background: #fff;
            border-radius: 999px;
            padding: 4px 12px;
            font-size: 0.75rem;
            cursor: pointer;
            color: var(--muted);
        }
        .filter-btn.active {
            background: #0f172a;
            border-color: #0f172a;
            color: #fff;
        }

        .section-title {
            font-size: 0.9rem;
            margin: 18px 0 8px;
            letter-spacing: -0.01em;
        }
        .section-title:first-child { margin-top: 0; }
        .empty-note { color: var(--muted); font-size: 0.875rem; margin: 0; }
        .mono {
            font-family: ui-monospace, SFMono-Regular, Menlo, monospace;
            font-size: 0.8rem;
            word-break: break-all;
        }
        .match-yes { color: var(--pass); font-weight: 600; }
        .match-no { color: var(--warn); font-weight: 600; }
        .kv-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 10px;
            margin-bottom: 12px;
        }
        .kv {
            border: 1px solid var(--border);
            border-radius: 10px;
            padding: 12px 14px;
            background: #fafafa;
        }
        .kv .label {
            font-size: 0.75rem;
            color: var(--muted);
            text-transform: uppercase;
            letter-spacing: 0.04em;
        }
        .kv .value {
            margin-top: 4px;
            font-size: 0.875rem;
            font-weight: 600;
            word-break: break-word;
        }

        .cta {
            margin-top: 28px;
            padding: 20px;
            border-radius: 12px;
            background: linear-gradient(135deg, #312e81, #4f46e5);
            color: #fff;
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            justify-content: space-between;
            gap: 16px;
        }
        .cta p { margin: 0; opacity: 0.92; max-width: 520px; }
        .cta .btn-outline { background: transparent; color: #fff; border-color: rgba(255,255,255,0.35); }
        .cta .btn-outline:hover { background: rgba(255,255,255,0.08); }

        .error-box {
            margin-top: 14px;
            padding: 12px 14px;
            border-radius: 8px;
            background: #fef2f2;
            color: #991b1b;
            font-size: 0.875rem;
        }

        footer {
            margin-top: 40px;
            text-align: center;
            font-size: 0.8125rem;
            color: var(--muted);
        }

        @media (max-width: 720px) {
            .scan-form { grid-template-columns: 1fr; }
            .summary-grid { grid-template-columns: repeat(2, 1fr); }
            .whois-grid { grid-template-columns: 1fr; }
            .kv-grid { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>
    <div class="wrap">
        <header>
            <a class="brand" href="/">
                <img src="/vendorpulse-logo.png" height="56" alt="VendorPulse">
                <span class="brand-text">Vendor<span>Pulse</span></span>
            </a>
            <nav class="nav">
                <a class="btn btn-outline" href="/privacy">Privacy Policy</a>
                <a class="btn btn-outline" href="{{ $signUpUrl }}">Sign Up</a>
                <a class="btn btn-outline" href="{{ $dashboardLoginUrl }}">Sign in</a>
                <a class="btn btn-primary" href="{{ $dashboardUrl }}">Open dashboard</a>
            </nav>
        </header>

        <section class="hero">
            <h1>Free DNS, Email &amp; WHOIS Check</h1>
            <p>
                Look up MX records, SPF, DMARC, DNS health, and domain registration (WHOIS/RDAP) for any public domain.
                For monitoring, vendors, and full security tools, sign in to the dashboard.
            </p>
        </section>

        <section class="card">
            <form id="scan-form" class="scan-form">
                <input
                    id="target-input"
                    type="text"
                    name="target"
                    placeholder="example.com"
                    autocomplete="off"
                    required
                >
                <button id="scan-button" class="btn btn-primary" type="submit">Check domain</button>
                <button id="whois-button" class="btn btn-secondary" type="button">Check WHOIS</button>
            </form>
            <p class="note">
                Public lookup only. Rate limited to protect the service. Do not scan private or unauthorized targets.
            </p>
            <div id="error-box" class="error-box hidden"></div>
        </section>

        <section id="results" class="hidden">
            <div class="summary-grid">
                <div class="stat">
                    <div class="stat-label">Host</div>
                    <div id="stat-host" class="stat-value" style="font-size:1rem;word-break:break-all;">—</div>
                </div>
                <div class="stat">
                    <div class="stat-label">Passed</div>
                    <div id="stat-passed" class="stat-value">0</div>
                </div>
                <div class="stat">
                    <div class="stat-label">Warnings</div>
                    <div id="stat-warnings" class="stat-value">0</div>
                </div>
                <div class="stat">
                    <div class="stat-label">Failed</div>
                    <div id="stat-failed" class="stat-value">0</div>
                </div>
            </div>

            <div class="card">
                <div class="tabs">
                    <button type="button" class="tab active" data-tab="findings">Findings</button>
                    <button type="button" class="tab" data-tab="mx">MX records</button>
                    <button type="button" class="tab" data-tab="email">Email auth</button>
                    <button type="button" class="tab" data-tab="records">DNS records</button>
                    <button type="button" class="tab" data-tab="nameservers">Nameservers</button>
                    <button type="button" class="tab" data-tab="propagation">Propagation</button>
                </div>

                <div id="panel-findings" class="panel active"></div>
                <div id="panel-mx" class="panel"></div>
                <div id="panel-email" class="panel"></div>
                <div id="panel-records" class="panel"></div>
                <div id="panel-nameservers" class="panel"></div>
                <div id="panel-propagation" class="panel"></div>
            </div>

            <div class="cta">
                <div>
                    <strong>Need ongoing monitoring?</strong>
                    <p>Track uptime, vendors, invoices, SEO audits, and security checks in VendorPulse.</p>
                </div>
                <a class="btn btn-outline" href="{{ $dashboardLoginUrl }}">Sign in for full access</a>
            </div>
        </section>

        <section id="whois-results" class="hidden" style="margin-top:20px;">
            <div class="card">
                <h2 style="margin:0 0 12px;font-size:1.125rem;">WHOIS / RDAP</h2>
                <p id="whois-domain" style="margin:0 0 16px;color:var(--muted);font-size:0.875rem;"></p>
                <dl class="whois-grid" id="whois-grid"></dl>
            </div>

            <div class="cta">
                <div>
                    <strong>Need expiry alerts?</strong>
                    <p>VendorPulse can watch domain and SSL expiry and notify your team automatically.</p>
                </div>
                <a class="btn btn-outline" href="{{ $dashboardLoginUrl }}">Sign in for full access</a>
            </div>
        </section>

        <footer>
            Powered by
            <a href="https://veravalonline.com" target="_blank" rel="noopener noreferrer"> VeravalOnline Private Limited</a>
            · Hosted by
            <a href="https://voguehosting.com" target="_blank" rel="noopener noreferrer"> Vogue Hosting</a>
            · Free public DNS &amp; WHOIS tool ·
            <a href="/privacy">Privacy Policy</a> ·
            <a href="{{ $signUpUrl }}">Sign Up</a> ·
            <a href="{{ $dashboardUrl }}">web dashboard</a>
        </footer>
    </div>

    <script>
        const form = document.getElementById('scan-form');
        const button = document.getElementById('scan-button');
        const whoisButton = document.getElementById('whois-button');
        const errorBox = document.getElementById('error-box');
        const results = document.getElementById('results');
        const whoisResults = document.getElementById('whois-results');

        function badge(status) {
            if (status === 'pass') return '<span class="badge badge-pass">Pass</span>';
            if (status === 'warning') return '<span class="badge badge-warning">Warning</span>';
            return '<span class="badge badge-fail">Fail</span>';
        }

        function escapeHtml(value) {
            return String(value ?? '')
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;');
        }

        function formatDate(iso) {
            if (!iso) return '—';
            const d = new Date(iso);
            if (Number.isNaN(d.getTime())) return escapeHtml(iso);
            return escapeHtml(d.toUTCString());
        }

        function emptyNote(text) {
            return `<p class="empty-note">${escapeHtml(text)}</p>`;
        }

        function recordTable(headers, rowsHtml) {
            return `
                <table>
                    <thead><tr>${headers.map((h) => `<th>${escapeHtml(h)}</th>`).join('')}</tr></thead>
                    <tbody>${rowsHtml}</tbody>
                </table>
            `;
        }

        function stringRows(values) {
            if (!values || !values.length) return emptyNote('None');
            return recordTable(['Value'], values.map((v) => `
                <tr><td class="mono">${escapeHtml(typeof v === 'string' ? v : JSON.stringify(v))}</td></tr>
            `).join(''));
        }

        function parseMx(entry) {
            if (typeof entry === 'object' && entry) {
                return {
                    priority: Number(entry.pri ?? entry.priority ?? 0),
                    host: String(entry.target ?? entry.host ?? entry.exchange ?? JSON.stringify(entry)),
                };
            }
            const text = String(entry);
            const match = text.match(/^(.*?)\s*\(priority\s+(\d+)\)\s*$/i);
            if (match) {
                return { host: match[1].trim(), priority: Number(match[2]) };
            }
            return { host: text, priority: null };
        }

        function extractSpf(txtRecords) {
            return (txtRecords || []).filter((t) => /^v=spf1\b/i.test(String(t)));
        }

        let cachedChecks = [];

        function statusRank(status) {
            if (status === 'fail') return 0;
            if (status === 'warning') return 1;
            return 2;
        }

        function renderFindings(checks, filter = 'all') {
            const panel = document.getElementById('panel-findings');
            cachedChecks = Array.isArray(checks) ? checks.slice() : [];
            const sorted = cachedChecks.slice().sort((a, b) => statusRank(a.status) - statusRank(b.status));
            const filtered = filter === 'all' ? sorted : sorted.filter((c) => c.status === filter);

            const counts = {
                all: cachedChecks.length,
                fail: cachedChecks.filter((c) => c.status === 'fail').length,
                warning: cachedChecks.filter((c) => c.status === 'warning').length,
                pass: cachedChecks.filter((c) => c.status === 'pass').length,
            };

            const filterBar = `
                <div class="filter-bar" id="findings-filter">
                    ${['all', 'fail', 'warning', 'pass'].map((key) => `
                        <button type="button" class="filter-btn ${filter === key ? 'active' : ''}" data-filter="${key}">
                            ${key === 'all' ? 'All' : key.charAt(0).toUpperCase() + key.slice(1)} (${counts[key]})
                        </button>
                    `).join('')}
                </div>
            `;

            if (!filtered.length) {
                panel.innerHTML = filterBar + emptyNote('No findings for this filter.');
            } else {
                panel.innerHTML = filterBar + filtered.map((item) => `
                    <article class="finding status-${escapeHtml(item.status || 'fail')}">
                        <div class="finding-meta">
                            ${badge(item.status)}
                            ${item.severity ? `<span class="chip">${escapeHtml(item.severity)}</span>` : ''}
                            ${item.key ? `<span class="finding-key">${escapeHtml(item.key)}</span>` : ''}
                        </div>
                        <h3>${escapeHtml(item.title || 'Finding')}</h3>
                        <p>${escapeHtml(item.explanation || '')}</p>
                        ${item.suggestion ? `<div class="finding-fix"><strong>Fix:</strong> ${escapeHtml(item.suggestion)}</div>` : ''}
                        ${(item.evidence || []).length ? `
                            <ul class="evidence">
                                ${item.evidence.map((line) => `<li>${escapeHtml(line)}</li>`).join('')}
                            </ul>
                        ` : ''}
                    </article>
                `).join('');
            }

            panel.querySelectorAll('.filter-btn').forEach((btn) => {
                btn.addEventListener('click', () => renderFindings(cachedChecks, btn.dataset.filter));
            });
        }

        function renderMx(records) {
            const panel = document.getElementById('panel-mx');
            const mx = (records.mx || []).map(parseMx)
                .sort((a, b) => {
                    if (a.priority === null && b.priority === null) return a.host.localeCompare(b.host);
                    if (a.priority === null) return 1;
                    if (b.priority === null) return -1;
                    return a.priority - b.priority;
                });

            if (!mx.length) {
                panel.innerHTML = emptyNote('No MX records found. This may be fine if the domain does not receive email.');
                return;
            }

            panel.innerHTML = `
                <p class="empty-note" style="margin-bottom:12px;">
                    Lower priority number = preferred mail server. ${mx.length} record${mx.length === 1 ? '' : 's'} found.
                </p>
                ${recordTable(['Priority', 'Mail server'], mx.map((row, index) => `
                    <tr>
                        <td>${row.priority === null ? '—' : escapeHtml(String(row.priority))}</td>
                        <td class="mono">${escapeHtml(row.host)}${index === 0 && row.priority !== null ? ' <span class="chip">primary</span>' : ''}</td>
                    </tr>
                `).join(''))}
            `;
        }

        function renderEmail(records) {
            const panel = document.getElementById('panel-email');
            const spf = extractSpf(records.txt || []);
            const dmarc = records.dmarc || [];
            const otherTxt = (records.txt || []).filter((t) => !/^v=spf1\b/i.test(String(t)));

            panel.innerHTML = `
                <h3 class="section-title">SPF</h3>
                ${spf.length ? stringRows(spf) : emptyNote('No SPF record found.')}
                <h3 class="section-title">DMARC</h3>
                ${dmarc.length ? stringRows(dmarc) : emptyNote('No DMARC record found at _dmarc.')}
                <h3 class="section-title">Other TXT records</h3>
                ${otherTxt.length ? stringRows(otherTxt) : emptyNote('No additional TXT records.')}
            `;
        }

        function renderRecords(records) {
            const panel = document.getElementById('panel-records');
            const sections = [
                { key: 'a', label: 'A (IPv4)' },
                { key: 'aaaa', label: 'AAAA (IPv6)' },
                { key: 'cname', label: 'CNAME' },
                { key: 'soa', label: 'SOA' },
                { key: 'caa', label: 'CAA' },
                { key: 'dnskey', label: 'DNSKEY (DNSSEC)' },
            ];

            const www = records.www || {};
            const wwwBlocks = [
                ['WWW A', www.a || []],
                ['WWW AAAA', www.aaaa || []],
                ['WWW CNAME', www.cname || []],
            ];

            panel.innerHTML = sections.map(({ key, label }) => `
                <h3 class="section-title">${escapeHtml(label)}</h3>
                ${stringRows(records[key] || [])}
            `).join('') + `
                <h3 class="section-title">WWW hostname</h3>
                ${wwwBlocks.map(([label, values]) => `
                    <h3 class="section-title" style="font-size:0.8rem;color:var(--muted);">${escapeHtml(label)}</h3>
                    ${stringRows(values)}
                `).join('')}
            `;
        }

        function renderNameservers(records) {
            const panel = document.getElementById('panel-nameservers');
            const zoneNs = records.ns || [];
            const parentNs = records.parent_delegation_ns || [];
            const hostRes = records.nameserver_host_resolution || {};

            const hostRows = Object.keys(hostRes).sort().map((host) => {
                const info = hostRes[host] || {};
                const ips = [...(info.a || []), ...(info.aaaa || [])];
                return `
                    <tr>
                        <td class="mono">${escapeHtml(host)}</td>
                        <td class="mono">${ips.length ? ips.map(escapeHtml).join('<br>') : '—'}</td>
                    </tr>
                `;
            }).join('');

            panel.innerHTML = `
                <h3 class="section-title">Zone NS</h3>
                ${stringRows(zoneNs)}
                <h3 class="section-title">Parent / registrar delegation NS</h3>
                ${stringRows(parentNs)}
                <h3 class="section-title">Nameserver host IPs</h3>
                ${hostRows
                    ? recordTable(['Nameserver', 'Resolved IPs'], hostRows)
                    : emptyNote('No nameserver host resolution data.')}
            `;
        }

        function renderPropagation(records, report) {
            const panel = document.getElementById('panel-propagation');
            const consensus = records.resolver_consensus || {};
            const resolvers = report.resolver_nameservers || [];
            const match = (value) => value
                ? '<span class="match-yes">Match</span>'
                : '<span class="match-no">Mismatch</span>';

            panel.innerHTML = `
                <div class="kv-grid">
                    <div class="kv">
                        <div class="label">Scanned at</div>
                        <div class="value">${formatDate(report.scanned_at)}</div>
                    </div>
                    <div class="kv">
                        <div class="label">Local resolvers</div>
                        <div class="value mono">${resolvers.length ? resolvers.map(escapeHtml).join(', ') : '—'}</div>
                    </div>
                    <div class="kv">
                        <div class="label">Local vs Google</div>
                        <div class="value">${match(!!consensus.local_vs_google_match)}</div>
                    </div>
                    <div class="kv">
                        <div class="label">Local vs Cloudflare</div>
                        <div class="value">${match(!!consensus.local_vs_cloudflare_match)}</div>
                    </div>
                </div>
                <h3 class="section-title">Local A answers</h3>
                ${stringRows(consensus.local_a || [])}
                <h3 class="section-title">Google DNS A answers</h3>
                ${stringRows(consensus.google_a || [])}
                ${consensus.google_error ? `<p class="empty-note">Google error: ${escapeHtml(consensus.google_error)}</p>` : ''}
                <h3 class="section-title">Cloudflare DNS A answers</h3>
                ${stringRows(consensus.cloudflare_a || [])}
                ${consensus.cloudflare_error ? `<p class="empty-note">Cloudflare error: ${escapeHtml(consensus.cloudflare_error)}</p>` : ''}
            `;
        }

        function renderDnsReport(report) {
            const records = report.records || {};
            renderFindings(report.checks || []);
            renderMx(records);
            renderEmail(records);
            renderRecords(records);
            renderNameservers(records);
            renderPropagation(records, report);
        }

        function renderWhois(report) {
            const days = report.days_remaining;
            let daysLabel = '—';
            if (typeof days === 'number') {
                if (days < 0) daysLabel = Math.abs(days) + ' days ago (expired)';
                else daysLabel = days + ' days';
            }

            const statuses = (report.statuses || []).map((s) => `<span class="chip">${escapeHtml(s)}</span>`).join('') || '—';
            const nameservers = (report.nameservers || []).map((s) => `<span class="chip">${escapeHtml(s)}</span>`).join('') || '—';

            document.getElementById('whois-domain').textContent = 'Domain: ' + (report.domain || '—');
            document.getElementById('whois-grid').innerHTML = `
                <div class="whois-item"><dt>Registrar</dt><dd>${escapeHtml(report.registrar || '—')}</dd></div>
                <div class="whois-item"><dt>Expires</dt><dd>${formatDate(report.expires_at)}</dd></div>
                <div class="whois-item"><dt>Days remaining</dt><dd>${escapeHtml(daysLabel)}</dd></div>
                <div class="whois-item"><dt>Created</dt><dd>${formatDate(report.created_at)}</dd></div>
                <div class="whois-item"><dt>Updated</dt><dd>${formatDate(report.updated_at)}</dd></div>
                <div class="whois-item"><dt>Source</dt><dd>${escapeHtml(report.source || 'rdap')}</dd></div>
                <div class="whois-item" style="grid-column:1/-1;"><dt>Status</dt><dd class="chip-list">${statuses}</dd></div>
                <div class="whois-item" style="grid-column:1/-1;"><dt>Nameservers</dt><dd class="chip-list">${nameservers}</dd></div>
            `;
            whoisResults.classList.remove('hidden');
            whoisResults.scrollIntoView({ behavior: 'smooth', block: 'start' });
        }

        document.querySelectorAll('.tab').forEach((tab) => {
            tab.addEventListener('click', () => {
                document.querySelectorAll('.tab').forEach((el) => el.classList.remove('active'));
                document.querySelectorAll('.panel').forEach((el) => el.classList.remove('active'));
                tab.classList.add('active');
                document.getElementById(`panel-${tab.dataset.tab}`).classList.add('active');
            });
        });

        form.addEventListener('submit', async (event) => {
            event.preventDefault();
            const target = document.getElementById('target-input').value.trim();
            if (!target) return;

            button.disabled = true;
            whoisButton.disabled = true;
            button.textContent = 'Checking...';
            errorBox.classList.add('hidden');
            errorBox.textContent = '';
            whoisResults.classList.add('hidden');

            try {
                const response = await fetch('/api/v1/public/dns-check', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                    },
                    body: JSON.stringify({ target }),
                });

                const payload = await response.json();
                if (!response.ok || !payload.success) {
                    const validationMsg = payload.errors?.target?.[0];
                    throw new Error(validationMsg || payload.message || 'DNS check failed.');
                }

                const report = payload.data;
                document.getElementById('stat-host').textContent = report.host || report.target;
                document.getElementById('stat-passed').textContent = report.summary?.passed ?? 0;
                document.getElementById('stat-warnings').textContent = report.summary?.warnings ?? 0;
                document.getElementById('stat-failed').textContent = report.summary?.failed ?? 0;

                renderDnsReport(report);
                results.classList.remove('hidden');
                document.querySelectorAll('.tab').forEach((el) => el.classList.remove('active'));
                document.querySelectorAll('.panel').forEach((el) => el.classList.remove('active'));
                document.querySelector('.tab[data-tab="findings"]').classList.add('active');
                document.getElementById('panel-findings').classList.add('active');
                results.scrollIntoView({ behavior: 'smooth', block: 'start' });
            } catch (error) {
                errorBox.textContent = error instanceof Error ? error.message : 'Unexpected error.';
                errorBox.classList.remove('hidden');
            } finally {
                button.disabled = false;
                whoisButton.disabled = false;
                button.textContent = 'Check domain';
            }
        });

        whoisButton.addEventListener('click', async () => {
            const target = document.getElementById('target-input').value.trim();
            if (!target) {
                document.getElementById('target-input').focus();
                return;
            }

            button.disabled = true;
            whoisButton.disabled = true;
            whoisButton.textContent = 'Looking up...';
            errorBox.classList.add('hidden');
            errorBox.textContent = '';
            results.classList.add('hidden');

            try {
                const response = await fetch('/api/v1/public/whois-check', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                    },
                    body: JSON.stringify({ target }),
                });

                const payload = await response.json();
                if (!response.ok || !payload.success) {
                    const validationMsg = payload.errors?.target?.[0];
                    throw new Error(validationMsg || payload.message || 'WHOIS check failed.');
                }

                renderWhois(payload.data || {});
            } catch (error) {
                errorBox.textContent = error instanceof Error ? error.message : 'Unexpected error.';
                errorBox.classList.remove('hidden');
            } finally {
                button.disabled = false;
                whoisButton.disabled = false;
                whoisButton.textContent = 'Check WHOIS';
            }
        });
    </script>
</body>
</html>
