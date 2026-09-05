<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Tenant;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;

class TenantAuthController extends Controller
{
    // 商户自助入驻 - shop1.xxx.com 模式（开通逻辑统一在 TenantProvisioning）
    // POST /api/v1/tenants/register
    public function register(Request $r)
    {
        $data = $r->validate([
            'tenant_name' => 'required|string|max:100',
            'slug' => 'required|string|max:50|regex:/^[a-z0-9-]+$/|unique:tenants,slug',
            'contact_name' => 'nullable|string|max:50',
            'contact_phone' => 'nullable|string|max:20',
            'admin_name' => 'required|string|max:50',
            'admin_email' => 'required|email|max:255|unique:users,email',
            'admin_password' => ['required','string', Password::min(8)->letters()->mixedCase()->numbers(), 'confirmed'],
            'admin_password_confirmation' => 'required',
            'plan_id' => 'nullable|integer|exists:membership_plans,id',
        ]);

        [$tenant, $admin] = app(\App\Services\TenantProvisioning::class)->provision(
            $data['tenant_name'],
            $data['slug'],
            ($data['contact_name'] ?? null) ?: $data['admin_name'],
            null,
            ['name'=>$data['admin_name'], 'email'=>$data['admin_email'], 'password'=>$data['admin_password']],
        );

        return response()->json([
            'tenant' => $tenant,
            'admin' => $admin,
            'token' => $admin->createToken('tenant-admin-token')->plainTextToken,
            'token_type' => 'Bearer',
            'domain' => $tenant->settings['domain'] ?? null,
            'message' => $tenant->status === 'active'
                ? '商户入驻成功，请用 ' . ($tenant->settings['domain'] ?? $tenant->slug.'.'. $this->baseDomain()) . ' 访问店铺'
                : '商户创建成功，等待平台审核通过后即可访问',
        ], 201);
    }

    public function checkSlug(Request $r)
    {
        $r->validate(['slug' => 'required|string|regex:/^[a-z0-9-]+$/']);
        $exists = Tenant::where('slug', Str::slug($r->slug))->exists();
        return response()->json(['available' => !$exists, 'slug' => Str::slug($r->slug)]);
    }

    private function baseDomain(): string
    {
        $url = config('app.url', 'http://localhost:8000');
        $host = parse_url($url, PHP_URL_HOST) ?: 'xxx.com';
        // 去掉端口和 www
        $host = preg_replace('/^www\./', '', $host);
        if ($host === 'localhost' || str_contains($host, '127.0.0.1')) return 'xxx.com';
        return $host;
    }
}
