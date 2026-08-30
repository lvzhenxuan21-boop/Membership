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
        ]);
        // api + web 自动解析子域名租户，控制器可通过 $request->attributes->get('tenant')
        $middleware->appendToGroup('api', \App\Http\Middleware\ResolveTenant::class);
        $middleware->appendToGroup('web', \App\Http\Middleware\ResolveTenant::class);
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
