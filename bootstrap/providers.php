<?php

use App\EmailMonitoring\EmailMonitoringServiceProvider;
use App\Providers\AppServiceProvider;
use App\Providers\HorizonServiceProvider;
use App\SiteMonitoring\SiteMonitoringServiceProvider;

return [
    AppServiceProvider::class,
    EmailMonitoringServiceProvider::class,
    SiteMonitoringServiceProvider::class,
    HorizonServiceProvider::class,
];
