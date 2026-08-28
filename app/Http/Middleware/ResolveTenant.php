<?php

namespace App\Http\Middleware;

use App\Models\Tenant;
use Closure;
use Illuminate\Http\Request;

class ResolveTenant
{
    public function handle(Request $request, Closure $next)
    {
        $tenant = $this->resolve($request);
        if ($tenant) {
            // 注入到请求与容器
            $request->attributes->set('tenant', $tenant);
            $request->attributes->set('tenant_id', $tenant->id);
            app()->instance('currentTenant', $tenant);
        }
        return $next($request);
    }

    public static function resolve(Request $request): ?Tenant
    {
        // 1. 显式 header 优先 X-Tenant-Slug
        if ($slug = $request->header('X-Tenant-Slug')) {
            if ($t = Tenant::where('slug', $slug)->first()) return $t;
        }
        // 2. query / body tenant_id / slug 兼容
        if ($request->filled('tenant_slug')) {
            if ($t = Tenant::where('slug', $request->input('tenant_slug'))->first()) return $t;
        }
        if ($request->filled('tenant_id') && is_numeric($request->input('tenant_id'))) {
            if ($t = Tenant::find($request->input('tenant_id'))) return $t;
        }
        // 3. 子域名解析 shop1.xxx.com -> slug=shop1
        $host = $request->getHost(); // shop1.xxx.com:8000 -> shop1.xxx.com
        $host = preg_replace('/:\d+$/', '', $host);
        $base = self::baseDomain();
        // localhost / 127.0.0.1 / xxx.com 本身不解析
        if (in_array($host, ['localhost','127.0.0.1']) || $host === $base) return null;

        // 子域名提取
        if (str_ends_with($host, '.' . $base)) {
            $sub = substr($host, 0, -strlen('.' . $base));
            // 支持 shop1  或 shop1.xxx 里的第一段
            $slug = explode('.', $sub)[0];
            if ($slug && $slug !== 'www' && $slug !== 'api' && $slug !== 'admin') {
                if ($t = Tenant::where('slug', $slug)->first()) return $t;
            }
        }
        // 4. 裸域 + path 模式 /shop/{slug} 兼容
        if ($request->is('shop/*')) {
            $slug = explode('/', trim($request->path(), '/'))[1] ?? null;
            if ($slug && $t = Tenant::where('slug', $slug)->first()) return $t;
        }
        return null;
    }

    public static function baseDomain(): string
    {
        $url = config('app.url', 'http://xxx.com');
        $host = parse_url($url, PHP_URL_HOST) ?: 'xxx.com';
        $host = preg_replace('/^www\./', '', $host);
        if ($host === 'localhost' || str_contains($host, '127.0.0.1')) return 'xxx.com';
        return $host;
    }
}
