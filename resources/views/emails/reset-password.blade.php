@extends('emails.layout')

@section('content')
    <p style="margin:0 0 16px;font-size:15px;line-height:1.6;">Hello {{ $recipientName }},</p>
    <p style="margin:0 0 16px;font-size:15px;line-height:1.6;">
        We received a request to reset your <strong>{{ $appName }}</strong> password.
    </p>

    @if ($resetUrl !== '')
        <p style="margin:0 0 20px;text-align:center;">
            <a href="{{ $resetUrl }}" style="display:inline-block;background:#4f46e5;color:#ffffff;text-decoration:none;font-size:14px;font-weight:600;padding:12px 20px;border-radius:8px;">
                Reset password
            </a>
        </p>
        <p style="margin:0 0 16px;font-size:12px;line-height:1.5;color:#71717a;word-break:break-all;">
            Or copy this link: {{ $resetUrl }}
        </p>
    @endif

    <p style="margin:0;font-size:13px;line-height:1.6;color:#52525b;">
        This link expires in {{ $expiresMinutes }} minutes. If you did not request a password reset, you can safely ignore this email.
    </p>
@endsection
