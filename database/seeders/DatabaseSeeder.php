<?php

namespace Database\Seeders;

use App\Models\Organization;
use App\Models\User;
use App\Services\Auth\AuthenticationService;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    private const DEV_EMAIL = 'test@example.com';

    private const DEV_PASSWORD = 'password';

    /**
     * Seed the application's database.
     *
     * If the dev user already exists, their password and organization links are
     * refreshed so `php artisan db:seed` always recovers from a bad password or
     * a partial record (avoids "credentials do not match" after an early skip).
     */
    public function run(): void
    {
        $user = User::query()->where('email', self::DEV_EMAIL)->first();

        if ($user) {
            $this->syncDevUser($user);

            return;
        }

        app(AuthenticationService::class)->register([
            'name' => 'Test User',
            'email' => self::DEV_EMAIL,
            'password' => self::DEV_PASSWORD,
            'organization_name' => 'Demo Organization',
        ]);

        $this->command?->info('Seeded '.self::DEV_EMAIL.' / '.self::DEV_PASSWORD.' (Demo Organization).');

        $this->call(MonitoringDemoSeeder::class);
    }

    private function syncDevUser(User $user): void
    {
        $organization = Organization::query()->firstOrCreate(
            ['name' => 'Demo Organization'],
            [],
        );

        $user->forceFill([
            'name' => 'Test User',
            'password' => self::DEV_PASSWORD,
            'default_organization_id' => $organization->id,
            'email_verified_at' => $user->email_verified_at ?? now(),
        ])->save();

        if (! $user->organizations()->where('organizations.id', $organization->id)->exists()) {
            $user->organizations()->attach($organization->id, [
                'role' => 'owner',
                'meta' => null,
            ]);
        }

        $this->command?->info('Dev user '.self::DEV_EMAIL.' / '.self::DEV_PASSWORD.' — password and organization links refreshed.');

        $this->call(MonitoringDemoSeeder::class);
    }
}
