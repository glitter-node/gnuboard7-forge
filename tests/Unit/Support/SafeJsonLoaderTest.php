<?php

namespace Tests\Unit\Support;

use App\Support\SafeJsonLoader;
use Tests\TestCase;

class SafeJsonLoaderTest extends TestCase
{
    public function test_it_returns_structured_error_for_missing_file(): void
    {
        $result = SafeJsonLoader::load(base_path('tests/Fixtures/missing.json'));

        $this->assertFalse($result['success']);
        $this->assertSame('file_not_found', $result['error']);
        $this->assertNull($result['data']);
    }

    public function test_it_returns_structured_error_for_invalid_json(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'safe-json-invalid-');
        file_put_contents($path, '{"broken": }');

        try {
            $result = SafeJsonLoader::load($path);

            $this->assertFalse($result['success']);
            $this->assertSame('invalid_json', $result['error']);
            $this->assertNull($result['data']);
        } finally {
            @unlink($path);
        }
    }

    public function test_it_returns_decoded_json_data(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'safe-json-valid-');
        file_put_contents($path, '{"components":[{"name":"Hero"}]}');

        try {
            $result = SafeJsonLoader::load($path);

            $this->assertTrue($result['success']);
            $this->assertNull($result['error']);
            $this->assertSame(['components' => [['name' => 'Hero']]], $result['data']);
        } finally {
            @unlink($path);
        }
    }
}
