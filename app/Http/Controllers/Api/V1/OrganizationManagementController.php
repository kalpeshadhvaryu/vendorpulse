<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Resources\Api\V1\OrganizationResource;
use App\Http\Resources\Api\V1\UserResource;
use App\Models\Organization;
use App\Models\User;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

class OrganizationManagementController extends BaseApiController
{
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
            $organizations = Organization::query()
                ->orderBy('name')
                ->get();
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

        return DB::transaction(function () use ($validated, $actor, $owner): JsonResponse {
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

            return ApiResponse::success(
                new OrganizationResource($organization),
                'Organization created.',
                Response::HTTP_CREATED
            );
        });
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

        return ApiResponse::success(
            new UserResource($user->fresh(['organizations', 'defaultOrganization'])),
            'User created and assigned to organization.',
            Response::HTTP_CREATED
        );
    }
}
