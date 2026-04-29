<?php

namespace Tests\Unit\Installer;

use PHPUnit\Framework\TestCase;

class BundledTemplatePackageValidationTest extends TestCase
{
    private string $templateId;

    private string $bundledTemplatePath;

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

        $this->templateId = 'test-installer-template-' . uniqid();
        $this->bundledTemplatePath = BASE_PATH . '/templates/_bundled/' . $this->templateId;
    }

    protected function tearDown(): void
    {
        $this->deleteDirectory($this->bundledTemplatePath);
        parent::tearDown();
    }

    public function test_validate_bundled_template_package_accepts_complete_runtime_package(): void
    {
        $this->createBundledTemplatePackage();

        $result = validateBundledTemplatePackage($this->templateId);

        $this->assertTrue($result['valid']);
        $this->assertSame([], $result['missing']);
    }

    public function test_validate_bundled_template_package_reports_missing_runtime_files(): void
    {
        $this->createBundledTemplatePackage();
        unlink($this->bundledTemplatePath . '/dist/js/components.iife.js');
        unlink($this->bundledTemplatePath . '/dist/css/components.css');

        $result = validateBundledTemplatePackage($this->templateId);

        $this->assertFalse($result['valid']);
        $this->assertContains('dist/js/components.iife.js', $result['missing']);
        $this->assertContains('dist/css/components.css', $result['missing']);
    }

    public function test_validate_selected_bundled_templates_returns_failures_keyed_by_identifier(): void
    {
        $this->createBundledTemplatePackage();
        rmdir($this->bundledTemplatePath . '/lang');

        $failures = validateSelectedBundledTemplates([$this->templateId, 'nonexistent-template']);

        $this->assertArrayHasKey($this->templateId, $failures);
        $this->assertContains('lang', $failures[$this->templateId]);
        $this->assertArrayHasKey('nonexistent-template', $failures);
    }

    private function createBundledTemplatePackage(): void
    {
        @mkdir($this->bundledTemplatePath . '/dist/js', 0775, true);
        @mkdir($this->bundledTemplatePath . '/dist/css', 0775, true);
        @mkdir($this->bundledTemplatePath . '/layouts', 0775, true);
        @mkdir($this->bundledTemplatePath . '/lang', 0775, true);

        file_put_contents($this->bundledTemplatePath . '/dist/js/components.iife.js', 'window.TestTemplate = {};');
        file_put_contents($this->bundledTemplatePath . '/dist/css/components.css', 'body{}');
        file_put_contents($this->bundledTemplatePath . '/routes.json', '{}');
        file_put_contents($this->bundledTemplatePath . '/components.json', '{}');
    }

    private function deleteDirectory(string $path): void
    {
        if (! is_dir($path)) {
            return;
        }

        $items = array_diff(scandir($path), ['.', '..']);

        foreach ($items as $item) {
            $itemPath = $path . DIRECTORY_SEPARATOR . $item;

            if (is_dir($itemPath)) {
                $this->deleteDirectory($itemPath);
                continue;
            }

            @unlink($itemPath);
        }

        @rmdir($path);
    }
}
