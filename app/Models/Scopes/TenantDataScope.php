<?php

namespace App\Models\Scopes;

use App\Models\Tenant;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

/**
 * 租户数据隔离：商户管理员(tenant_admin/staff)只能读写自己租户的数据，
 * 平台管理员(super_admin/admin，users.tenant_id 为 null)不受限。
 * 经 addGlobalScope 注册到所有带 tenant_id 的业务模型（见 AppServiceProvider）。
 */
class TenantDataScope implements Scope
{
    public function apply(Builder $builder, Model $model): void
    {
        $user = auth()->user();
        if (!$user || !$user->tenant_id) return; // 未登录（webhook/命令行/游客）或平台管理员 → 不限
        if (method_exists($user, 'hasAnyRole') && $user->hasAnyRole(['super_admin', 'admin'])) return;

        if ($model instanceof Tenant) {
            $builder->whereKey($user->tenant_id); // 租户模型：只能看到自己这家
            return;
        }
        $builder->where($model->getTable() . '.tenant_id', $user->tenant_id);
    }
}
