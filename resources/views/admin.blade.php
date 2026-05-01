<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ config('app.name', '그누보드7') }} - Admin</title>

        <!-- Font Awesome CDN -->
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

        <!-- Fallback UI 스타일 -->
        @if(empty($activeAdminTemplate))
        @include('partials.error-fallback-styles')
        @endif

        <!-- 템플릿 컴포넌트 스타일 -->
        @if(!empty($activeAdminTemplate))
        <link rel="stylesheet" href="/api/templates/assets/{{ $activeAdminTemplate }}/css/components.css?v={{ time() }}">
        @endif
    </head>
    <body>
        <!-- React 렌더링 루트 -->
        <div id="app" data-template-id="{{ $activeAdminTemplate ?? '' }}">
            <!-- Progressive Enhancement: 템플릿 없음 Fallback UI -->
            @if(empty($activeAdminTemplate))
            @include('partials.error-fallback-ui')
            @endif
        </div>

        @if(!empty($activeAdminTemplate))
        @php
            $coreEnginePath = public_path('build/core/template-engine.min.js');
            $coreEngineVersion = is_file($coreEnginePath) && is_readable($coreEnginePath) ? filemtime($coreEnginePath) : null;
            $reverbPublicOptions = config('broadcasting.connections.reverb.public_options', []);
            $reverbPublicAppKey = $reverbPublicOptions['app_key'] ?? null;
            $reverbPublicHost = $reverbPublicOptions['host'] ?? null;
            $reverbPublicPort = $reverbPublicOptions['port'] ?? null;
            $reverbPublicScheme = $reverbPublicOptions['scheme'] ?? null;
            $websocketConfig = filled($reverbPublicAppKey) && filled($reverbPublicHost) && filled($reverbPublicPort) && filled($reverbPublicScheme)
                ? [
                    'appKey' => $reverbPublicAppKey,
                    'host' => $reverbPublicHost,
                    'port' => (int) $reverbPublicPort,
                    'scheme' => $reverbPublicScheme,
                ]
                : null;
        @endphp
        <!-- G7 설정 전역 변수 -->
        <script>
            window.G7Config = {
                settings: @json($frontendSettings ?? []),
                plugins: @json($pluginSettings ?? []),
                modules: @json($moduleSettings ?? []),
                moduleAssets: @json($moduleAssets ?? []),
                pluginAssets: @json($pluginAssets ?? []),
                appConfig: @json($appConfig ?? [])
            };
            @if(isset($errorCode) && isset($errorLayout))
            // 에러 상태 정보 (503 의존성 미충족 등)
            window.G7Error = {
                code: {{ $errorCode }},
                layout: '{{ $errorLayout }}',
                data: @json($unmetDependencies ?? [])
            };
            @endif
        </script>

        <!-- 코어 렌더링 엔진 -->
        <script src="{{ asset('build/core/template-engine.min.js') }}@if($coreEngineVersion !== null)?v={{ $coreEngineVersion }}@endif"></script>

        <!-- 템플릿 컴포넌트 번들 (IIFE) -->
        <script src="/api/templates/assets/{{ $activeAdminTemplate }}/js/components.iife.js?v={{ time() }}"></script>

        <!-- 템플릿 엔진 초기화 (TemplateApp 사용) -->
        <script>
            // TemplateApp을 통한 초기화 (DOMContentLoaded 이벤트에서 자동으로 초기화됨)
            if (window.G7Core && window.G7Core.initTemplateApp) {
                window.G7Core.initTemplateApp({
                    templateId: '{{ $activeAdminTemplate }}',
                    templateType: 'admin',
                    locale: '{{ app()->getLocale() }}',
                    debug: {{ config('app.debug') ? 'true' : 'false' }}@if($websocketConfig),
                    websocket: {
                        appKey: @json($websocketConfig['appKey']),
                        host: @json($websocketConfig['host']),
                        port: {{ $websocketConfig['port'] }},
                        scheme: @json($websocketConfig['scheme'])
                    }@endif
                });
            } else {
                console.error('[Admin] G7Core.initTemplateApp is not available');
            }
        </script>
        @endif
    </body>
</html>
