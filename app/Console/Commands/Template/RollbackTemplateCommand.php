<?php

namespace App\Console\Commands\Template;

use App\Console\Commands\Traits\RunsAutomaticSmokeCheck;
use App\Support\ExtensionLoadDiagnosticLogger;
use App\Support\TemplateReleaseHistory;
use Illuminate\Console\Command;

class RollbackTemplateCommand extends Command
{
    use RunsAutomaticSmokeCheck;

    protected $signature = 'template:rollback {identifier : Template identifier} {--to= : Snapshot timestamp}';

    protected $description = 'Rollback a template to a stored release snapshot';

    public function __construct(
        private TemplateReleaseHistory $templateReleaseHistory
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $identifier = (string) $this->argument('identifier');
        $timestamp = $this->option('to');

        try {
            $selectedTimestamp = $this->templateReleaseHistory->rollback($identifier, is_string($timestamp) && $timestamp !== '' ? $timestamp : null);
            $this->info("Template rolled back: {$identifier} ({$selectedTimestamp})");
            ExtensionLoadDiagnosticLogger::log('template', $identifier, 'rollback', 'info', 'template_rollback_completed', base_path("templates/{$identifier}"), null, ['timestamp' => $selectedTimestamp]);
        } catch (\Throwable $e) {
            ExtensionLoadDiagnosticLogger::log('template', $identifier, 'rollback', 'error', 'template_rollback_failed', storage_path("app/template-releases/{$identifier}"), $e);
            $this->error($e->getMessage());

            return Command::FAILURE;
        }

        if ($this->call('config:clear') !== Command::SUCCESS) {
            return Command::FAILURE;
        }

        if ($this->call('cache:clear') !== Command::SUCCESS) {
            return Command::FAILURE;
        }

        if ($this->call('view:clear') !== Command::SUCCESS) {
            return Command::FAILURE;
        }

        $this->runAutomaticSmokeCheck('template', $identifier, 'post.rollback.smoke_check');

        return Command::SUCCESS;
    }
}
