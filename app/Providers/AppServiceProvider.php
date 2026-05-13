<?php

namespace App\Providers;

use App\Repositories\Contracts\InvoiceRepositoryInterface;
use App\Repositories\Contracts\MonitoringCheckRepositoryInterface;
use App\Repositories\Contracts\VendorEmailRepositoryInterface;
use App\Repositories\Contracts\VendorRepositoryInterface;
use App\Repositories\Eloquent\InvoiceRepository;
use App\Repositories\Eloquent\MonitoringCheckRepository;
use App\Repositories\Eloquent\VendorEmailRepository;
use App\Repositories\Eloquent\VendorRepository;
use App\Support\Organization\CurrentOrganization;
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
    }

    public function boot(): void
    {
        //
    }
}
