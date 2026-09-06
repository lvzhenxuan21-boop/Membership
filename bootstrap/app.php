<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'tenant' => \App\Http\Middleware\ResolveTenant::class,
            'role' => \Spatie\Permission\Middleware\RoleMiddleware::class,
            'verify-email' => \App\Http\Middleware\EnsureEmailVerifiedIfRequired::class,
        ]);
        // 信任反向代理（nginx/CDN），否则 request()->ip() 恒为代理 IP：
        // 登录/下单限流、签到 IP 风控会把所有用户算成同一个 IP。
        // TRUSTED_PROXIES: * 或逗号分隔的 IP/CIDR（如 127.0.0.1,10.0.0.0/8）；留空=不信任（直连部署）
        $proxies = env('TRUSTED_PROXIES');
        if (!empty($proxies)) {
            $middleware->trustProxies(at: trim($proxies) === '*' ? '*' : array_map('trim', explode(',', $proxies)));
        }
        // 允许同域 web session（前端 fetch 携带 cookie）通过 api 认证，配合 auth:sanctum
        $middleware->statefulApi();
        // api + web 自动解析子域名租户，控制器可通过 $request->attributes->get('tenant')
        $middleware->appendToGroup('api', \App\Http\Middleware\ResolveTenant::class);
        $middleware->appendToGroup('web', \App\Http\Middleware\ResolveTenant::class);
        // 基础安全响应头（防点击劫持/内容嗅探/引用泄漏）
        $middleware->append(\App\Http\Middleware\SecurityHeaders::class);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
        // Web 端 403 统一渲染 Gallery 友好页（替代裸 403），API 仍走 JSON
        $exceptions->render(function (\Illuminate\Auth\Access\AuthorizationException $e, Request $request) {
            if ($request->is('api/*') || $request->expectsJson()) return null;
            return response()->view('errors.403', ['exception' => $e], 403);
        });
        $exceptions->render(function (\Symfony\Component\HttpKernel\Exception\HttpException $e, Request $request) {
            if ($e->getStatusCode() !== 403) return null;
            if ($request->is('api/*') || $request->expectsJson()) return null;
            return response()->view('errors.403', ['exception' => $e], 403);
        });
    })->create();
