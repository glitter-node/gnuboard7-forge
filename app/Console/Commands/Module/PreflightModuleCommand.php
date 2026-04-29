<?php

namespace App\Console\Commands\Module;

use App\Contracts\Extension\ModuleInterface;
use App\Extension\ExtensionManager;
use App\Support\ExtensionLoadDiagnosticLogger;
use Illuminate\Console\Command;

class PreflightModuleCommand extends Command
{
    protected $signature = 'module:preflight {identifier : Module identifier}';

    protected $description = 'Validate a module before reload or operation';

    public function handle(): int
    {
        $identifier = (string) $this->argument('identifier');
        $modulePath = base_path("modules/{$identifier}");
        $checks = [];
        $failed = false;

        $failed = $this->validateDirectory($identifier, $checks, 'module_directory', $modulePath, 'active_module_not_found') || $failed;
        [, $moduleJsonOk] = $this->validateJsonFile($identifier, $checks, 'module.json', "{$modulePath}/module.json", 'missing_manifest', 'invalid_json_metadata');
        $failed = $failed || ! $moduleJsonOk;
        $failed = $this->validateFile($identifier, $checks, 'module.php', "{$modulePath}/module.php", 'extension_class_not_found') || $failed;
        $failed = $this->validateModuleClass($identifier, $checks, $modulePath) || $failed;

        $this->pushOptionalDirectory($checks, 'resources', "{$modulePath}/resources");
        $this->pushOptionalDirectory($checks, 'dist', "{$modulePath}/dist");
        $this->pushOptionalDirectory($checks, 'config', "{$modulePath}/config");

        $this->newLine();
        $this->table(['Check', 'Target', 'Result'], $checks);

        if ($failed) {
            $this->error("Module preflight failed: {$identifier}");

            return Command::FAILURE;
        }

        $this->info("Module preflight passed: {$identifier}");

        return Command::SUCCESS;
    }

    private function validateJsonFile(string $identifier, array &$checks, string $check, string $path, string $missingEvent, string $invalidEvent): array
    {
        if (! is_file($path)) {
            $this->pushResult($checks, $check, $path, false);
            ExtensionLoadDiagnosticLogger::log('module', $identifier, 'preflight', 'error', $missingEvent, $path);

            return [null, false];
        }

        try {
            $content = file_get_contents($path);
            $decoded = json_decode($content ?: '', true, 512, JSON_THROW_ON_ERROR);
        } catch (\Throwable $e) {
            $this->pushResult($checks, $check, $path, false);
            ExtensionLoadDiagnosticLogger::log('module', $identifier, 'preflight', 'error', $invalidEvent, $path, $e);

            return [null, false];
        }

        $this->pushResult($checks, $check, $path, true);

        return [$decoded, true];
    }

    private function validateFile(string $identifier, array &$checks, string $check, string $path, string $event): bool
    {
        $exists = is_file($path);
        $this->pushResult($checks, $check, $path, $exists);

        if (! $exists) {
            ExtensionLoadDiagnosticLogger::log('module', $identifier, 'preflight', 'error', $event, $path);
        }

        return ! $exists;
    }

    private function validateDirectory(string $identifier, array &$checks, string $check, string $path, string $event): bool
    {
        $exists = is_dir($path);
        $this->pushResult($checks, $check, $path, $exists);

        if (! $exists) {
            ExtensionLoadDiagnosticLogger::log('module', $identifier, 'preflight', 'error', $event, $path);
        }

        return ! $exists;
    }

    private function validateModuleClass(string $identifier, array &$checks, string $modulePath): bool
    {
        $moduleFile = "{$modulePath}/module.php";
        $namespace = ExtensionManager::directoryToNamespace($identifier);
        $moduleClass = "Modules\\{$namespace}\\Module";

        if (! is_file($moduleFile)) {
            $this->pushResult($checks, 'module_class', $moduleClass, false);

            return true;
        }

        try {
            if (! class_exists($moduleClass, false)) {
                require_once $moduleFile;
            }
        } catch (\Throwable $e) {
            $this->pushResult($checks, 'module_class', $moduleClass, false);
            ExtensionLoadDiagnosticLogger::log('module', $identifier, 'preflight', 'error', 'extension_boot_register_failure', $moduleFile, $e);

            return true;
        }

        $resolvable = class_exists($moduleClass) && is_subclass_of($moduleClass, ModuleInterface::class);
        $this->pushResult($checks, 'module_class', $moduleClass, $resolvable);

        if (! $resolvable) {
            ExtensionLoadDiagnosticLogger::log('module', $identifier, 'preflight', 'error', 'extension_class_not_found', $moduleFile, null, ['class' => $moduleClass]);
        }

        return ! $resolvable;
    }

    private function pushOptionalDirectory(array &$checks, string $check, string $path): void
    {
        $this->pushResult($checks, $check, $path, is_dir($path) ? true : null);
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
