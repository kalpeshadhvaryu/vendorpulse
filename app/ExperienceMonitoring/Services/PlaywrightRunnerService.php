<?php

namespace App\ExperienceMonitoring\Services;

use App\ExperienceMonitoring\DTO\PlaywrightRunInput;
use App\ExperienceMonitoring\DTO\PlaywrightRunResult;
use RuntimeException;
use Symfony\Component\Process\Process;

class PlaywrightRunnerService
{
    public function run(PlaywrightRunInput $input): PlaywrightRunResult
    {
        $command = (string) config('experience-monitoring.runner_command', 'node');
        $script = (string) config('experience-monitoring.runner_script');

        $process = new Process([
            $command,
            $script,
            json_encode($input->toArray(), JSON_THROW_ON_ERROR),
        ], base_path(), null, null, max(5, (int) config('experience-monitoring.runner_timeout_seconds', 120)));

        $process->run();

        if (! $process->isSuccessful()) {
            throw new RuntimeException('Playwright execution failed: '.$process->getErrorOutput());
        }

        $json = trim($process->getOutput());
        if ($json === '') {
            throw new RuntimeException('Playwright execution returned empty output.');
        }

        /** @var array<string, mixed> $payload */
        $payload = json_decode($json, true, 512, JSON_THROW_ON_ERROR);

        return PlaywrightRunResult::fromArray($payload);
    }
}
