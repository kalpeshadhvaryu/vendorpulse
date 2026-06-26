<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $appName ?? config('app.name', 'VendorPulse') }}</title>
</head>
<body style="margin:0;padding:0;background:#f4f4f5;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif;color:#18181b;">
<table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="background:#f4f4f5;padding:32px 16px;">
    <tr>
        <td align="center">
            <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="max-width:560px;background:#ffffff;border:1px solid #e4e4e7;border-radius:12px;overflow:hidden;">
                <tr>
                    <td style="padding:28px 28px 12px;text-align:center;">
                        <div style="display:inline-block;width:44px;height:44px;line-height:44px;border-radius:12px;background:#4f46e5;color:#ffffff;font-weight:700;font-size:16px;">VP</div>
                        <p style="margin:12px 0 0;font-size:18px;font-weight:600;">{{ $appName ?? config('app.name', 'VendorPulse') }}</p>
                    </td>
                </tr>
                <tr>
                    <td style="padding:8px 28px 28px;">
                        @yield('content')
                    </td>
                </tr>
                <tr>
                    <td style="padding:16px 28px 24px;border-top:1px solid #f4f4f5;font-size:12px;line-height:1.5;color:#71717a;text-align:center;">
                        Sent by {{ $appName ?? config('app.name', 'VendorPulse') }}. If you did not expect this email, contact your administrator.
                    </td>
                </tr>
            </table>
        </td>
    </tr>
</table>
</body>
</html>
