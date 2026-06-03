<?php

namespace App\ExperienceMonitoring\DTO;

use App\ExperienceMonitoring\Support\BrowserTypes;
use App\Models\ExperienceMonitoringTest;

class PlaywrightRunInput
{
    public function __construct(
        public string $testId,
        public string $organizationId,
        public string $testName,
        public string $loginUrl,
        public string $loginUsername,
        public string $loginPassword,
        public string $dashboardUrl,
        public string $browserType,
        public int $timeoutMs,
        public int $sessionIndex,
        public array $configuration = [],
    ) {}

    public static function fromTest(ExperienceMonitoringTest $test, int $sessionIndex): self
    {
        return new self(
            testId: (string) $test->id,
            organizationId: (string) $test->organization_id,
            testName: (string) $test->name,
            loginUrl: (string) $test->login_url,
            loginUsername: (string) $test->login_username,
            loginPassword: (string) $test->login_password,
            dashboardUrl: (string) $test->dashboard_url,
            browserType: BrowserTypes::normalize($test->browser_type),
            timeoutMs: (int) $test->timeout_ms,
            sessionIndex: $sessionIndex,
            configuration: is_array($test->configuration) ? $test->configuration : [],
        );
    }

    public function toArray(): array
    {
        return [
            'test_id' => $this->testId,
            'organization_id' => $this->organizationId,
            'test_name' => $this->testName,
            'login_url' => $this->loginUrl,
            'login_username' => $this->loginUsername,
            'login_password' => $this->loginPassword,
            'dashboard_url' => $this->dashboardUrl,
            'browser_type' => $this->browserType,
            'timeout_ms' => $this->timeoutMs,
            'session_index' => $this->sessionIndex,
            'configuration' => $this->configuration,
        ];
    }
}
