<?php

namespace App\Http\Requests\Public\Template;

use App\Contracts\Extension\TemplateManagerInterface;
use App\Rules\AllowedTemplateFileType;
use App\Rules\SafeTemplatePath;
use Illuminate\Foundation\Http\FormRequest;

class ServeTemplateAssetRequest extends FormRequest
{
    /**
     * 사용자가 이 요청을 수행할 권한이 있는지 확인
     *
     * 권한 체크는 라우트의 permission 미들웨어에서 수행됩니다.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * 요청에 적용할 검증 규칙
     */
    public function rules(): array
    {
        // 템플릿 식별자로부터 실제 활성 템플릿 dist 기준 경로 구성
        $identifier = $this->route('identifier');
        $templateManager = app(TemplateManagerInterface::class);
        $templateData = $templateManager->getTemplate($identifier);
        $candidateRoots = array_filter([
            $templateData['_paths']['root'] ?? null,
            base_path("templates/{$identifier}"),
            base_path("templates/_pending/{$identifier}"),
            base_path("templates/_bundled/{$identifier}"),
        ]);
        $templateRoot = base_path("templates/{$identifier}");
        foreach ($candidateRoots as $candidateRoot) {
            if (is_dir($candidateRoot)) {
                $templateRoot = $candidateRoot;
                break;
            }
        }
        $basePath = rtrim($templateRoot, DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR.'dist';

        return [
            'identifier' => ['required', 'string'],
            'path' => [
                'required',
                'string',
                new SafeTemplatePath($basePath),
                new AllowedTemplateFileType(),
            ],
        ];
    }

    /**
     * 검증을 위한 데이터 준비
     */
    protected function prepareForValidation(): void
    {
        // 라우트 파라미터를 검증 데이터에 병합
        $this->merge([
            'identifier' => $this->route('identifier'),
            'path' => $this->route('path'),
        ]);
    }

    /**
     * 검증 오류 메시지 커스터마이징
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'identifier.required' => __('validation.asset.identifier.required'),
            'identifier.string' => __('validation.asset.identifier.string'),
            'path.required' => __('validation.asset.path.required'),
            'path.string' => __('validation.asset.path.string'),
        ];
    }
}
