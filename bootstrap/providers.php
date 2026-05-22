<?php

use App\EmailMonitoring\EmailMonitoringServiceProvider;
use App\ExperienceMonitoring\ExperienceMonitoringServiceProvider;
use App\Providers\AppServiceProvider;
use App\Providers\HorizonServiceProvider;
use App\SiteMonitoring\SiteMonitoringServiceProvider;

return [
    AppServiceProvider::class,
    EmailMonitoringServiceProvider::class,
    ExperienceMonitoringServiceProvider::class,
    SiteMonitoringServiceProvider::class,
    HorizonServiceProvider::class,
];
