<?php

namespace App\Services\Mail;

use App\Models\Organization;
use App\Models\SystemSetting;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\View;
use Throwable;

class ManagementEmailService
{
    private const MANAGEMENT_EMAIL_SETTINGS_KEY = 'management_email_notifications';
    private const MAIN_SMTP_SETTINGS_KEY = 'main_smtp_settings';

    public function sendWelcomeUserEmail(
        User $recipient,
        Organization $organization,
        User $actor,
        string $plainPassword,
    ): void {
        if (! $this->isManagementEmailEnabled('notify_user_created', true)) {
            return;
        }

        if (! filter_var($recipient->email, FILTER_VALIDATE_EMAIL)) {
            return;
        }

        $appName = $this->appName();
        $loginUrl = $this->dashboardLoginUrl();
        $subject = sprintf('[%s] Your account is ready', $appName);

        $html = View::make('emails.welcome-user', [
            'appName' => $appName,
            'recipientName' => $recipient->name,
            'organizationName' => $organization->name,
            'actorName' => $actor->name,
            'actorEmail' => $actor->email,
            'email' => $recipient->email,
            'plainPassword' => $plainPassword,
            'loginUrl' => $loginUrl,
        ])->render();

        $text = implode("\n", array_filter([
            sprintf('Hello %s,', $recipient->name),
            '',
            sprintf('Your %s account is ready.', $appName),
            sprintf('Organization: %s', $organization->name),
            sprintf('Created by: %s (%s)', $actor->name, $actor->email),
            '',
            'Sign-in details:',
            sprintf('Email: %s', $recipient->email),
            sprintf('Temporary password: %s', $plainPassword),
            $loginUrl !== '' ? sprintf('Sign in: %s', $loginUrl) : null,
            '',
            'Change your password after your first sign-in.',
            '',
            'If this was unexpected, contact your administrator.',
        ]));

        $this->send($recipient->email, $subject, $html, $text);
    }

    public function sendPasswordResetEmail(User $user, string $token): void
    {
        if (! filter_var($user->email, FILTER_VALIDATE_EMAIL)) {
            return;
        }

        $appName = $this->appName();
        $resetUrl = $this->passwordResetUrl($user->email, $token);
        $subject = sprintf('[%s] Reset your password', $appName);

        $html = View::make('emails.reset-password', [
            'appName' => $appName,
            'recipientName' => $user->name,
            'resetUrl' => $resetUrl,
            'expiresMinutes' => (int) config('auth.passwords.users.expire', 60),
        ])->render();

        $text = implode("\n", array_filter([
            sprintf('Hello %s,', $user->name),
            '',
            sprintf('We received a request to reset your %s password.', $appName),
            $resetUrl !== '' ? sprintf('Reset your password: %s', $resetUrl) : null,
            '',
            sprintf('This link expires in %d minutes.', (int) config('auth.passwords.users.expire', 60)),
            '',
            'If you did not request a reset, you can ignore this email.',
        ]));

        $this->send($user->email, $subject, $html, $text);
    }

    public function sendOrganizationCreatedEmail(User $recipient, Organization $organization, User $actor): void
    {
        if (! $this->isManagementEmailEnabled('notify_organization_created', true)) {
            return;
        }

        if (! filter_var($recipient->email, FILTER_VALIDATE_EMAIL)) {
            return;
        }

        $appName = $this->appName();
        $dashboardUrl = $this->dashboardLoginUrl();
        $subject = sprintf('[%s] Organization created: %s', $appName, $organization->name);
        $bodyLines = [
            sprintf('Hello %s,', $recipient->name),
            '',
            sprintf('A new organization "%s" was created in %s.', $organization->name, $appName),
            sprintf('Created by: %s (%s)', $actor->name, $actor->email),
            sprintf('Organization ID: %s', $organization->id),
        ];

        if ($dashboardUrl !== '') {
            $bodyLines[] = sprintf('Dashboard: %s', $dashboardUrl);
        }

        $bodyLines[] = '';
        $bodyLines[] = 'If you were not expecting this change, contact your administrator.';

        $this->send($recipient->email, $subject, null, implode("\n", $bodyLines));
    }

