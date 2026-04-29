<?php

namespace App\Support;

use Illuminate\Support\Facades\File;

class TemplateReleaseHistory
{
    public function createSnapshot(string $identifier, string $source = '_bundled', ?string $version = null): string
    {
        $activeTemplatePath = base_path("templates/{$identifier}");

        if (! File::isDirectory($activeTemplatePath)) {
            throw new \RuntimeException("Active template directory not found: {$identifier}");
        }

        $timestamp = now()->format('YmdHis');
        $releasePath = $this->getReleasePath($identifier, $timestamp);
        $snapshotPath = "{$releasePath}/template";

        File::ensureDirectoryExists($releasePath);

        if (! File::copyDirectory($activeTemplatePath, $snapshotPath)) {
            throw new \RuntimeException("Failed to snapshot template directory: {$identifier}");
        }

        $metadata = [
            'timestamp' => $timestamp,
            'identifier' => $identifier,
            'source' => $source,
            'version' => $version,
            'hash' => $this->hashDirectory($snapshotPath),
        ];

        File::put("{$releasePath}/metadata.json", json_encode($metadata, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));

        return $timestamp;
    }

    public function resolveSnapshotPath(string $identifier, ?string $timestamp = null): string
    {
        $selectedTimestamp = $timestamp ?? $this->latestTimestamp($identifier);

        if ($selectedTimestamp === null) {
            throw new \RuntimeException("No snapshot found for template: {$identifier}");
        }

        $releasePath = $this->getReleasePath($identifier, $selectedTimestamp);
        $snapshotPath = "{$releasePath}/template";

        if (! File::isDirectory($snapshotPath)) {
            throw new \RuntimeException("Snapshot not found for template: {$identifier} ({$selectedTimestamp})");
        }

        return $snapshotPath;
    }

    public function getReleaseMetadata(string $identifier, string $timestamp): ?array
    {
        $metadataPath = $this->getReleasePath($identifier, $timestamp).'/metadata.json';

        if (! File::isFile($metadataPath)) {
            return null;
        }

        try {
            $decoded = json_decode((string) File::get($metadataPath), true, 512, JSON_THROW_ON_ERROR);
        } catch (\Throwable) {
            return null;
        }

        return is_array($decoded) ? $decoded : null;
    }

    public function latestTimestamp(string $identifier): ?string
    {
        $historyPath = $this->getHistoryPath($identifier);

        if (! File::isDirectory($historyPath)) {
            return null;
        }

        $directories = array_map('basename', File::directories($historyPath));
        $timestamps = array_values(array_filter($directories, static fn (string $value) => preg_match('/^\d{14}$/', $value) === 1));

        if ($timestamps === []) {
            return null;
        }

        rsort($timestamps, SORT_STRING);

        return $timestamps[0];
    }

    public function rollback(string $identifier, ?string $timestamp = null): string
    {
        $selectedTimestamp = $timestamp ?? $this->latestTimestamp($identifier);

        if ($selectedTimestamp === null) {
            throw new \RuntimeException("No snapshot found for template: {$identifier}");
        }

        $snapshotPath = $this->resolveSnapshotPath($identifier, $selectedTimestamp);
        $activeTemplatePath = base_path("templates/{$identifier}");
        $workPath = storage_path('app/template-releases/.rollback-work');
        $backupPath = "{$workPath}/{$identifier}-".now()->format('YmdHis');

        File::ensureDirectoryExists($workPath);

        if (File::exists($backupPath)) {
            File::deleteDirectory($backupPath);
        }

        $currentExists = File::isDirectory($activeTemplatePath);

        if ($currentExists && ! File::moveDirectory($activeTemplatePath, $backupPath)) {
            throw new \RuntimeException("Failed to move current template directory: {$identifier}");
        }

        try {
            if (! File::copyDirectory($snapshotPath, $activeTemplatePath)) {
                throw new \RuntimeException("Failed to restore snapshot for template: {$identifier}");
            }
        } catch (\Throwable $e) {
            if (File::isDirectory($activeTemplatePath)) {
                File::deleteDirectory($activeTemplatePath);
            }

            if ($currentExists && File::isDirectory($backupPath)) {
                File::moveDirectory($backupPath, $activeTemplatePath);
            }

            throw $e;
        }

        if (File::isDirectory($backupPath)) {
            File::deleteDirectory($backupPath);
        }

        return $selectedTimestamp;
    }

    private function getHistoryPath(string $identifier): string
    {
        return storage_path("app/template-releases/{$identifier}");
    }

    private function getReleasePath(string $identifier, string $timestamp): string
    {
        return $this->getHistoryPath($identifier)."/{$timestamp}";
    }

    private function hashDirectory(string $directory): string
    {
        $files = collect(File::allFiles($directory))
            ->map(fn (\SplFileInfo $file) => $file->getPathname())
            ->sort()
            ->values()
            ->all();

        $context = hash_init('sha256');

        foreach ($files as $file) {
            $relativePath = ltrim(str_replace($directory, '', $file), DIRECTORY_SEPARATOR);
            hash_update($context, $relativePath);
            hash_update($context, hash_file('sha256', $file) ?: '');
        }

        return hash_final($context);
    }
}
