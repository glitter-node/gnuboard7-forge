<?php

namespace App\Console\Commands\Module;

use App\Console\Commands\Traits\RunsAutomaticSmokeCheck;
use App\Extension\ModuleManager;
use App\Support\ExtensionLoadDiagnosticLogger;
use Illuminate\Console\Command;

class ReloadModuleCommand extends Command
{
    use RunsAutomaticSmokeCheck;

    protected $signature = 'module:reload {identifier : Module identifier}';

    protected $description = 'Reload a module and run smoke-check';

    public function __construct(
        private ModuleManager $moduleManager
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $identifier = (string) $this->argument('identifier');

        if ($this->call('module:preflight', ['identifier' => $identifier]) !== Command::SUCCESS) {
            return Command::FAILURE;
        }

        try {
            $this->moduleManager->loadModules();
        } catch (\Throwable $e) {
            ExtensionLoadDiagnosticLogger::log('module', $identifier, 'reload', 'error', 'extension_boot_register_failure', base_path("modules/{$identifier}"), $e);
            $this->error($e->getMessage());

            return Command::FAILURE;
        }

        $this->info("Module reloaded: {$identifier}");
        $this->runAutomaticSmokeCheck('module', $identifier, 'post.reload.smoke_check');

        return Command::SUCCESS;
    }
}
