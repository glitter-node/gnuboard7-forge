<?php

namespace App\Console\Commands\Template;

use App\Support\ExtensionLoadDiagnosticLogger;
use Illuminate\Console\Command;

class PreflightTemplateCommand extends Command
{
    protected $signature = 'template:preflight
        {identifier : Template identifier}
        {--admin : Require the target template to be an admin template}';

    protected $description = 'Validate a bundled template source before release';

    public function handle(): int
    {
        $identifier = (string) $this->argument('identifier');
        $templatePath = base_path("templates/_bundled/{$identifier}");
        $checks = [];
        $failed = false;
        $manifestData = null;

        if (! is_dir($templatePath)) {
            $failed = true;
            $this->pushResult($checks, 'bundled_template_directory', $templatePath, false);
            ExtensionLoadDiagnosticLogger::log('template', $identifier, 'preflight', 'error', 'active_bundled_source_mismatch', $templatePath);
        } else {
            $this->pushResult($checks, 'bundled_template_directory', $templatePath, true);
        }

        [$manifestData, $manifestOk] = $this->validateJsonFile(
            $identifier,
            $checks,
            'template.json',
            "{$templatePath}/template.json",
            'missing_manifest',
            'invalid_json_metadata'
        );
        $failed = $failed || ! $manifestOk;

        [, $routesOk] = $this->validateJsonFile(
            $identifier,
            $checks,
            'routes.json',
            "{$templatePath}/routes.json",
            'missing_routes_json',
            'invalid_json_metadata'
        );
        $failed = $failed || ! $routesOk;

        [, $componentsOk] = $this->validateJsonFile(
            $identifier,
            $checks,
            'components.json',
            "{$templatePath}/components.json",
            'missing_components_json',
            'invalid_json_metadata'
        );
        $failed = $failed || ! $componentsOk;

        $failed = $this->validateDirectory($identifier, $checks, 'src', "{$templatePath}/src", 'missing_src_directory') || $failed;
        $failed = $this->validateDirectory($identifier, $checks, 'layouts', "{$templatePath}/layouts", 'missing_layout_directory') || $failed;
        $failed = $this->validateDirectory($identifier, $checks, 'lang', "{$templatePath}/lang", 'missing_lang_directory') || $failed;

        $distPath = "{$templatePath}/dist";
        if (is_dir($distPath)) {
            $this->pushResult($checks, 'dist', $distPath, true);
        } else {
            $this->pushResult($checks, 'dist', $distPath, null);
        }

        if ($this->option('admin')) {
            $isAdmin = is_array($manifestData) && (($manifestData['type'] ?? null) === 'admin');
            $this->pushResult($checks, 'template_type', $templatePath, $isAdmin);

            if (! $isAdmin) {
                $failed = true;
                ExtensionLoadDiagnosticLogger::log(
                    'template',
                    $identifier,
                    'preflight',
                    'error',
                    'invalid_template_type',
                    "{$templatePath}/template.json",
                    null,
                    ['expected_type' => 'admin', 'actual_type' => is_array($manifestData) ? ($manifestData['type'] ?? null) : null]
                );
            }
        }

        $this->newLine();
        $this->table(['Check', 'Target', 'Result'], $checks);

        if ($failed) {
            $this->error("Template preflight failed: {$identifier}");

            return Command::FAILURE;
        }

        $this->info("Template preflight passed: {$identifier}");

        return Command::SUCCESS;
    }

    private function validateJsonFile(
        string $identifier,
        array &$checks,
        string $check,
        string $path,
        string $missingEvent,
        string $invalidEvent
    ): array {
        if (! is_file($path)) {
            $this->pushResult($checks, $check, $path, false);
            ExtensionLoadDiagnosticLogger::log('template', $identifier, 'preflight', 'error', $missingEvent, $path);

            return [null, false];
        }

        try {
            $content = file_get_contents($path);
        } catch (\Throwable $e) {
            $this->pushResult($checks, $check, $path, false);
            ExtensionLoadDiagnosticLogger::log('template', $identifier, 'preflight', 'error', $invalidEvent, $path, $e);

            return [null, false];
        }

        try {
            $decoded = json_decode($content ?: '', true, 512, JSON_THROW_ON_ERROR);
        } catch (\Throwable $e) {
            $this->pushResult($checks, $check, $path, false);
            ExtensionLoadDiagnosticLogger::log('template', $identifier, 'preflight', 'error', $invalidEvent, $path, $e);

            return [null, false];
        }

        $this->pushResult($checks, $check, $path, true);

        return [$decoded, true];
    }

    private function validateDirectory(
        string $identifier,
        array &$checks,
        string $check,
        string $path,
        string $event
    ): bool {
        $exists = is_dir($path);
        $this->pushResult($checks, $check, $path, $exists);

        if (! $exists) {
            ExtensionLoadDiagnosticLogger::log('template', $identifier, 'preflight', 'error', $event, $path);
        }

        return ! $exists;
    }

    private function pushResult(array &$checks, string $check, string $target, ?bool $passed): void
    {
        $checks[] = [
            'check' => $check,
            'target' => $target,
            'result' => $passed === null ? 'SKIP' : ($passed ? 'PASS' : 'FAIL'),
        ];
    }
}
