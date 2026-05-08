<?php
declare (strict_types = 1);

namespace app\admin\middleware;

/**
 * 安全响应头中间件
 */
class SecurityHeaders
{
    public function handle($request, \Closure $next)
    {
        $response = $next($request);
        
        $headers = [
            'X-Content-Type-Options' => 'nosniff',
            'X-Frame-Options' => 'SAMEORIGIN',
            'X-XSS-Protection' => '1; mode=block',
            'Referrer-Policy' => 'strict-origin-when-cross-origin',
            'Content-Security-Policy' => "default-src 'self'; script-src 'self' 'unsafe-inline' 'unsafe-eval'; style-src 'self' 'unsafe-inline'; img-src 'self' data: blob:; font-src 'self' data:; connect-src 'self' http://localhost:*",
            'Strict-Transport-Security' => 'max-age=31536000; includeSubDomains',
        ];
        
        foreach ($headers as $key => $value) {
            $response->header($key, $value);
        }
        
        return $response;
    }
}