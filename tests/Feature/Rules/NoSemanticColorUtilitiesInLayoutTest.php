<?php

namespace Tests\Feature\Rules;

use App\Models\Template;
use App\Rules\NoSemanticColorUtilitiesInLayout;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NoSemanticColorUtilitiesInLayoutTest extends TestCase
{
    use RefreshDatabase;

    public function test_blocks_direct_forbidden_class_in_class_name(): void
    {
        [$failed, $message] = $this->validateLayoutWithClass('bg-red-500');

        $this->assertTrue($failed);
        $this->assertStringContainsString('bg-red-500', $message);
        $this->assertStringContainsString('layout.components[0].props.className', $message);
        $this->assertStringContainsString('variant', $message);
    }

    public function test_blocks_prefixed_forbidden_class(): void
    {
        [$failed, $message] = $this->validateLayoutWithClass('hover:bg-red-500');

        $this->assertTrue($failed);
        $this->assertStringContainsString('hover:bg-red-500', $message);
    }

    public function test_blocks_multi_prefixed_forbidden_class(): void
    {
        [$failed, $message] = $this->validateLayoutWithClass('sm:hover:border-danger-500');

        $this->assertTrue($failed);
        $this->assertStringContainsString('sm:hover:border-danger-500', $message);
    }

    public function test_blocks_dark_prefixed_text_color(): void
    {
        [$failed, $message] = $this->validateLayoutWithClass('dark:text-green-400');

        $this->assertTrue($failed);
        $this->assertStringContainsString('dark:text-green-400', $message);
    }

    public function test_allows_structural_classes(): void
    {
        [$failed] = $this->validateLayoutWithClass('flex grid gap-2 px-4 py-2 rounded-lg transition-colors cursor-pointer hover:opacity-80 focus:ring-2');

        $this->assertFalse($failed);
    }

    public function test_allows_typography_size_classes(): void
    {
        [$failed] = $this->validateLayoutWithClass('text-sm text-base text-lg font-semibold');

        $this->assertFalse($failed);
    }

    public function test_allows_gray_and_slate_layout_color_classes(): void
    {
        [$failed] = $this->validateLayoutWithClass('border border-t border-b border-gray-200 dark:border-gray-700 text-slate-700 dark:text-slate-300');

        $this->assertFalse($failed);
    }

    public function test_blocks_nested_layout_node_class(): void
    {
        $layout = $this->baseLayout([
            [
                'type' => 'layout',
                'name' => 'Div',
                'children' => [
                    [
                        'type' => 'basic',
                        'name' => 'Button',
                        'props' => [
                            'className' => 'gap-2 text-primary-600',
                        ],
                    ],
                ],
            ],
        ]);

        [$failed, $message] = $this->validateLayout($layout);

        $this->assertTrue($failed);
        $this->assertStringContainsString('text-primary-600', $message);
        $this->assertStringContainsString('layout.components[0].children[0].props.className', $message);
    }

    public function test_blocks_class_map_values(): void
    {
        $layout = $this->baseLayout([
            [
                'type' => 'basic',
                'name' => 'Badge',
                'classMap' => [
                    'base' => 'px-2 py-1 rounded-full',
                    'variants' => [
                        'active' => 'bg-green-100 text-green-700',
                    ],
                    'key' => '{{status}}',
                    'default' => 'border-gray-200',
                ],
            ],
        ]);

        [$failed, $message] = $this->validateLayout($layout);

        $this->assertTrue($failed);
        $this->assertStringContainsString('bg-green-100', $message);
        $this->assertStringContainsString('layout.components[0].classMap.variants.active', $message);
    }

    public function test_blocks_responsive_class_name_values(): void
    {
        $layout = $this->baseLayout([
            [
                'type' => 'basic',
                'name' => 'Button',
                'props' => [
                    'responsive' => [
                        'sm' => [
                            'className' => 'gap-2 bg-blue-500',
                        ],
                    ],
                ],
            ],
        ]);

        [$failed, $message] = $this->validateLayout($layout);

        $this->assertTrue($failed);
        $this->assertStringContainsString('bg-blue-500', $message);
        $this->assertStringContainsString('layout.components[0].props.responsive.sm.className', $message);
    }

    public function test_applies_only_to_sirsoft_comm_template_context(): void
    {
        $template = Template::query()->firstOrCreate(
            ['identifier' => 'sirsoft-comm'],
            Template::factory()->make(['identifier' => 'sirsoft-comm'])->toArray()
        );
        $layout = $this->baseLayoutWithClass('bg-red-500');
        $rule = new NoSemanticColorUtilitiesInLayout;
        $rule->setData(['template_id' => $template->id]);

        [$failed] = $this->runRule($rule, $layout);

        $this->assertTrue($failed);
    }

    public function test_ignores_unrelated_template_context(): void
    {
        $template = Template::factory()->create(['identifier' => 'other-template']);
        $layout = $this->baseLayoutWithClass('bg-red-500');
        $rule = new NoSemanticColorUtilitiesInLayout;
        $rule->setData(['template_id' => $template->id]);

        [$failed] = $this->runRule($rule, $layout);

        $this->assertFalse($failed);
    }

    private function validateLayoutWithClass(string $className): array
    {
        return $this->validateLayout($this->baseLayoutWithClass($className));
    }

    private function validateLayout(array $layout): array
    {
        return $this->runRule(new NoSemanticColorUtilitiesInLayout('sirsoft-comm'), $layout);
    }

    private function runRule(NoSemanticColorUtilitiesInLayout $rule, array $layout): array
    {
        $failed = false;
        $message = '';

        $rule->validate('layout', $layout, function (string $error) use (&$failed, &$message): void {
            $failed = true;
            $message = $error;
        });

        return [$failed, $message];
    }

    private function baseLayoutWithClass(string $className): array
    {
        return $this->baseLayout([
            [
                'type' => 'basic',
                'name' => 'Button',
                'props' => [
                    'className' => $className,
                ],
            ],
        ]);
    }

    private function baseLayout(array $components): array
    {
        return [
            'version' => '1.0.0',
            'layout_name' => 'test',
            'components' => $components,
        ];
    }
}
