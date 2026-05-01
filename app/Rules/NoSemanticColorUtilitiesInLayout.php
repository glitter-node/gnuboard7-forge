<?php

namespace App\Rules;

use App\Models\Template;
use Closure;
use Illuminate\Contracts\Validation\DataAwareRule;
use Illuminate\Contracts\Validation\ValidationRule;

class NoSemanticColorUtilitiesInLayout implements DataAwareRule, ValidationRule
{
    private const SCOPED_TEMPLATE = 'sirsoft-comm';

    private const CLASS_KEYS = [
        'class',
        'className',
        'iconClassName',
        'headerClassName',
        'rowClassName',
        'cellClassName',
        'containerClassName',
    ];

    private array $data = [];

    public function __construct(private ?string $templateIdentifier = null)
    {
    }

    public function setData(array $data): static
    {
        $this->data = $data;

        return $this;
    }

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! $this->shouldValidate()) {
            return;
        }

        if (is_string($value)) {
            $decoded = json_decode($value, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                return;
            }
            $value = $decoded;
        }

        if (! is_array($value)) {
            return;
        }

        $violation = $this->findViolation($value, $attribute);

        if ($violation !== null) {
            $fail(__('validation.layout.semantic_color_utility_prohibited', $violation));
        }
    }

    private function shouldValidate(): bool
    {
        if ($this->templateIdentifier !== null) {
            return $this->templateIdentifier === self::SCOPED_TEMPLATE;
        }

        $templateId = $this->data['template_id'] ?? null;
        if (! is_numeric($templateId)) {
            return false;
        }

        return Template::query()
            ->whereKey((int) $templateId)
            ->where('identifier', self::SCOPED_TEMPLATE)
            ->exists();
    }

    private function findViolation(mixed $value, string $path): ?array
    {
        if (! is_array($value)) {
            return null;
        }

        foreach ($value as $key => $child) {
            $childPath = $this->appendPath($path, $key);

            if ($this->isClassStringField($key) && is_string($child)) {
                $token = $this->findForbiddenToken($child);
                if ($token !== null) {
                    return [
                        'token' => $token,
                        'path' => $childPath,
                    ];
                }
            }

            if ($key === 'classMap') {
                $violation = $this->findClassMapViolation($child, $childPath);
                if ($violation !== null) {
                    return $violation;
                }
            }

            if (is_array($child)) {
                $violation = $this->findViolation($child, $childPath);
                if ($violation !== null) {
                    return $violation;
                }
            }
        }

        return null;
    }

    private function findClassMapViolation(mixed $value, string $path): ?array
    {
        if (! is_array($value)) {
            return null;
        }

        foreach ($value as $key => $child) {
            if ($key === 'key') {
                continue;
            }

            $childPath = $this->appendPath($path, $key);

            if (is_string($child)) {
                $token = $this->findForbiddenToken($child);
                if ($token !== null) {
                    return [
                        'token' => $token,
                        'path' => $childPath,
                    ];
                }
            }

            if (is_array($child)) {
                $violation = $this->findClassMapViolation($child, $childPath);
                if ($violation !== null) {
                    return $violation;
                }
            }
        }

        return null;
    }

    private function isClassStringField(int|string $key): bool
    {
        if (! is_string($key)) {
            return false;
        }

        return in_array($key, self::CLASS_KEYS, true) || str_ends_with($key, 'ClassName');
    }

    private function findForbiddenToken(string $classes): ?string
    {
        $tokens = preg_split('/\s+/', trim($classes)) ?: [];

        foreach ($tokens as $token) {
            if ($token === '') {
                continue;
            }

            $segments = explode(':', $token);
            $utility = end($segments);

            if (is_string($utility) && $this->isForbiddenUtility($utility)) {
                return $token;
            }
        }

        return null;
    }

    private function isForbiddenUtility(string $utility): bool
    {
        return preg_match('/^(bg|text|border)-(red|green|blue|yellow|orange|amber|teal|primary|success|warning|danger|neutral)-.+$/', $utility) === 1;
    }

    private function appendPath(string $path, int|string $key): string
    {
        if (is_int($key)) {
            return "{$path}[{$key}]";
        }

        return "{$path}.{$key}";
    }
}
