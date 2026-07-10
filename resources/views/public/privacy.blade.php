<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Privacy Policy — VendorPulse</title>
    <meta name="description" content="Privacy Policy for VendorPulse by VeravalOnline Private Limited.">
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

        .brand { font-size: 1.125rem; font-weight: 700; letter-spacing: -0.02em; }
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

        .btn-outline {
            background: #fff;
            border-color: var(--border);
            color: var(--text);
        }

        .btn-outline:hover { background: #f8fafc; text-decoration: none; }

        .card {
            background: var(--card);
            border: 1px solid var(--border);
            border-radius: 14px;
            box-shadow: 0 10px 30px rgba(15, 23, 42, 0.06);
            padding: 28px 24px;
        }

        h1 {
            margin: 0 0 8px;
            font-size: clamp(1.75rem, 4vw, 2.15rem);
            letter-spacing: -0.03em;
        }

        .meta {
            margin: 0 0 28px;
            color: var(--muted);
            font-size: 0.875rem;
        }

        h2 {
            margin: 28px 0 10px;
            font-size: 1.125rem;
        }

        p, li { color: #334155; font-size: 0.9375rem; }
        p { margin: 0 0 12px; }
        ul { margin: 0 0 12px; padding-left: 1.25rem; }
        li { margin-bottom: 6px; }

        footer {
            margin-top: 40px;
            text-align: center;
            font-size: 0.8125rem;
            color: var(--muted);
        }
    </style>
</head>
<body>
    <div class="wrap">
        <header>
            <a class="brand" href="/">Vendor<span>Pulse</span></a>
            <nav class="nav">
                <a class="btn btn-outline" href="/privacy">Privacy Policy</a>
                <a class="btn btn-outline" href="{{ $signUpUrl }}">Sign Up</a>
                <a class="btn btn-outline" href="{{ $dashboardLoginUrl }}">Sign in</a>
                <a class="btn btn-primary" href="{{ $dashboardUrl }}">Open dashboard</a>
            </nav>
        </header>

        <article class="card">
            <h1>Privacy Policy</h1>
            <p class="meta">Last updated: July 10, 2026 · VendorPulse by VeravalOnline Private Limited</p>

            <p>
                This Privacy Policy describes how VeravalOnline Private Limited (“we”, “us”, or “our”)
                collects, uses, and shares information when you use VendorPulse, including our website,
                web dashboard, mobile applications, and related APIs (the “Services”).
            </p>

            <h2>1. Information we collect</h2>
            <p>Depending on how you use the Services, we may collect:</p>
            <ul>
                <li><strong>Account information</strong> — name, email address, password, organization name, and timezone.</li>
                <li><strong>Service data</strong> — vendor records, invoices, monitoring configurations, scan results, and related operational content you submit or generate in the product.</li>
                <li><strong>Device and usage data</strong> — IP address, browser or app type, device identifiers, approximate location derived from IP, and logs of how you interact with the Services.</li>
                <li><strong>Support communications</strong> — messages you send to us by email or other channels.</li>
            </ul>

            <h2>2. How we use information</h2>
            <p>We use information to:</p>
            <ul>
                <li>Provide, operate, and improve the Services (including authentication, monitoring, and alerts).</li>
                <li>Communicate with you about your account, security notices, and product updates.</li>
                <li>Prevent abuse, enforce our terms, and protect the security of our systems and users.</li>
                <li>Comply with legal obligations and respond to lawful requests.</li>
            </ul>

            <h2>3. Mobile app and API</h2>
            <p>
                When you use the VendorPulse mobile app, it communicates with our API to authenticate
                you and sync account and operational data. Credentials and tokens are used only to
                provide the Services. We do not sell personal information.
            </p>

            <h2>4. Public tools</h2>
            <p>
                Our public DNS and related lookup tools may process the domain or target you submit,
                along with technical request metadata (such as IP address) for rate limiting and abuse prevention.
                Do not submit private or unauthorized targets.
            </p>

            <h2>5. Sharing of information</h2>
            <p>We may share information with:</p>
            <ul>
                <li>Service providers who help us host, operate, or support the Services, under appropriate confidentiality obligations.</li>
                <li>Professional advisors, or authorities, when required by law or to protect our rights and users.</li>
                <li>A successor entity in connection with a merger, acquisition, or similar corporate transaction.</li>
            </ul>

            <h2>6. Data retention</h2>
            <p>
                We retain account and service data for as long as your account is active or as needed
                to provide the Services, meet legal requirements, resolve disputes, and enforce agreements.
                You may request deletion of your account by contacting us.
            </p>

            <h2>7. Security</h2>
            <p>
                We implement administrative, technical, and organizational measures designed to protect
                personal information. No method of transmission or storage is completely secure;
                please use strong passwords and keep your credentials confidential.
            </p>

            <h2>8. Your choices</h2>
            <ul>
                <li>You may update account details through the dashboard where available.</li>
                <li>You may request access, correction, or deletion of personal information by contacting us.</li>
                <li>You may opt out of non-essential marketing emails by following unsubscribe instructions or contacting us.</li>
            </ul>

            <h2>9. Children’s privacy</h2>
            <p>
                The Services are not directed to children under 16, and we do not knowingly collect
                personal information from children.
            </p>

            <h2>10. International transfers</h2>
            <p>
                Information may be processed in India or other countries where we or our service providers
                operate. By using the Services, you understand that your information may be transferred
                to and processed in those locations.
            </p>

            <h2>11. Changes to this policy</h2>
            <p>
                We may update this Privacy Policy from time to time. We will post the revised version
                on this page and update the “Last updated” date. Continued use of the Services after
                changes become effective constitutes acceptance of the updated policy.
            </p>

            <h2>12. Contact us</h2>
            <p>
                For privacy questions or requests, contact VeravalOnline Private Limited:
            </p>
            <ul>
                <li>Email: <a href="mailto:sales@veravalonline.com">sales@veravalonline.com</a></li>
                <li>Phone: <a href="tel:+917863042495">+91-7863042495</a></li>
            </ul>
        </article>

        <footer>
            Powered by VendorPulse ·
            <a href="/">Home</a> ·
            <a href="/privacy">Privacy Policy</a> ·
            <a href="{{ $dashboardUrl }}">web dashboard</a>
        </footer>
    </div>
</body>
</html>
