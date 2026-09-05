<?php

namespace App\Services;

use App\Http\Middleware\ResolveTenant;
use App\Models\Branch;
use App\Models\MembershipLevel;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * 商户开通：建租户 + 总店 + 默认等级 + 绑定/创建管理员。
 * Web 自助开店与 POST /api/v1/tenants/register 共用此实现，避免两处逻辑漂移。
 * 是否直接激活由 MEMBERSHIP_TENANT_AUTO_ACTIVATE 控制：生产建议关闭，
 * 新租户置为 pending，平台管理员在后台审核后再激活（店铺解析只认 active 租户）。
 */
class TenantProvisioning
{
    /**
     * @param  string  $tenantName  商户名称
     * @param  string  $slug        子域名标识（自动 slug 化）
     * @param  string|null $contactName  联系人
     * @param  User|null   $attachUser   已登录用户直接开店（绑定为其管理员）
     * @param  array|null  $newAdmin     新建管理员 ['name','email','password']
     * @return array{0: Tenant, 1: User} [租户, 管理员]
     */
    public function provision(string $tenantName, string $slug, ?string $contactName = null, ?User $attachUser = null, ?array $newAdmin = null): array
    {
        if (!$attachUser && empty($newAdmin['name']) && empty($newAdmin['email'])) {
            throw new \InvalidArgumentException('必须提供要绑定的用户或新管理员资料');
        }

        return DB::transaction(function () use ($tenantName, $slug, $contactName, $attachUser, $newAdmin) {
            $slug = Str::slug($slug);
            $tenant = Tenant::create([
                'name' => $tenantName,
                'slug' => $slug,
                'contact_name' => $contactName ?? $attachUser?->name ?? ($newAdmin['name'] ?? null),
                'status' => config('membership.tenant_auto_activate', true) ? 'active' : 'pending',
                'settings' => [
                    'domain' => $slug.'.'.ResolveTenant::baseDomain(),
                    'platform_fee_rate' => 0.05,
                ],
            ]);

            Branch::create(['tenant_id' => $tenant->id, 'name' => '总店', 'status' => 'active']);

            MembershipLevel::create([
                'tenant_id' => $tenant->id,
                'name' => '普通会员',
                'slug' => 'normal',
                'level' => 1,
                'min_points' => 0,
                'is_default' => true,
            ]);

            if ($attachUser) {
                $admin = $attachUser;
                $admin->update(['tenant_id' => $tenant->id]);
            } else {
                $admin = User::create([
                    'name' => $newAdmin['name'],
                    'email' => $newAdmin['email'],
                    'password' => $newAdmin['password'], // hashed cast 自动哈希
                    'tenant_id' => $tenant->id, // 后台数据隔离的关键归属
                ]);
            }
            try { $admin->assignRole('tenant_admin'); } catch (\Throwable) {}

            return [$tenant, $admin];
        });
    }
}
