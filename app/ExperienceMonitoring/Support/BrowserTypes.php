<?php

namespace App\ExperienceMonitoring\Support;

class BrowserTypes
{
    /**
     * @return list<string>
     */
    public static function allowed(): array
    {
        $allowed = config('experience-monitoring.allowed_browsers', ['chromium']);

        if (! is_array($allowed)) {
            return ['chromium'];
        }

        $allowed = array_values(array_filter(array_map(
            static fn ($browser) => strtolower(trim((string) $browser)),
            $allowed,
        )));

        return $allowed !== [] ? $allowed : ['chromium'];
    }

    public static function default(): string
    {
        $default = strtolower(trim((string) config('experience-monitoring.default_browser', 'chromium')));

        return in_array($default, self::allowed(), true) ? $default : self::allowed()[0];
    }

    public static function normalize(?string $browser): string
    {
        $browser = strtolower(trim((string) ($browser ?: self::default())));

        if (in_array($browser, self::allowed(), true)) {
            return $browser;
        }

        return self::default();
    }
}
