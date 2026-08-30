<?php

namespace App\Observers;

use Illuminate\Database\Eloquent\Model;

/**
 * 租户写入归属守护：
 * - creating：商户管理员建数据时强制 tenant_id = 本人租户（即使表单/请求传了别人的）
 * - updating：禁止把记录改挂到别的租户下（改了也弹回）
 * 仅对已登录且带租户归属的用户生效，平台管理员/游客不受影响。
 */
class TenantDataObserver
{
    public function creating(Model $model): void
    {
        $user = auth()->user();
        if (!$user || !$user->tenant_id) return;
        if (!in_array('tenant_id', $model->getFillable())) return;
        $model->tenant_id = $user->tenant_id;
    }

    public function updating(Model $model): void
    {
        $user = auth()->user();
        if (!$user || !$user->tenant_id) return;
        if ($model->isDirty('tenant_id')) {
            $model->tenant_id = $model->getOriginal('tenant_id');
        }
    }
}
