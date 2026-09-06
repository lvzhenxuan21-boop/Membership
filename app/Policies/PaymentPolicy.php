<?php

namespace App\Policies;

use App\Models\Payment;
use App\Models\User;

/**
 * 支付单后台授权：
 * - 查看：所有面板用户可见（数据范围由租户全局作用域收敛）
 * - 创建/更新：一律禁止——支付单只能由业务流程（下单/订阅/充值）创建，
 *   状态只能经 PaymentService 核销/退款（走业务钩子与事务），手改行会绕过账务
 * - 删除：仅平台管理员（清理脏数据用）
 */
class PaymentPolicy
{
    public function viewAny(User $user): bool { return true; }

    public function view(User $user, Payment $payment): bool { return true; }

    public function create(User $user): bool { return false; }

    public function update(User $user, Payment $payment): bool { return false; }

    public function delete(User $user, Payment $payment): bool
    {
        return $user->hasAnyRole(['super_admin', 'admin']);
    }

    public function deleteAny(User $user): bool
    {
        return $user->hasAnyRole(['super_admin', 'admin']);
    }
}
