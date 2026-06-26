@extends('emails.layout')

@section('content')
    <p style="margin:0 0 16px;font-size:15px;line-height:1.6;">Hello {{ $recipientName }},</p>
    <p style="margin:0 0 16px;font-size:15px;line-height:1.6;">
        Your <strong>{{ $appName }}</strong> account has been created for <strong>{{ $organizationName }}</strong>.
    </p>
    <p style="margin:0 0 16px;font-size:14px;line-height:1.6;color:#52525b;">
        Created by {{ $actorName }} ({{ $actorEmail }}).
    </p>

    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="margin:20px 0;background:#fafafa;border:1px solid #e4e4e7;border-radius:10px;">
        <tr>
            <td style="padding:18px 20px;">
                <p style="margin:0 0 10px;font-size:13px;font-weight:600;color:#3f3f46;text-transform:uppercase;letter-spacing:0.04em;">Sign-in details</p>
                <p style="margin:0 0 8px;font-size:14px;line-height:1.5;"><strong>Email:</strong> {{ $email }}</p>
                <p style="margin:0;font-size:14px;line-height:1.5;"><strong>Temporary password:</strong> <code style="background:#eef2ff;color:#312e81;padding:2px 6px;border-radius:4px;">{{ $plainPassword }}</code></p>
            </td>
        </tr>
    </table>

    @if ($loginUrl !== '')
        <p style="margin:0 0 20px;text-align:center;">
            <a href="{{ $loginUrl }}" style="display:inline-block;background:#4f46e5;color:#ffffff;text-decoration:none;font-size:14px;font-weight:600;padding:12px 20px;border-radius:8px;">
                Sign in to {{ $appName }}
            </a>
        </p>
        <p style="margin:0;font-size:12px;line-height:1.5;color:#71717a;word-break:break-all;">
            Or copy this link: {{ $loginUrl }}
        </p>
    @endif

    <p style="margin:20px 0 0;font-size:13px;line-height:1.6;color:#52525b;">
        For security, change your password after your first sign-in.
    </p>
@endsection
