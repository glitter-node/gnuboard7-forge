<?php

namespace App\Support;

use ErrorException;
use JsonException;

class SafeJsonLoader
{
    /**
     * @return array{success: bool, data: array|null, error: string|null}
     */
    public static function load(string $path): array
    {
        $exists = self::runFilesystemOperation(static fn () => is_file($path));
        if (! $exists['success']) {
            return self::failure($exists['error']);
        }

        if (! $exists['value']) {
            return self::failure('file_not_found');
        }

        $content = self::runFilesystemOperation(static fn () => file_get_contents($path));
        if (! $content['success']) {
            return self::failure($content['error']);
        }

        if (! is_string($content['value'])) {
            return self::failure('read_failed');
        }

        try {
            $data = json_decode($content['value'], true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            return self::failure('invalid_json');
        }

        return [
            'success' => true,
            'data' => is_array($data) ? $data : [],
            'error' => null,
        ];
    }

    /**
     * @return array{success: bool, value: mixed, error: string|null}
     */
    private static function runFilesystemOperation(callable $callback): array
    {
        set_error_handler(static function (int $severity, string $message, string $file, int $line): never {
            throw new ErrorException($message, 0, $severity, $file, $line);
        });

        try {
            return [
                'success' => true,
                'value' => $callback(),
                'error' => null,
            ];
        } catch (ErrorException $e) {
            return [
                'success' => false,
                'value' => null,
                'error' => self::mapFilesystemError($e->getMessage()),
            ];
        } finally {
            restore_error_handler();
        }
    }

    private static function mapFilesystemError(string $message): string
    {
        if (str_contains($message, 'Permission denied') || str_contains($message, 'open_basedir restriction in effect')) {
            return 'permission_denied';
        }

        if (str_contains($message, 'No such file or directory')) {
            return 'file_not_found';
        }

        return 'read_failed';
    }

    /**
     * @return array{success: bool, data: array|null, error: string}
     */
    private static function failure(string $error): array
    {
        return [
            'success' => false,
            'data' => null,
            'error' => $error,
        ];
    }
}
