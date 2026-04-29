<?php

namespace App\Console\Commands\Template;

use App\Contracts\Repositories\TemplateRepositoryInterface;
use App\Support\ExtensionLoadDiagnosticLogger;
use App\Support\TemplateReleaseHistory;
use Illuminate\Console\Command;
use Throwable;

class ReleaseTemplateCommand extends Command
{
    protected $signature = 'template:release
        {identifier : Template identifier}
        {--admin : Require the target template to be an admin template}
        {--skip-preflight : Skip template:preflight}
        {--skip-build : Skip template:build}
        {--skip-activate : Skip template:activate}
        {--skip-smoke : Skip system:smoke-check}';

    protected $description = 'Release a template through build, update, activate, cache clear, and smoke check';

    public function __construct(
        private TemplateRepositoryInterface $templateRepository,
        private TemplateReleaseHistory $templateReleaseHistory
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $identifier = (string) $this->argument('identifier');
        $steps = [];

        try {
            $template = $this->templateRepository->findByIdentifier($identifier);

            if ($this->option('admin') && $template && $template->type !== 'admin') {
                $this->error("Template {$identifier} is not an admin template");

                return Command::FAILURE;
            }

            if (! $this->option('skip-preflight')) {
                if (! $this->runStep($steps, 'preflight', 'template:preflight', ['identifier' => $identifier, '--admin' => (bool) $this->option('admin')])) {
                    return $this->failRelease($identifier, 'release.preflight', $steps);
                }
            } else {
                $this->markSkipped($steps, 'preflight');
            }

            if (! $this->option('skip-build')) {
                if (! $this->runStep($steps, 'build', 'template:build', ['identifier' => $identifier])) {
                    return $this->failRelease($identifier, 'release.build', $steps);
                }
            } else {
                $this->markSkipped($steps, 'build');
            }

            if (! $this->snapshotTemplate($steps, $identifier, $template?->version)) {
                return $this->failRelease($identifier, 'release.snapshot', $steps);
            }

            if (! $this->runStep($steps, 'update', 'template:update', ['identifier' => $identifier, '--force' => true, '--source' => 'bundled', '--skip-auto-smoke' => true])) {
                return $this->failRelease($identifier, 'release.update', $steps);
            }

            if (! $this->option('skip-activate')) {
                $activeTemplate = $template ? $this->templateRepository->findActiveByType($template->type) : null;

                if ($activeTemplate && $activeTemplate->identifier === $identifier) {
                    $this->markSkipped($steps, 'activate');
                } elseif (! $this->runStep($steps, 'activate', 'template:activate', ['identifier' => $identifier, '--skip-auto-smoke' => true])) {
                    ExtensionLoadDiagnosticLogger::log('template', $identifier, 'release.activate', 'error', 'extension_boot_register_failure', base_path("templates/{$identifier}"));

                    return $this->failRelease($identifier, 'release.activate', $steps);
                }
            } else {
                $this->markSkipped($steps, 'activate');
            }

            if (! $this->runStep($steps, 'config:clear', 'config:clear')) {
                return $this->failRelease($identifier, 'release.config_clear', $steps);
            }

            if (! $this->runStep($steps, 'cache:clear', 'cache:clear')) {
                return $this->failRelease($identifier, 'release.cache_clear', $steps);
            }

            if (! $this->runStep($steps, 'view:clear', 'view:clear')) {
                return $this->failRelease($identifier, 'release.view_clear', $steps);
            }

            if (! $this->option('skip-smoke')) {
                if (! $this->runStep($steps, 'smoke-check', 'system:smoke-check')) {
                    return $this->failRelease($identifier, 'release.smoke_check', $steps);
                }
            } else {
                $this->markSkipped($steps, 'smoke-check');
            }
        } catch (Throwable $e) {
            ExtensionLoadDiagnosticLogger::log('template', $identifier, 'release.exception', 'error', 'extension_boot_register_failure', null, $e);
            $this->renderSummary($steps);
            $this->error($e->getMessage());

            return Command::FAILURE;
        }

        $this->renderSummary($steps);
        $this->info("Template release completed: {$identifier}");

        return Command::SUCCESS;
    }

    private function runStep(array &$steps, string $name, string $command, array $arguments = []): bool
    {
        $this->line("Running {$name}");
        $exitCode = $this->call($command, $arguments);
        $passed = $exitCode === Command::SUCCESS;
        $steps[] = [
            'step' => $name,
            'result' => $passed ? 'PASS' : 'FAIL',
            'code' => (string) $exitCode,
        ];

        return $passed;
    }

    private function snapshotTemplate(array &$steps, string $identifier, ?string $version): bool
    {
        $this->line('Running snapshot');

        try {
            $timestamp = $this->templateReleaseHistory->createSnapshot($identifier, '_bundled', $version);
            $steps[] = [
                'step' => 'snapshot',
                'result' => 'PASS',
                'code' => $timestamp,
            ];

            return true;
        } catch (Throwable $e) {
            ExtensionLoadDiagnosticLogger::log('template', $identifier, 'release.snapshot', 'error', 'extension_boot_register_failure', base_path("templates/{$identifier}"), $e);
            $steps[] = [
                'step' => 'snapshot',
                'result' => 'FAIL',
                'code' => 'snapshot_error',
            ];

            return false;
        }
    }

    private function markSkipped(array &$steps, string $name): void
    {
        $steps[] = [
            'step' => $name,
            'result' => 'SKIP',
            'code' => '-',
        ];
    }

    private function failRelease(string $identifier, string $phase, array $steps): int
    {
        ExtensionLoadDiagnosticLogger::log(
            'template',
            $identifier,
            $phase,
            'error',
            'extension_boot_register_failure',
            null,
            null,
            ['steps' => $steps]
        );

        $this->renderSummary($steps);
        $this->error("Template release failed: {$identifier}");

        return Command::FAILURE;
    }

    private function renderSummary(array $steps): void
    {
        if ($steps === []) {
            return;
        }

        $this->newLine();
        $this->table(['Step', 'Result', 'Code'], $steps);
    }
}
