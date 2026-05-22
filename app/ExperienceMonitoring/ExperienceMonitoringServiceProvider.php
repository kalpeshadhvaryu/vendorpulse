<?php

namespace App\ExperienceMonitoring;

use Illuminate\Support\ServiceProvider;

class ExperienceMonitoringServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Bindings live in AppServiceProvider to keep repository registrations centralized.
    }

    public function boot(): void
    {
    }
}
