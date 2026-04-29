<?php

namespace App\Console\Commands\Traits;

use App\Support\ExtensionLoadDiagnosticLogger;
use Illuminate\Console\Command;

trait RunsAutomaticSmokeCheck
{
    protected function runAutomaticSmokeCheck(string $area, string $identifier, string $phase): void
    {
        $this->newLine();
        $this->line('Running smoke-check');

        $exitCode = $this->call('system:smoke-check');

        if ($exitCode === Command::SUCCESS) {
            return;
        }

        ExtensionLoadDiagnosticLogger::log(
            $area,
            $identifier,
            $phase,
            'error',
            'extension_boot_register_failure',
            null,
            null,
            ['smoke_check_exit_code' => $exitCode]
        );

        $this->warn("Automatic smoke-check failed after {$identifier}");
    }
}
