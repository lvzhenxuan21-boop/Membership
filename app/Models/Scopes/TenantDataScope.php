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
        if (!$user) return; // 未登录（webhook/命令行/游客）→ 不限，隔离由各控制器按 user_id 收敛
        if (method_exists($user, 'hasAnyRole') && $user->hasAnyRole(['super_admin', 'admin'])) return; // 平台管理员全量可见

        // 仅租户职员（tenant_admin/staff）受全局作用域约束。
        // 会员可同时属于多个租户（users.tenant_id 为空），不能套作用域——否则跨租户购物/查询会被错误过滤，
        // 且观察者会把别的租户的数据强行改挂到会员的租户上。
        if (!method_exists($user, 'hasAnyRole') || !$user->hasAnyRole(['tenant_admin', 'staff'])) return;

        if (!$user->tenant_id) {
            // 归属异常的租户职员：宁可不可见，也不放开全量
            $builder->whereRaw('1 = 0');
            return;
        }

        if ($model instanceof Tenant) {
            $builder->whereKey($user->tenant_id); // 租户模型：只能看到自己这家
            return;
        }
        $builder->where($model->getTable() . '.tenant_id', $user->tenant_id);
    }
}
