<?php

namespace Tests\Unit\Services;

use App\Contracts\Extension\CacheInterface;
use App\Contracts\Repositories\AttachmentRepositoryInterface;
use App\Contracts\Repositories\ConfigRepositoryInterface;
use App\Services\SettingsService;
use Tests\TestCase;

class SettingsServiceSystemInfoTest extends TestCase
{
    public function test_get_system_info_returns_fallbacks_when_system_metrics_fail(): void
    {
        unset($_SERVER['SERVER_SOFTWARE']);

        $service = new class(
            $this->mock(ConfigRepositoryInterface::class)->shouldIgnoreMissing(),
            $this->mock(AttachmentRepositoryInterface::class)->shouldIgnoreMissing(),
            $this->mock(CacheInterface::class)->shouldIgnoreMissing()
        ) extends SettingsService {
            protected function getCpuInfo(): string
            {
                throw new \RuntimeException('cpu unavailable');
            }

            protected function getMemoryUsage(): string
            {
                throw new \RuntimeException('memory unavailable');
            }

            protected function getDiskUsage(): array
            {
                throw new \RuntimeException('disk unavailable');
            }

            public function getDatabaseConfig(): array
            {
                throw new \RuntimeException('db config unavailable');
            }

            public function getPhpExtensions(): array
            {
                throw new \RuntimeException('extensions unavailable');
            }
        };

        $result = $service->getSystemInfo();

        $this->assertArrayHasKey('mysql_version', $result);
        $this->assertIsString($result['mysql_version']);
        $this->assertNotSame('', $result['mysql_version']);
        $this->assertArrayHasKey('web_server', $result);
        $this->assertSame('Unknown', $result['web_server']);
        $this->assertSame('Unknown', $result['cpu_info']);
        $this->assertSame('Unknown', $result['memory_usage']);
        $this->assertSame([
            'total' => null,
            'used' => null,
            'free' => null,
            'percentage' => null,
        ], $result['disk_usage']);
        $this->assertSame([
            'required' => [],
            'optional' => [],
        ], $result['php_extensions']);
        $this->assertSame([
            'has_read_write_split' => false,
            'write' => [
                'host' => 'unknown',
                'port' => null,
                'database' => 'unknown',
                'username' => 'unknown',
            ],
            'read' => [],
        ], $result['database_config']);
    }
}
