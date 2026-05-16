<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Resources\Api\V1\OrganizationResource;
use App\Http\Resources\Api\V1\UserResource;
use App\Models\Organization;
use App\Models\SystemSetting;
use App\Models\User;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class OrganizationManagementController extends BaseApiController
{
    private const MANAGEMENT_EMAIL_SETTINGS_KEY = 'management_email_notifications';
    private const MAIN_SMTP_SETTINGS_KEY = 'main_smtp_settings';

    public function users(Request $request): JsonResponse
    {
        Gate::authorize('viewUsers', Organization::class);

        $users = User::query()
            ->with(['organizations', 'defaultOrganization'])
            ->orderBy('name')
            ->get();

        return ApiResponse::success(UserResource::collection($users));
    }

    public function index(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        Gate::authorize('viewAny', Organization::class);

        if ($user->isAdmin()) {
            $query = Organization::query();

            if (filter_var($request->query('include_trashed'), FILTER_VALIDATE_BOOLEAN)) {
                $query->withTrashed();
            }

            $organizations = $query->orderBy('name')->get();
        } else {
            $organizations = $user->organizations()
                ->orderBy('name')
                ->get();
        }

        return ApiResponse::success(OrganizationResource::collection($organizations));
    }

    public function store(Request $request): JsonResponse
    {
        /** @var User $actor */
        $actor = $request->user();
        Gate::authorize('create', Organization::class);
        $isAdmin = $actor->isAdmin();

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:255', 'unique:organizations,slug'],
            'settings' => ['nullable', 'array'],
            'owner_user_email' => ['nullable', 'string', 'email', 'max:255'],
            'set_owner_default' => ['sometimes', 'boolean'],
        ]);

        $ownerEmail = isset($validated['owner_user_email'])
            ? mb_strtolower(trim((string) $validated['owner_user_email']))
            : null;

        if (! $isAdmin && $ownerEmail !== null && $ownerEmail !== mb_strtolower($actor->email)) {
            return ApiResponse::error('Only admins can assign a different organization owner.', Response::HTTP_FORBIDDEN);
        }

        $owner = $ownerEmail
            ? User::query()->whereRaw('LOWER(email) = ?', [$ownerEmail])->first()
            : $actor;

        if (! $owner) {
            return ApiResponse::error(
                'Owner user was not found. Create that user first or leave owner email empty.',
                Response::HTTP_UNPROCESSABLE_ENTITY
            );
        }

        $organization = DB::transaction(function () use ($validated, $actor, $owner): Organization {
            $organization = Organization::query()->create([
                'name' => $validated['name'],
                'slug' => $validated['slug'] ?? null,
                'settings' => $validated['settings'] ?? null,
                'created_by' => $actor->id,
                'updated_by' => $actor->id,
            ]);

            $owner->organizations()->syncWithoutDetaching([
                $organization->id => [
                    'role' => 'owner',
                    'meta' => null,
                ],
            ]);

            $setOwnerDefault = (bool) ($validated['set_owner_default'] ?? true);
            if ($setOwnerDefault) {
                $owner->forceFill(['default_organization_id' => $organization->id])->save();
            }

            return $organization;
        });

        $this->sendOrganizationCreatedEmail($owner, $organization, $actor);

        return ApiResponse::success(
            new OrganizationResource($organization),
            'Organization created.',
            Response::HTTP_CREATED
        );
    }

    public function attachMember(Request $request, Organization $organization): JsonResponse
    {
        /** @var User $actor */
        $actor = $request->user();
        Gate::authorize('attachMember', $organization);

        $validated = $request->validate([
            'user_email' => ['required', 'string', 'email', 'max:255'],
            'role' => ['sometimes', 'string', 'in:member,owner,admin'],
            'set_default' => ['sometimes', 'boolean'],
        ]);

        $email = mb_strtolower(trim((string) $validated['user_email']));
        $role = (string) ($validated['role'] ?? 'member');
        $setDefault = (bool) ($validated['set_default'] ?? true);

        $user = User::query()->whereRaw('LOWER(email) = ?', [$email])->first();

        if (! $user) {
            return ApiResponse::error('User not found for the provided email.', Response::HTTP_NOT_FOUND);
        }

        $user->organizations()->syncWithoutDetaching([
            $organization->id => [
                'role' => $role,
                'meta' => null,
            ],
        ]);

        if ($setDefault) {
            $user->forceFill(['default_organization_id' => $organization->id])->save();
        }

        return ApiResponse::success(null, 'User assigned to organization.');
    }

    public function createUser(Request $request, Organization $organization): JsonResponse
    {
        Gate::authorize('createUser', $organization);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8'],
            'timezone' => ['nullable', 'string', 'max:64'],
            'role' => ['sometimes', 'string', 'in:member,owner,admin'],
            'is_admin' => ['sometimes', 'boolean'],
            'set_default' => ['sometimes', 'boolean'],
            'email_verified' => ['sometimes', 'boolean'],
        ]);

        $role = (string) ($validated['role'] ?? 'member');
        $setDefault = (bool) ($validated['set_default'] ?? true);
        $emailVerified = (bool) ($validated['email_verified'] ?? true);

        $user = User::query()->create([
            'name' => $validated['name'],
            'email' => mb_strtolower(trim((string) $validated['email'])),
            'password' => $validated['password'],
            'timezone' => $validated['timezone'] ?? null,
            'is_admin' => (bool) ($validated['is_admin'] ?? false),
            'default_organization_id' => $setDefault ? $organization->id : null,
            'email_verified_at' => $emailVerified ? now() : null,
        ]);

        $user->organizations()->syncWithoutDetaching([
            $organization->id => [
                'role' => $role,
                'meta' => null,
            ],
        ]);

        if ($setDefault && $user->default_organization_id !== $organization->id) {
            $user->forceFill(['default_organization_id' => $organization->id])->save();
        }

        $this->sendUserCreatedEmail($user, $organization, $request->user());

        return ApiResponse::success(
            new UserResource($user->fresh(['organizations', 'defaultOrganization'])),
            'User created and assigned to organization.',
            Response::HTTP_CREATED
        );
    }

    public function destroy(Request $request, Organization $organization): JsonResponse
    {
        Gate::authorize('delete', $organization);

        if ($organization->trashed()) {
            return ApiResponse::error('Organization is already soft deleted.', Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        DB::transaction(function () use ($organization): void {
            User::query()->where('default_organization_id', $organization->id)->update([
                'default_organization_id' => null,
            ]);

            $organization->delete();
        });

        return ApiResponse::success(null, 'Organization soft deleted.');
    }

    public function forceDestroy(Request $request, string $organization): JsonResponse
    {
        $model = Organization::query()->withTrashed()->find($organization);

        if (! $model) {
            return ApiResponse::error('Organization not found.', Response::HTTP_NOT_FOUND);
        }

        Gate::authorize('forceDelete', $model);

        DB::transaction(function () use ($model): void {
            User::query()->where('default_organization_id', $model->id)->update([
                'default_organization_id' => null,
            ]);

            $model->users()->detach();
            $model->forceDelete();
        });

        return ApiResponse::success(null, 'Organization permanently deleted.');
    }

    private function sendOrganizationCreatedEmail(User $recipient, Organization $organization, User $actor): void
    {
        if (! $this->isManagementEmailEnabled('notify_organization_created', true)) {
            return;
        }

        if (! filter_var($recipient->email, FILTER_VALIDATE_EMAIL)) {
            return;
        }

        $appName = (string) config('app.name', 'VendorPulse');
        $dashboardUrl = (string) config('app.url', '');
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

        $this->sendMainVendorPulseEmail($recipient->email, $subject, implode("\n", $bodyLines));
    }

    private function sendUserCreatedEmail(User $recipient, Organization $organization, User $actor): void
    {
        if (! $this->isManagementEmailEnabled('notify_user_created', true)) {
            return;
        }

        if (! filter_var($recipient->email, FILTER_VALIDATE_EMAIL)) {
            return;
        }

        $appName = (string) config('app.name', 'VendorPulse');
        $dashboardUrl = (string) config('app.url', '');
        $subject = sprintf('[%s] Your account is ready', $appName);
        $bodyLines = [
            sprintf('Hello %s,', $recipient->name),
            '',
            sprintf('Your account has been created in %s.', $appName),
            sprintf('Organization: %s', $organization->name),
            sprintf('Created by: %s (%s)', $actor->name, $actor->email),
            'Use your assigned credentials to sign in.',
        ];

        if ($dashboardUrl !== '') {
            $bodyLines[] = sprintf('Sign in: %s', $dashboardUrl);
        }

        $bodyLines[] = '';
        $bodyLines[] = 'If this was unexpected, contact your administrator.';

        $this->sendMainVendorPulseEmail($recipient->email, $subject, implode("\n", $bodyLines));
    }

    private function sendMainVendorPulseEmail(string $to, string $subject, string $body): void
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

            Mail::mailer($mailerName)->raw($body, function ($message) use ($to, $subject, $smtp): void {
                $message->to($to)->subject($subject);

                if (($smtp['from_address'] ?? null) !== null) {
                    $message->from((string) $smtp['from_address'], (string) ($smtp['from_name'] ?? config('app.name', 'VendorPulse')));
                }

                if (($smtp['reply_to_address'] ?? null) !== null) {
                    $message->replyTo((string) $smtp['reply_to_address'], (string) ($smtp['reply_to_name'] ?? ''));
                }
            });
        } catch (Throwable) {
            // Notification delivery failures should not block management operations.
        }
    }

    private function isManagementEmailEnabled(string $key, bool $default = true): bool
    {
        if (! Schema::hasTable('system_settings')) {
            return $default;
        }

        $value = SystemSetting::query()
            ->where('key', self::MANAGEMENT_EMAIL_SETTINGS_KEY)
            ->value('value');

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
        if (! Schema::hasTable('system_settings')) {
            return [];
        }

        $value = SystemSetting::query()
            ->where('key', self::MAIN_SMTP_SETTINGS_KEY)
            ->value('value');

        return is_array($value) ? $value : [];
    }
}
