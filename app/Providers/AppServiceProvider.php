<?php

namespace App\Providers;

use App\Repositories\Contracts\InvoiceRepositoryInterface;
use App\Repositories\Contracts\ExperienceMonitoringRepositoryInterface;
use App\Repositories\Contracts\MonitoringCheckRepositoryInterface;
use App\Repositories\Contracts\VendorEmailRepositoryInterface;
use App\Repositories\Contracts\VendorRepositoryInterface;
use App\Repositories\Eloquent\InvoiceRepository;
use App\Repositories\Eloquent\ExperienceMonitoringRepository;
use App\Repositories\Eloquent\MonitoringCheckRepository;
use App\Repositories\Eloquent\VendorEmailRepository;
use App\Repositories\Eloquent\VendorRepository;
use App\Models\Organization;
use App\Models\User;
use App\Policies\OrganizationPolicy;
use App\Policies\UserPolicy;
use App\Support\Organization\CurrentOrganization;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(CurrentOrganization::class, fn () => new CurrentOrganization);

        $this->app->bind(VendorRepositoryInterface::class, VendorRepository::class);
        $this->app->bind(VendorEmailRepositoryInterface::class, VendorEmailRepository::class);
        $this->app->bind(InvoiceRepositoryInterface::class, InvoiceRepository::class);
        $this->app->bind(MonitoringCheckRepositoryInterface::class, MonitoringCheckRepository::class);
        $this->app->bind(ExperienceMonitoringRepositoryInterface::class, ExperienceMonitoringRepository::class);
    }

    public function boot(): void
    {
        Gate::policy(Organization::class, OrganizationPolicy::class);
        Gate::policy(User::class, UserPolicy::class);
    }
}
