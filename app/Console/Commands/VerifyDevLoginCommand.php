<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;

class VerifyDevLoginCommand extends Command
{
    protected $signature = 'vendorpulse:verify-dev-login
                            {--email=test@example.com : Email to look up}
                            {--password=password : Plain password to verify against the stored hash}';

    protected $description = 'Print active DB connection and verify Hash::check for a user (debug login issues)';

    public function handle(): int
    {
        $default = (string) config('database.default');
        $database = config("database.connections.{$default}.database");
        $this->info("Default connection: {$default}");
        $this->info('Database: '.(is_string($database) ? $database : json_encode($database)));

        $email = mb_strtolower(trim((string) $this->option('email')));
        $plain = (string) $this->option('password');

        $user = User::query()->whereRaw('LOWER(email) = ?', [$email])->first();

        if (! $user) {
            $this->error("No user with email matching [{$email}] in this database.");
            $this->line('Run: php artisan config:clear && php artisan db:seed');

            return self::FAILURE;
        }

        $this->info("User id: {$user->id}");

        $hash = $user->getRawOriginal('password');
        if (! is_string($hash) || $hash === '') {
            $this->error('User has no password hash in the database.');

            return self::FAILURE;
        }

        $this->line('Hash prefix: '.substr($hash, 0, 7).'… (len '.strlen($hash).')');

        if (! Hash::check($plain, $hash)) {
            $this->error('Hash::check failed — password in DB does not match --password.');
            $this->line('Fix: php artisan db:seed');

            return self::FAILURE;
        }

        $this->info('Hash::check OK — login should succeed against THIS database.');

        return self::SUCCESS;
    }
}
