<?php

namespace App\Console\Commands\Plugin;

use App\Console\Commands\Traits\RunsAutomaticSmokeCheck;
use App\Extension\PluginManager;
use App\Support\ExtensionLoadDiagnosticLogger;
use Illuminate\Console\Command;

class ReloadPluginCommand extends Command
{
    use RunsAutomaticSmokeCheck;

    protected $signature = 'plugin:reload {identifier : Plugin identifier}';

    protected $description = 'Reload a plugin and run smoke-check';

    public function __construct(
        private PluginManager $pluginManager
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $identifier = (string) $this->argument('identifier');

        if ($this->call('plugin:preflight', ['identifier' => $identifier]) !== Command::SUCCESS) {
            return Command::FAILURE;
        }

        try {
            $this->pluginManager->loadPlugins();
        } catch (\Throwable $e) {
            ExtensionLoadDiagnosticLogger::log('plugin', $identifier, 'reload', 'error', 'extension_boot_register_failure', base_path("plugins/{$identifier}"), $e);
            $this->error($e->getMessage());

            return Command::FAILURE;
        }

        $this->info("Plugin reloaded: {$identifier}");
        $this->runAutomaticSmokeCheck('plugin', $identifier, 'post.reload.smoke_check');

        return Command::SUCCESS;
    }
}
