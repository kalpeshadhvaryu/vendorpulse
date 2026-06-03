<?php

namespace App\ExperienceMonitoring\Services;

use App\ExperienceMonitoring\DTO\PlaywrightRunInput;
use App\ExperienceMonitoring\DTO\PlaywrightRunResult;
use RuntimeException;
use Symfony\Component\Process\ExecutableFinder;
use Symfony\Component\Process\Process;

class PlaywrightRunnerService
{
    public function run(PlaywrightRunInput $input): PlaywrightRunResult
    {
        $command = $this->resolveRunnerCommand((string) config('experience-monitoring.runner_command', 'node'));
        $script = (string) config('experience-monitoring.runner_script');

        $process = new Process([
            $command,
            $script,
            json_encode($input->toArray(), JSON_THROW_ON_ERROR),
        ], base_path(), $this->runnerEnvironment(), null, max(5, (int) config('experience-monitoring.runner_timeout_seconds', 120)));

        $process->run();

        $json = trim($process->getOutput());
        if ($json !== '') {
            try {
                /** @var array<string, mixed> $payload */
                $payload = json_decode($json, true, 512, JSON_THROW_ON_ERROR);

                return PlaywrightRunResult::fromArray($payload);
            } catch (\JsonException) {
                // Fall through to process failure handling below.
            }
        }

        if (! $process->isSuccessful()) {
            $stderr = trim($process->getErrorOutput());
            $stdout = trim($process->getOutput());
            $hint = str_contains(strtolower($stderr.$stdout), 'executable doesn\'t exist')
                || str_contains(strtolower($stderr.$stdout), 'playwright install')
                ? ' Install Playwright browsers: npm run experience-monitoring:install (or rebuild the horizon Docker image).'
                : '';

            throw new RuntimeException('Playwright execution failed: '.($stderr !== '' ? $stderr : $stdout).$hint);
        }

        throw new RuntimeException('Playwright execution returned empty output.');
    }

    /**
     * @return array<string, string>
     */
    private function runnerEnvironment(): array
    {
        $env = [];
        $browsersPath = trim((string) config('experience-monitoring.playwright_browsers_path', ''));

        if ($browsersPath !== '') {
            $env['PLAYWRIGHT_BROWSERS_PATH'] = $browsersPath;
        }

        return $env;
    }

    private function resolveRunnerCommand(string $configuredCommand): string
    {
        $configuredCommand = trim($configuredCommand);
        if ($configuredCommand === '') {
            $configuredCommand = 'node';
        }

        if (str_contains($configuredCommand, '/') && is_executable($configuredCommand)) {
            return $configuredCommand;
        }

        $finder = new ExecutableFinder;
        $resolved = $finder->find($configuredCommand);
        if (is_string($resolved) && $resolved !== '') {
            if (str_contains($resolved, '/') && is_executable($resolved)) {
                return $resolved;
            }
        }

        foreach ($this->fallbackNodeCandidates() as $candidate) {
            if (is_executable($candidate)) {
                return $candidate;
            }
        }

        throw new RuntimeException(
            sprintf(
                'Unable to find Playwright runner command "%s". Set EXPERIENCE_MONITORING_RUNNER_COMMAND to a valid Node binary path.',
                $configuredCommand
            )
        );
    }

    /**
     * @return array<int, string>
     */
    private function fallbackNodeCandidates(): array
    {
        $candidates = [
            '/usr/local/bin/node',
            '/usr/bin/node',
            '/bin/node',
            '/snap/bin/node',
        ];

        $home = getenv('HOME');
        if (is_string($home) && $home !== '') {
            $nvmNodes = glob(rtrim($home, '/').'/.nvm/versions/node/*/bin/node');
            if (is_array($nvmNodes) && $nvmNodes !== []) {
                sort($nvmNodes, SORT_NATURAL);
                $candidates[] = end($nvmNodes) ?: '';
            }
        }

        return array_values(array_filter(array_unique($candidates)));
    }
}