    public function send(string $to, string $subject, ?string $html, string $text): void
    {
        try {
            $mailerName = (string) config('mail.default', 'smtp');
            $smtp = $this->mainSmtpSettingsRaw();

            if (($smtp['host'] ?? null) !== null && ($smtp['from_address'] ?? null) !== null) {
                $runtimeMailer = 'main_runtime_smtp';
                $password = null;

                if (! empty($smtp['password'])) {
                    try {
                        $password = Crypt::decryptString((string) $smtp['password']);
                    } catch (Throwable) {
                        $password = null;
                    }
                }

                config([
                    "mail.mailers.{$runtimeMailer}" => [
                        'transport' => 'smtp',
                        'host' => $smtp['host'],
                        'port' => $smtp['port'] ?? 587,
                        'username' => $smtp['username'] ?? null,
                        'password' => $password,
                        'encryption' => $smtp['encryption'] ?? null,
                        'timeout' => $smtp['timeout'] ?? null,
                        'local_domain' => $smtp['local_domain'] ?? null,
                    ],
                ]);

                $mailerName = $runtimeMailer;
            }

            Mail::mailer($mailerName)->send([], [], function ($message) use ($to, $subject, $html, $text, $smtp): void {
                $message->to($to)->subject($subject);

                if ($html !== null) {
                    $message->html($html);
                    $message->text($text);
                } else {
                    $message->text($text);
                }

                if (($smtp['from_address'] ?? null) !== null) {
                    $message->from((string) $smtp['from_address'], (string) ($smtp['from_name'] ?? $this->appName()));
                }

                if (($smtp['reply_to_address'] ?? null) !== null) {
                    $message->replyTo((string) $smtp['reply_to_address'], (string) ($smtp['reply_to_name'] ?? ''));
                }
            });
        } catch (Throwable) {
            // Notification delivery failures should not block management operations.
        }
    }

    public function dashboardLoginUrl(): string
    {
        $base = rtrim((string) config('vendorpulse.web_dashboard_url', ''), '/');

        return $base === '' ? '' : $base.'/web_dashboard/login';
    }

    public function passwordResetUrl(string $email, string $token): string
    {
        $base = rtrim((string) config('vendorpulse.web_dashboard_url', ''), '/');
        if ($base === '') {
            return '';
        }

        $query = http_build_query([
            'email' => $email,
            'token' => $token,
        ]);

        return $base.'/web_dashboard/reset-password?'.$query;
    }

    private function appName(): string
    {
        return (string) config('app.name', 'VendorPulse');
    }

    private function isManagementEmailEnabled(string $key, bool $default = true): bool
    {
        if (! $this->hasSystemSettingsTable()) {
            return $default;
        }

        try {
            $value = SystemSetting::query()
                ->where('key', self::MANAGEMENT_EMAIL_SETTINGS_KEY)
                ->value('value');
        } catch (QueryException) {
            return $default;
        }

        if (! is_array($value) || ! array_key_exists($key, $value)) {
            return $default;
        }

        return (bool) $value[$key];
    }

    /**
     * @return array<string, mixed>
     */
    private function mainSmtpSettingsRaw(): array
    {
        if (! $this->hasSystemSettingsTable()) {
            return [];
        }

        try {
            $value = SystemSetting::query()
                ->where('key', self::MAIN_SMTP_SETTINGS_KEY)
                ->value('value');
        } catch (QueryException) {
            return [];
        }

        return is_array($value) ? $value : [];
    }

    private function hasSystemSettingsTable(): bool
    {
        try {
            return Schema::hasTable('system_settings');
        } catch (QueryException) {
            return false;
        }
    }
}
