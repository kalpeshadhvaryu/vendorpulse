<?php

namespace Database\Seeders;

use App\Models\Organization;
use App\Models\User;
use App\Services\Auth\AuthenticationService;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    private const GLOBAL_ADMIN_EMAIL = 'admin@vendorpulse.com';

    private const GLOBAL_ADMIN_PASSWORD = 'Admin@123456';

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
        $this->seedGlobalAdmin();
        $this->call(ManagementEmailSettingsSeeder::class);

        $user = User::query()->where('email', self::DEV_EMAIL)->first();

        if ($user) {
            $this->syncDevUser($user);
            $this->seedLocalDemoRecords();

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
        $this->seedLocalDemoRecords();
    }

    private function seedGlobalAdmin(): void
    {
        $admin = User::query()
            ->withTrashed()
            ->where('email', self::GLOBAL_ADMIN_EMAIL)
            ->first();

        if (! $admin) {
            $admin = new User([
                'email' => self::GLOBAL_ADMIN_EMAIL,
            ]);
        }

        if (method_exists($admin, 'trashed') && $admin->trashed()) {
            $admin->restore();
        }

        $admin->forceFill([
            'name' => 'Global Admin',
            'password' => self::GLOBAL_ADMIN_PASSWORD,
            'is_admin' => true,
            'default_organization_id' => null,
            'email_verified_at' => $admin->email_verified_at ?? now(),
        ])->save();

        $this->command?->info('Global admin '.$admin->email.' / '.self::GLOBAL_ADMIN_PASSWORD.' seeded.');
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
            'is_admin' => false,
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

    private function seedLocalDemoRecords(): void
    {
        if (! app()->environment(['local', 'testing'])) {
            return;
        }

        $this->call(VendorInvoiceDemoSeeder::class);
    }
}
