<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ForceApiJsonResponse
{
    /**
     * API 요청이 HTML 예외 페이지로 빠지지 않도록 JSON 응답을 강제합니다.
     */
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->is('api/*') && ! $request->expectsJson()) {
            $request->headers->set('Accept', 'application/json');
        }

        return $next($request);
    }
}
