<?php

namespace App\Services\Auth;

use App\Models\Organization;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthenticationService
{
    /**
     * @return array{user: User, organization: Organization, token: string}
     */
    public function register(array $input): array
    {
        return DB::transaction(function () use ($input) {
            $organization = Organization::query()->create([
                'name' => $input['organization_name'],
                'slug' => $input['organization_slug'] ?? null,
                'settings' => $input['organization_settings'] ?? null,
            ]);

            $user = User::query()->create([
                'name' => $input['name'],
                'email' => mb_strtolower(trim((string) $input['email'])),
                'password' => $input['password'],
                'timezone' => $input['timezone'] ?? null,
                'default_organization_id' => $organization->id,
                'email_verified_at' => now(),
            ]);

            $user->organizations()->attach($organization->id, [
                'role' => 'owner',
                'meta' => null,
            ]);

            $token = $user->createToken('mobile')->plainTextToken;

            return [
                'user' => $user->fresh(['organizations']),
                'organization' => $organization,
                'token' => $token,
            ];
        });
    }

    /**
     * @return array{user: User, token: string}
     */
    public function login(string $email, string $password, ?string $deviceName = null): array
    {
        $email = mb_strtolower(trim($email));

        $user = User::query()
            ->whereRaw('LOWER(email) = ?', [$email])
            ->first();

        if (! $user) {
            throw ValidationException::withMessages([
                'email' => [__('auth.failed')],
            ]);
        }

        // Use the raw column so we never depend on casts/hidden quirks when verifying.
        $hash = $user->getRawOriginal('password');

        if (! is_string($hash) || ! Hash::check($password, $hash)) {
            throw ValidationException::withMessages([
                'email' => [__('auth.failed')],
            ]);
        }

        $tokenName = $deviceName ?: 'mobile';
        $token = $user->createToken($tokenName)->plainTextToken;

        return [
            'user' => $user->load(['organizations', 'defaultOrganization']),
            'token' => $token,
        ];
    }

    public function logout(User $user): void
    {
        $token = $user->currentAccessToken();
        if ($token) {
            $token->delete();
        }
    }
}
