<?php

namespace Tests\Unit\Installer;

use PHPUnit\Framework\TestCase;

class DatabasePreparationTest extends TestCase
{
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
    }

    public function test_normalize_existing_db_action_maps_supported_aliases(): void
    {
        $this->assertSame('skip', normalizeExistingDbAction('skip'));
        $this->assertSame('skip', normalizeExistingDbAction('none'));
        $this->assertSame('drop_tables', normalizeExistingDbAction('drop_tables'));
        $this->assertSame('drop_tables', normalizeExistingDbAction('reset_tables'));
        $this->assertSame('skip', normalizeExistingDbAction('unexpected'));
    }

    public function test_get_prefixed_tables_from_table_list_filters_by_prefix(): void
    {
        $tables = [
            'g7_migrations',
            'g7_notification_logs',
            'other_table',
            'g7_users',
        ];

        $result = getPrefixedTablesFromTableList($tables, 'g7_');

        $this->assertSame([
            'g7_migrations',
            'g7_notification_logs',
            'g7_users',
        ], $result);
    }

    public function test_should_block_database_migration_only_when_skip_action_and_prefixed_tables_exist(): void
    {
        $prefixedTables = ['g7_migrations', 'g7_notification_logs'];

        $this->assertTrue(shouldBlockDatabaseMigration($prefixedTables, 'skip'));
        $this->assertTrue(shouldBlockDatabaseMigration($prefixedTables, 'none'));
        $this->assertFalse(shouldBlockDatabaseMigration($prefixedTables, 'drop_tables'));
        $this->assertFalse(shouldBlockDatabaseMigration([], 'skip'));
    }
}
