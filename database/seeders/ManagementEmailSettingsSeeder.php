<?php

namespace Database\Seeders;

use App\Models\SystemSetting;
use Illuminate\Database\Seeder;

class ManagementEmailSettingsSeeder extends Seeder
{
    public function run(): void
    {
        SystemSetting::query()->updateOrCreate(
            ['key' => 'management_email_notifications'],
            [
                'value' => [
                    'notify_organization_created' => true,
                    'notify_user_created' => true,
                ],
            ]
        );
    }
}
