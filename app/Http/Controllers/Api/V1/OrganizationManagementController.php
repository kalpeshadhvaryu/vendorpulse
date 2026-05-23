<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Resources\Api\V1\OrganizationResource;
use App\Http\Resources\Api\V1\UserResource;
use App\Models\Organization;
use App\Models\SystemSetting;
use App\Models\User;
use App\Support\ApiResponse;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class OrganizationManagementController extends BaseApiController
{
    private const MANAGEMENT_EMAIL_SETTINGS_KEY = 'management_email_notifications';
    private const MAIN_SMTP_SETTINGS_KEY = 'main_smtp_settings';

    public function users(Request $request): JsonResponse
    {
        Gate::authorize('viewUsers', Organization::class);

        $query = User::query()
            ->with(['organizations', 'defaultOrganization'])
            ->orderBy('name');

        if (filter_var($request->query('include_trashed'), FILTER_VALIDATE_BOOLEAN)) {
            $query->withTrashed();
        }

        $users = $query->get();

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

        if ($request->filled('slug')) {
            $request->merge([
                'slug' => $this->normalizeSlug((string) $request->input('slug')),
            ]);
        }

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:255', 'unique:organizations,slug'],
            'phone_country_code' => ['nullable', 'string', 'max:8', 'regex:/^\+[1-9][0-9]{0,3}$/'],
            'phone_number' => ['nullable', 'string', 'max:20', 'regex:/^[0-9]{6,15}$/'],
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
                'phone_country_code' => $validated['phone_country_code'] ?? null,
                'phone_number' => $validated['phone_number'] ?? null,
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

    public function update(Request $request, Organization $organization): JsonResponse
    {
        Gate::authorize('update', $organization);

        $request->merge([
            'slug' => $this->normalizeSlug((string) $request->input('slug', '')),
        ]);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['required', 'string', 'max:255', 'unique:organizations,slug,'.$organization->id],
            'phone_country_code' => ['nullable', 'string', 'max:8', 'regex:/^\+[1-9][0-9]{0,3}$/'],
            'phone_number' => ['nullable', 'string', 'max:20', 'regex:/^[0-9]{6,15}$/'],
            'settings' => ['nullable', 'array'],
        ]);

        $organization->forceFill([
            'name' => $validated['name'],
            'slug' => $validated['slug'],
            'phone_country_code' => $validated['phone_country_code'] ?? null,
            'phone_number' => $validated['phone_number'] ?? null,
            'settings' => $validated['settings'] ?? $organization->settings,
            'updated_by' => $request->user()?->id,
        ])->save();

        return ApiResponse::success(new OrganizationResource($organization->fresh()), 'Organization updated.');
    }

    private function normalizeSlug(string $value): string
    {
        return (string) Str::of($value)
            ->trim()
            ->slug('-');
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

    public function updateUserGlobalAccess(Request $request, User $user): JsonResponse
    {
        Gate::authorize('viewUsers', Organization::class);

        $validated = $request->validate([
            'is_admin' => ['required', 'boolean'],
        ]);

        $user->forceFill([
            'is_admin' => (bool) $validated['is_admin'],
        ])->save();

        return ApiResponse::success(
            new UserResource($user->fresh(['organizations', 'defaultOrganization'])),
            (bool) $validated['is_admin']
                ? 'Global access granted.'
                : 'Global access revoked.'
        );
    }

    public function show(Request $request, string $organization): JsonResponse
    {
        $model = Organization::query()->withTrashed()->find($organization);

        if (! $model) {
            return ApiResponse::error('Organization not found.', Response::HTTP_NOT_FOUND);
        }

        Gate::authorize('view', $model);

        $model->load([
            'users' => fn ($query) => $query->orderBy('name'),
        ]);

        return ApiResponse::success([
            'organization' => (new OrganizationResource($model))->resolve($request),
            'members' => UserResource::collection($model->users)->resolve($request),
        ]);
    }

    public function restore(Request $request, string $organization): JsonResponse
    {
        $model = Organization::query()->withTrashed()->find($organization);

        if (! $model) {
            return ApiResponse::error('Organization not found.', Response::HTTP_NOT_FOUND);
        }

        Gate::authorize('restore', $model);

        if (! $model->trashed()) {
            return ApiResponse::error('Organization is not deleted.', Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $model->restore();

        return ApiResponse::success(
            new OrganizationResource($model->fresh()),
            'Organization restored.'
        );
    }

    public function updateMember(Request $request, Organization $organization, User $user): JsonResponse
    {
        Gate::authorize('updateMember', $organization);

        if (! $user->belongsToOrganization((string) $organization->id)) {
            return ApiResponse::error('User is not a member of this organization.', Response::HTTP_NOT_FOUND);
        }

        $validated = $request->validate([
            'role' => ['required', 'string', 'in:member,owner,admin'],
            'set_default' => ['sometimes', 'boolean'],
        ]);

        $role = (string) $validated['role'];
        $setDefault = (bool) ($validated['set_default'] ?? false);

        $organization->users()->updateExistingPivot($user->id, [
            'role' => $role,
        ]);

        if ($setDefault) {
            $user->forceFill(['default_organization_id' => $organization->id])->save();
        }

        $member = $organization->users()
            ->where('users.id', $user->id)
            ->first();

        $member?->load(['organizations', 'defaultOrganization']);

        return ApiResponse::success(
            new UserResource($member ?? $user),
            'Member role updated.'
        );
    }

    public function detachMember(Request $request, Organization $organization, User $user): JsonResponse
    {
        Gate::authorize('detachMember', $organization);

        if (! $user->belongsToOrganization((string) $organization->id)) {
            return ApiResponse::error('User is not a member of this organization.', Response::HTTP_NOT_FOUND);
        }

        /** @var User $actor */
        $actor = $request->user();

        if ($actor->id === $user->id) {
            return ApiResponse::error('You cannot remove yourself from an organization.', Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        DB::transaction(function () use ($organization, $user): void {
            $organization->users()->detach($user->id);

            if ($user->default_organization_id === $organization->id) {
                $fallbackOrgId = $user->organizations()
                    ->orderBy('organizations.name')
                    ->value('organizations.id');

                $user->forceFill([
                    'default_organization_id' => $fallbackOrgId,
                ])->save();
            }
        });

        return ApiResponse::success(null, 'User removed from organization.');
    }

    public function showUser(Request $request, string $user): JsonResponse
    {
        $model = User::query()->withTrashed()->find($user);

        if (! $model) {
            return ApiResponse::error('User not found.', Response::HTTP_NOT_FOUND);
        }

        Gate::authorize('viewUser', $model);

        $model->load(['organizations', 'defaultOrganization']);

        return ApiResponse::success(new UserResource($model));
    }

    public function updateUser(Request $request, User $user): JsonResponse
    {
        Gate::authorize('updateUser', $user);

        $validated = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'email' => ['sometimes', 'string', 'email', 'max:255', 'unique:users,email,'.$user->id],
            'timezone' => ['nullable', 'string', 'max:64'],
            'password' => ['sometimes', 'string', 'min:8'],
            'default_organization_id' => ['nullable', 'uuid', 'exists:organizations,id'],
        ]);

        if (array_key_exists('email', $validated)) {
            $validated['email'] = mb_strtolower(trim((string) $validated['email']));
        }

        if (array_key_exists('default_organization_id', $validated) && $validated['default_organization_id'] !== null) {
            if (! $user->belongsToOrganization((string) $validated['default_organization_id'])) {
                return ApiResponse::error(
                    'Default organization must be one the user belongs to.',
                    Response::HTTP_UNPROCESSABLE_ENTITY
                );
            }
        }

        $attributes = [
            'name' => $validated['name'] ?? $user->name,
            'email' => $validated['email'] ?? $user->email,
            'timezone' => array_key_exists('timezone', $validated) ? $validated['timezone'] : $user->timezone,
            'default_organization_id' => array_key_exists('default_organization_id', $validated)
                ? $validated['default_organization_id']
                : $user->default_organization_id,
            'updated_by' => $request->user()?->id,
        ];

        if (array_key_exists('password', $validated)) {
            $attributes['password'] = $validated['password'];
        }

        $user->forceFill($attributes)->save();

        $user->load(['organizations', 'defaultOrganization']);

        return ApiResponse::success(new UserResource($user), 'User updated.');
    }

    public function destroyUser(Request $request, User $user): JsonResponse
    {
        Gate::authorize('deleteUser', $user);

        /** @var User $actor */
        $actor = $request->user();

        if ($actor->id === $user->id) {
            return ApiResponse::error('You cannot deactivate your own account.', Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        if ($user->trashed()) {
            return ApiResponse::error('User is already deactivated.', Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        DB::transaction(function () use ($user): void {
            $user->tokens()->delete();
            $user->delete();
        });

        return ApiResponse::success(null, 'User deactivated.');
    }

    public function restoreUser(Request $request, string $user): JsonResponse
    {
        $model = User::query()->withTrashed()->find($user);

        if (! $model) {
            return ApiResponse::error('User not found.', Response::HTTP_NOT_FOUND);
        }

        Gate::authorize('restoreUser', $model);

        if (! $model->trashed()) {
            return ApiResponse::error('User is not deactivated.', Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $model->restore();

        $model->load(['organizations', 'defaultOrganization']);

        return ApiResponse::success(new UserResource($model), 'User restored.');
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
