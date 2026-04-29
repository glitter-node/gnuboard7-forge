<?php

namespace App\Console\Commands\Plugin;

use App\Contracts\Extension\PluginInterface;
use App\Extension\ExtensionManager;
use App\Support\ExtensionLoadDiagnosticLogger;
use Illuminate\Console\Command;

class PreflightPluginCommand extends Command
{
    protected $signature = 'plugin:preflight {identifier : Plugin identifier}';

    protected $description = 'Validate a plugin before reload or operation';

    public function handle(): int
    {
        $identifier = (string) $this->argument('identifier');
        $pluginPath = base_path("plugins/{$identifier}");
        $checks = [];
        $failed = false;

        $failed = $this->validateDirectory($identifier, $checks, 'plugin_directory', $pluginPath, 'active_plugin_not_found') || $failed;
        [, $pluginJsonOk] = $this->validateJsonFile($identifier, $checks, 'plugin.json', "{$pluginPath}/plugin.json", 'missing_manifest', 'invalid_json_metadata');
        $failed = $failed || ! $pluginJsonOk;
        $failed = $this->validateFile($identifier, $checks, 'plugin.php', "{$pluginPath}/plugin.php", 'extension_class_not_found') || $failed;
        $failed = $this->validatePluginClass($identifier, $checks, $pluginPath) || $failed;

        $this->pushOptionalDirectory($checks, 'resources', "{$pluginPath}/resources");
        $this->pushOptionalDirectory($checks, 'dist', "{$pluginPath}/dist");
        $this->pushOptionalDirectory($checks, 'config', "{$pluginPath}/config");

        $this->newLine();
        $this->table(['Check', 'Target', 'Result'], $checks);

        if ($failed) {
            $this->error("Plugin preflight failed: {$identifier}");

            return Command::FAILURE;
        }

        $this->info("Plugin preflight passed: {$identifier}");

        return Command::SUCCESS;
    }

    private function validateJsonFile(string $identifier, array &$checks, string $check, string $path, string $missingEvent, string $invalidEvent): array
    {
        if (! is_file($path)) {
            $this->pushResult($checks, $check, $path, false);
            ExtensionLoadDiagnosticLogger::log('plugin', $identifier, 'preflight', 'error', $missingEvent, $path);

            return [null, false];
        }

        try {
            $content = file_get_contents($path);
            $decoded = json_decode($content ?: '', true, 512, JSON_THROW_ON_ERROR);
        } catch (\Throwable $e) {
            $this->pushResult($checks, $check, $path, false);
            ExtensionLoadDiagnosticLogger::log('plugin', $identifier, 'preflight', 'error', $invalidEvent, $path, $e);

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
            ExtensionLoadDiagnosticLogger::log('plugin', $identifier, 'preflight', 'error', $event, $path);
        }

        return ! $exists;
    }

    private function validateDirectory(string $identifier, array &$checks, string $check, string $path, string $event): bool
    {
        $exists = is_dir($path);
        $this->pushResult($checks, $check, $path, $exists);

        if (! $exists) {
            ExtensionLoadDiagnosticLogger::log('plugin', $identifier, 'preflight', 'error', $event, $path);
        }

        return ! $exists;
    }

    private function validatePluginClass(string $identifier, array &$checks, string $pluginPath): bool
    {
        $pluginFile = "{$pluginPath}/plugin.php";
        $namespace = ExtensionManager::directoryToNamespace($identifier);
        $pluginClass = "Plugins\\{$namespace}\\Plugin";

        if (! is_file($pluginFile)) {
            $this->pushResult($checks, 'plugin_class', $pluginClass, false);

            return true;
        }

        try {
            if (! class_exists($pluginClass, false)) {
                require_once $pluginFile;
            }
        } catch (\Throwable $e) {
            $this->pushResult($checks, 'plugin_class', $pluginClass, false);
            ExtensionLoadDiagnosticLogger::log('plugin', $identifier, 'preflight', 'error', 'extension_boot_register_failure', $pluginFile, $e);

            return true;
        }

        $resolvable = class_exists($pluginClass) && is_subclass_of($pluginClass, PluginInterface::class);
        $this->pushResult($checks, 'plugin_class', $pluginClass, $resolvable);

        if (! $resolvable) {
            ExtensionLoadDiagnosticLogger::log('plugin', $identifier, 'preflight', 'error', 'extension_class_not_found', $pluginFile, null, ['class' => $pluginClass]);
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
