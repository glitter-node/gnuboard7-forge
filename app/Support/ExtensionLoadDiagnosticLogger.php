<?php

namespace App\Support;

use Illuminate\Support\Facades\Log;
use Throwable;

class ExtensionLoadDiagnosticLogger
{
    public static function log(
        string $area,
        string $identifier,
        string $phase,
        string $severity,
        string $event,
        ?string $path = null,
        ?Throwable $exception = null,
        array $context = []
    ): void {
        $level = self::normalizeSeverity($severity);
        $payload = array_filter(array_merge([
            'area' => $area,
            'identifier' => $identifier,
            'phase' => $phase,
            'severity' => $level,
            'event' => $event,
            'path' => $path,
            'exception_class' => $exception?->getMessage() !== null ? $exception::class : null,
            'exception_message' => $exception?->getMessage(),
        ], $context), static fn ($value) => $value !== null && $value !== []);

        try {
            Log::log($level, 'Extension load diagnostic', $payload);
        } catch (Throwable) {
        }

        try {
            @file_put_contents(
                storage_path('logs/extension-load-diagnostics.log'),
                json_encode([
                    'timestamp' => date('c'),
                    'message' => 'Extension load diagnostic',
                    ...$payload,
                ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE).PHP_EOL,
                FILE_APPEND | LOCK_EX
            );
        } catch (Throwable) {
        }
    }

    private static function normalizeSeverity(string $severity): string
    {
        return in_array($severity, ['debug', 'info', 'notice', 'warning', 'error', 'critical', 'alert', 'emergency'], true)
            ? $severity
            : 'warning';
    }
}
