<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\SystemSetting;
use App\Support\ApiResponse;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class SystemSettingsController extends BaseApiController
{
    private const MANAGEMENT_EMAIL_SETTINGS_KEY = 'management_email_notifications';
    private const MAIN_SMTP_SETTINGS_KEY = 'main_smtp_settings';

    /**
     * @return array{notify_organization_created: bool, notify_user_created: bool}
     */
    private function defaultManagementEmailSettings(): array
    {
        return [
            'notify_organization_created' => true,
            'notify_user_created' => true,
        ];
    }

    public function showManagementEmailNotifications(Request $request): JsonResponse
    {
        $user = $request->user();
        if (! $user || ! $user->isAdmin()) {
            return ApiResponse::error('Only admins can manage system settings.', Response::HTTP_FORBIDDEN);
        }

        $settings = $this->defaultManagementEmailSettings();

        if (! $this->hasSystemSettingsTable()) {
            return ApiResponse::success($settings);
        }

        $stored = $this->safeSystemSettingValue(self::MANAGEMENT_EMAIL_SETTINGS_KEY);

        if (is_array($stored)) {
            $settings['notify_organization_created'] = (bool) ($stored['notify_organization_created'] ?? $settings['notify_organization_created']);
            $settings['notify_user_created'] = (bool) ($stored['notify_user_created'] ?? $settings['notify_user_created']);
        }

        return ApiResponse::success($settings);
    }

    public function updateManagementEmailNotifications(Request $request): JsonResponse
    {
        $user = $request->user();
        if (! $user || ! $user->isAdmin()) {
            return ApiResponse::error('Only admins can manage system settings.', Response::HTTP_FORBIDDEN);
        }

        if (! $this->hasSystemSettingsTable()) {
            return ApiResponse::error('System settings storage is not ready. Run migrations first.', Response::HTTP_SERVICE_UNAVAILABLE);
        }

        $validated = $request->validate([
            'notify_organization_created' => ['sometimes', 'boolean'],
            'notify_user_created' => ['sometimes', 'boolean'],
        ]);

        $settings = $this->defaultManagementEmailSettings();
        $existing = SystemSetting::query()->where('key', self::MANAGEMENT_EMAIL_SETTINGS_KEY)->first();
        if (is_array($existing?->value)) {
            $settings['notify_organization_created'] = (bool) (($existing->value)['notify_organization_created'] ?? $settings['notify_organization_created']);
            $settings['notify_user_created'] = (bool) (($existing->value)['notify_user_created'] ?? $settings['notify_user_created']);
        }

        if (array_key_exists('notify_organization_created', $validated)) {
            $settings['notify_organization_created'] = (bool) $validated['notify_organization_created'];
        }
        if (array_key_exists('notify_user_created', $validated)) {
            $settings['notify_user_created'] = (bool) $validated['notify_user_created'];
        }

        SystemSetting::query()->updateOrCreate(
            ['key' => self::MANAGEMENT_EMAIL_SETTINGS_KEY],
            ['value' => $settings]
        );

        return ApiResponse::success($settings, 'Management email notification settings saved.');
    }

    public function showMainSmtpSettings(Request $request): JsonResponse
    {
        $user = $request->user();
        if (! $user || ! $user->isAdmin()) {
            return ApiResponse::error('Only admins can manage system settings.', Response::HTTP_FORBIDDEN);
        }

        return ApiResponse::success($this->sanitizeMainSmtpSettings($this->mainSmtpSettingsRaw()));
    }

    public function updateMainSmtpSettings(Request $request): JsonResponse
    {
        $user = $request->user();
        if (! $user || ! $user->isAdmin()) {
            return ApiResponse::error('Only admins can manage system settings.', Response::HTTP_FORBIDDEN);
        }

        if (! $this->hasSystemSettingsTable()) {
            return ApiResponse::error('System settings storage is not ready. Run migrations first.', Response::HTTP_SERVICE_UNAVAILABLE);
        }

        $validated = $request->validate([
            'host' => ['required', 'string', 'max:255'],
            'port' => ['required', 'integer', 'between:1,65535'],
            'username' => ['nullable', 'string', 'max:255'],
            'password' => ['nullable', 'string', 'max:255'],
            'encryption' => ['nullable', 'string', 'in:tls,ssl,none'],
            'from_address' => ['required', 'string', 'email', 'max:255'],
            'from_name' => ['required', 'string', 'max:255'],
            'reply_to_address' => ['nullable', 'string', 'email', 'max:255'],
            'reply_to_name' => ['nullable', 'string', 'max:255'],
            'timeout' => ['nullable', 'integer', 'between:1,120'],
            'local_domain' => ['nullable', 'string', 'max:255'],
        ]);

        $existing = $this->mainSmtpSettingsRaw();

        $passwordEncrypted = $existing['password'] ?? null;
        if (array_key_exists('password', $validated)) {
            $incomingPassword = (string) ($validated['password'] ?? '');
            if ($incomingPassword !== '') {
                $passwordEncrypted = Crypt::encryptString($incomingPassword);
            }
        }

        $settings = [
            'host' => trim((string) $validated['host']),
            'port' => (int) $validated['port'],
            'username' => isset($validated['username']) && $validated['username'] !== '' ? trim((string) $validated['username']) : null,
            'password' => $passwordEncrypted,
            'encryption' => ($validated['encryption'] ?? null) === 'none' ? null : ($validated['encryption'] ?? null),
            'from_address' => mb_strtolower(trim((string) $validated['from_address'])),
            'from_name' => trim((string) $validated['from_name']),
            'reply_to_address' => isset($validated['reply_to_address']) && $validated['reply_to_address'] !== ''
                ? mb_strtolower(trim((string) $validated['reply_to_address']))
                : null,
            'reply_to_name' => isset($validated['reply_to_name']) && $validated['reply_to_name'] !== ''
                ? trim((string) $validated['reply_to_name'])
                : null,
            'timeout' => isset($validated['timeout']) ? (int) $validated['timeout'] : null,
            'local_domain' => isset($validated['local_domain']) && $validated['local_domain'] !== ''
                ? trim((string) $validated['local_domain'])
                : null,
        ];

        SystemSetting::query()->updateOrCreate(
            ['key' => self::MAIN_SMTP_SETTINGS_KEY],
            ['value' => $settings]
        );

        return ApiResponse::success($this->sanitizeMainSmtpSettings($settings), 'Main SMTP settings saved.');
    }

    public function sendMainSmtpTestEmail(Request $request): JsonResponse
    {
        $user = $request->user();
        if (! $user || ! $user->isAdmin()) {
            return ApiResponse::error('Only admins can manage system settings.', Response::HTTP_FORBIDDEN);
        }

        $validated = $request->validate([
            'to' => ['required', 'string', 'email', 'max:255'],
            'subject' => ['nullable', 'string', 'max:255'],
            'message' => ['nullable', 'string', 'max:2000'],
        ]);

        $smtp = $this->mainSmtpSettingsRaw();
        if (($smtp['host'] ?? null) === null || ($smtp['from_address'] ?? null) === null) {
            return ApiResponse::error('Configure main SMTP settings first.', Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $decryptedPassword = null;
        if (! empty($smtp['password'])) {
            try {
                $decryptedPassword = Crypt::decryptString((string) $smtp['password']);
            } catch (Throwable) {
                $decryptedPassword = null;
            }
        }

        $mailerName = 'main_runtime_smtp';
        config([
            "mail.mailers.{$mailerName}" => [
                'transport' => 'smtp',
                'host' => $smtp['host'],
                'port' => $smtp['port'] ?? 587,
                'username' => $smtp['username'] ?? null,
                'password' => $decryptedPassword,
                'encryption' => $smtp['encryption'] ?? null,
                'timeout' => $smtp['timeout'] ?? null,
                'local_domain' => $smtp['local_domain'] ?? null,
            ],
        ]);

        $to = mb_strtolower(trim((string) $validated['to']));
        $subject = trim((string) ($validated['subject'] ?? 'VendorPulse main SMTP test email'));
        $body = trim((string) ($validated['message'] ?? 'This is a test email from VendorPulse main SMTP settings.'));

        try {
            Mail::mailer($mailerName)->raw($body, function ($message) use ($smtp, $to, $subject): void {
                $message->to($to)
                    ->subject($subject)
                    ->from((string) $smtp['from_address'], (string) ($smtp['from_name'] ?? 'VendorPulse'));

                if (! empty($smtp['reply_to_address'])) {
                    $message->replyTo((string) $smtp['reply_to_address'], (string) ($smtp['reply_to_name'] ?? ''));
                }
            });
        } catch (Throwable $e) {
            return ApiResponse::error('Failed to send test email: '.$e->getMessage(), Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        return ApiResponse::success(null, 'Test email sent.');
    }

    /**
     * @return array<string, mixed>
     */
    private function mainSmtpSettingsRaw(): array
    {
        if (! $this->hasSystemSettingsTable()) {
            return [];
        }

        $stored = $this->safeSystemSettingValue(self::MAIN_SMTP_SETTINGS_KEY);

        return is_array($stored) ? $stored : [];
    }

    private function hasSystemSettingsTable(): bool
    {
        try {
            return Schema::hasTable('system_settings');
        } catch (QueryException) {
            return false;
        }
    }

    private function safeSystemSettingValue(string $key): mixed
    {
        try {
            return SystemSetting::query()->where('key', $key)->value('value');
        } catch (QueryException) {
            return null;
        }
    }

    /**
     * @param  array<string, mixed>  $smtp
     * @return array<string, mixed>
     */
    private function sanitizeMainSmtpSettings(array $smtp): array
    {
        return [
            'host' => $smtp['host'] ?? null,
            'port' => $smtp['port'] ?? null,
            'username' => $smtp['username'] ?? null,
            'encryption' => $smtp['encryption'] ?? null,
            'from_address' => $smtp['from_address'] ?? null,
            'from_name' => $smtp['from_name'] ?? null,
            'reply_to_address' => $smtp['reply_to_address'] ?? null,
            'reply_to_name' => $smtp['reply_to_name'] ?? null,
            'timeout' => $smtp['timeout'] ?? null,
            'local_domain' => $smtp['local_domain'] ?? null,
            'has_password' => ! empty($smtp['password']),
        ];
    }
}
