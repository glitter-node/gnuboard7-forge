<?php

namespace Tests\Unit\Installer;

use PHPUnit\Framework\TestCase;

class InstallationCompletionDetectionTest extends TestCase
{
    private string $envPath;

    protected function setUp(): void
    {
        parent::setUp();

        if (! defined('BASE_PATH')) {
            define('BASE_PATH', dirname(__DIR__, 2).'/..');
        }
        if (! defined('REQUIRED_DIRECTORY_PERMISSIONS')) {
            define('REQUIRED_DIRECTORY_PERMISSIONS', 0770);
        }
        if (! defined('REQUIRED_DIRECTORY_PERMISSIONS_DISPLAY')) {
            define('REQUIRED_DIRECTORY_PERMISSIONS_DISPLAY', '770');
        }
        if (! defined('REQUIRED_DIRECTORIES')) {
            define('REQUIRED_DIRECTORIES', ['storage' => true]);
        }
        if (! defined('SUPPORTED_LANGUAGES')) {
            define('SUPPORTED_LANGUAGES', ['ko' => '한국어', 'en' => 'English']);
        }
        if (! defined('INSTALLER_BASE_URL')) {
            define('INSTALLER_BASE_URL', '/install');
        }

        require_once BASE_PATH . '/public/install/includes/functions.php';

        $this->envPath = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'g7_installer_env_' . uniqid() . '.env';
    }

    protected function tearDown(): void
    {
        if (file_exists($this->envPath)) {
            @unlink($this->envPath);
        }

        parent::tearDown();
    }

    public function test_parse_installer_env_file_reads_db_values(): void
    {
        file_put_contents($this->envPath, implode("\n", [
            'DB_WRITE_HOST=localhost',
            'DB_WRITE_PORT=3307',
            'DB_WRITE_DATABASE=test_db',
            'DB_WRITE_USERNAME=test_user',
            'DB_WRITE_PASSWORD="secret"',
            'DB_PREFIX=test_',
        ]));

        $config = getInstallerDatabaseConfigFromEnvFile($this->envPath);

        $this->assertSame('localhost', $config['host']);
        $this->assertSame('3307', $config['port']);
        $this->assertSame('test_db', $config['database']);
        $this->assertSame('test_user', $config['username']);
        $this->assertSame('secret', $config['password']);
        $this->assertSame('test_', $config['prefix']);
    }

    public function test_database_initialized_check_returns_false_when_db_config_is_incomplete(): void
    {
        file_put_contents($this->envPath, "DB_WRITE_HOST=\nDB_WRITE_DATABASE=\nDB_WRITE_USERNAME=\n");

        $this->assertFalse(isInstallerDatabaseInitialized(getInstallerDatabaseConfigFromEnvFile($this->envPath)));
    }
}
