<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\MembershipLevel;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;

class TenantAuthController extends Controller
{
    // 商户自助入驻 - shop1.xxx.com 模式
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

        return DB::transaction(function () use ($data) {
            $tenant = Tenant::create([
                'name' => $data['tenant_name'],
                'slug' => Str::slug($data['slug']),
                'contact_name' => $data['contact_name'] ?? $data['admin_name'],
                'contact_phone' => $data['contact_phone'] ?? null,
                'status' => 'active',
                'settings' => [
                    'domain' => $data['slug'] . '.' . $this->baseDomain(),
                    'platform_fee_rate' => 0.05,
                ],
            ]);

            $branch = Branch::create([
                'tenant_id' => $tenant->id,
                'name' => '总店',
                'status' => 'active',
            ]);

            // 默认等级
            MembershipLevel::create([
                'tenant_id' => $tenant->id,
                'name' => '普通会员',
                'slug' => 'normal',
                'level' => 1,
                'min_points' => 0,
                'is_default' => true,
            ]);

            $user = User::create([
                'name' => $data['admin_name'],
                'email' => $data['admin_email'],
                'password' => $data['admin_password'],
            ]);
            // 平台租户管理员角色，需在 seeder 中预置 tenant_admin
            if (method_exists($user, 'assignRole')) {
                try { $user->assignRole('tenant_admin'); } catch (\Throwable $e) {}
            }

            $token = $user->createToken('tenant-admin-token')->plainTextToken;

            // 自动订阅试用套餐（可选）
            // if (!empty($data['plan_id'])) { app(\App\Services\MembershipService::class)->subscribe($tenant->id, $user->id, $data['plan_id']); }

            return response()->json([
                'tenant' => $tenant,
                'branch' => $branch,
                'admin' => $user,
                'token' => $token,
                'token_type' => 'Bearer',
                'domain' => $tenant->settings['domain'] ?? null,
                'message' => '商户入驻成功，请用 ' . ($tenant->settings['domain'] ?? $tenant->slug.'.'. $this->baseDomain()) . ' 访问店铺',
            ], 201);
        });
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
