<?php

namespace App\Console\Commands\System;

use App\Services\TemplateService;
use App\Support\ExtensionLoadDiagnosticLogger;
use Illuminate\Console\Command;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Throwable;

class SmokeCheckCommand extends Command
{
    protected $signature = 'system:smoke-check';

    protected $description = 'Run critical system smoke checks';

    public function handle(TemplateService $templateService): int
    {
        try {
            $userTemplate = $templateService->getActiveTemplateIdentifier('user');
            $adminTemplate = $templateService->getActiveTemplateIdentifier('admin');
        } catch (Throwable $e) {
            ExtensionLoadDiagnosticLogger::log('core', 'smoke-check', 'resolve.templates', 'error', 'active_template_not_found', null, $e);
            $this->error('Failed to resolve active templates');
            $this->line($e->getMessage());

            return Command::FAILURE;
        }

        $checks = [
            $this->makeCheck('/', [200], false),
            $this->makeCheck('/admin/dashboard', [200], false),
            $this->makeCheck("/api/templates/{$userTemplate}/routes.json", [200], true),
            $this->makeCheck("/api/layouts/{$adminTemplate}/admin_dashboard.json", [200, 401], true),
            $this->makeCheck('/api/admin/dashboard/resources', [200, 401], true),
        ];

        $results = [];
        $hasFailure = false;

        foreach ($checks as $check) {
            $result = $this->runCheck($check);
            $results[] = [
                'route' => $result['route'],
                'status' => (string) $result['status'],
                'pass/fail' => $result['passed'] ? 'PASS' : 'FAIL',
            ];

            if (! $result['passed']) {
                $hasFailure = true;
            }
        }

        $this->table(['Route', 'Status', 'Pass/Fail'], $results);

        if ($hasFailure) {
            $this->error('Smoke check failed');

            return Command::FAILURE;
        }

        $this->info('Smoke check passed');

        return Command::SUCCESS;
    }

    private function makeCheck(string $route, array $expectedStatuses, bool $expectsJson): array
    {
        return [
            'route' => $route,
            'expected_statuses' => $expectedStatuses,
            'expects_json' => $expectsJson,
        ];
    }

    private function runCheck(array $check): array
    {
        $url = $this->resolveUrl($check['route']);

        try {
            $request = Http::timeout(15)->acceptJson();

            if ($this->shouldSkipTlsVerification($url)) {
                $request = $request->withoutVerifying();
            }

            $response = $request->get($url);
            $status = $response->status();

            $passed = in_array($status, $check['expected_statuses'], true);

            if ($passed && $check['expects_json']) {
                json_decode($response->body(), true);
                $passed = json_last_error() === JSON_ERROR_NONE;
            }

            if (! $passed) {
                ExtensionLoadDiagnosticLogger::log(
                    'core',
                    $check['route'],
                    'smoke-check.http',
                    'error',
                    'extension_boot_register_failure',
                    $url,
                    null,
                    [
                        'http_status' => $status,
                        'expected_statuses' => $check['expected_statuses'],
                        'expects_json' => $check['expects_json'],
                        'json_valid' => $check['expects_json'] ? json_last_error() === JSON_ERROR_NONE : null,
                    ]
                );
            }

            return [
                'route' => $check['route'],
                'status' => $status,
                'passed' => $passed,
            ];
        } catch (ConnectionException $e) {
            ExtensionLoadDiagnosticLogger::log('core', $check['route'], 'smoke-check.http', 'error', 'extension_boot_register_failure', $url, $e);

            return [
                'route' => $check['route'],
                'status' => 'connection_error',
                'passed' => false,
            ];
        } catch (Throwable $e) {
            ExtensionLoadDiagnosticLogger::log('core', $check['route'], 'smoke-check.http', 'error', 'extension_boot_register_failure', $url, $e);

            return [
                'route' => $check['route'],
                'status' => 'exception',
                'passed' => false,
            ];
        }
    }

    private function resolveUrl(string $route): string
    {
        return rtrim((string) config('app.url'), '/').$route;
    }

    private function shouldSkipTlsVerification(string $url): bool
    {
        return str_starts_with($url, 'https://127.0.0.1') || str_starts_with($url, 'https://localhost');
    }
}
