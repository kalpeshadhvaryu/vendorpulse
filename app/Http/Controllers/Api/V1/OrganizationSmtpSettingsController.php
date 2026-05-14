<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\Organization;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Mail;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class OrganizationSmtpSettingsController extends BaseApiController
{
    public function show(Request $request): JsonResponse
    {
        $organization = $this->resolveOrganization();
        Gate::authorize('manageSmtp', $organization);

        return ApiResponse::success($this->sanitizeSmtpSettings($this->smtpSettings($organization)));
    }

    public function update(Request $request): JsonResponse
    {
        $organization = $this->resolveOrganization();
        Gate::authorize('manageSmtp', $organization);

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

        $settings = $organization->settings ?? [];
        $existing = $this->smtpSettings($organization);

        $password = array_key_exists('password', $validated)
            ? (($validated['password'] ?? '') !== '' ? $validated['password'] : null)
            : ($existing['password'] ?? null);

        $settings['smtp'] = [
            'host' => trim((string) $validated['host']),
            'port' => (int) $validated['port'],
            'username' => isset($validated['username']) && $validated['username'] !== '' ? trim((string) $validated['username']) : null,
            'password' => $password,
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

        $organization->forceFill(['settings' => $settings])->save();

        return ApiResponse::success(
            $this->sanitizeSmtpSettings($settings['smtp']),
            'SMTP settings saved.'
        );
    }

    public function sendTestEmail(Request $request): JsonResponse
    {
        $organization = $this->resolveOrganization();
        Gate::authorize('manageSmtp', $organization);

        $validated = $request->validate([
            'to' => ['required', 'string', 'email', 'max:255'],
            'subject' => ['nullable', 'string', 'max:255'],
            'message' => ['nullable', 'string', 'max:2000'],
        ]);

        $smtp = $this->smtpSettings($organization);
        if (($smtp['host'] ?? null) === null || ($smtp['from_address'] ?? null) === null) {
            return ApiResponse::error('Configure SMTP settings first.', Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $mailerName = 'organization_runtime_smtp';

        config([
            "mail.mailers.{$mailerName}" => [
                'transport' => 'smtp',
                'host' => $smtp['host'],
                'port' => $smtp['port'] ?? 587,
                'username' => $smtp['username'] ?? null,
                'password' => $smtp['password'] ?? null,
                'encryption' => $smtp['encryption'] ?? null,
                'timeout' => $smtp['timeout'] ?? null,
                'local_domain' => $smtp['local_domain'] ?? null,
            ],
        ]);

        $to = mb_strtolower(trim((string) $validated['to']));
        $subject = trim((string) ($validated['subject'] ?? 'VendorPulse SMTP test email'));
        $body = trim((string) ($validated['message'] ?? 'This is a test email from VendorPulse SMTP settings.'));

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

    private function resolveOrganization(): Organization
    {
        $organizationId = $this->organizationId();

        /** @var Organization $organization */
        $organization = Organization::query()->findOrFail($organizationId);

        return $organization;
    }

    private function smtpSettings(Organization $organization): array
    {
        $settings = $organization->settings ?? [];

        $smtp = $settings['smtp'] ?? [];

        return is_array($smtp) ? $smtp : [];
    }

    private function sanitizeSmtpSettings(array $smtp): array
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
