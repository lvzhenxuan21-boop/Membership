<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\MembershipLevel;
use App\Models\MembershipPlan;
use App\Models\MemberProfile;
use App\Models\Subscription;
use App\Services\MembershipService;
use Illuminate\Http\Request;

class MembershipController extends Controller
{
    public function __construct(private MembershipService $svc) {}

    /**
     * 管理员操作的目标租户：平台管理员可指定任意租户；
     * 租户职员强制本租户（不信任请求传入的 tenant_id，防跨租户越权）。
     */
    private function targetTenantId(Request $r): int
    {
        $user = $r->user();
        if ($user->hasAnyRole(['super_admin','admin'])) {
            $tid = (int) $r->input('tenant_id', 0);
            if ($tid <= 0) throw new \InvalidArgumentException('平台管理员需指定 tenant_id');
            return $tid;
        }
        if (!empty($user->tenant_id)) return (int) $user->tenant_id;
        throw new \InvalidArgumentException('账号未绑定租户，无法执行该操作');
    }

    // 目标用户必须在该商户下有会员档案（防止把积分/钱包/订阅挂到跨租户用户身上）
    private function requireProfileInTenant(int $tenantId, int $userId): void
    {
        if (!MemberProfile::where('tenant_id',$tenantId)->where('user_id',$userId)->exists()) {
            throw new \InvalidArgumentException('目标用户在该商户下没有会员档案');
        }
    }

    // 套餐/等级公开（公开定价页）
    public function plans(Request $r) {
        $tenantId = (int)($r->input('tenant_id', 1));
        return MembershipPlan::with('features')->where('tenant_id',$tenantId)->where('is_active',true)->orderBy('price')->get();
    }

    public function levels(Request $r) {
        $tenantId = (int)($r->input('tenant_id', 1));
        return MembershipLevel::where('tenant_id',$tenantId)->orderBy('level')->get();
    }

    // 会员档案：仅本人或管理员可查
    public function profile(int $userId, Request $r) {
        $user = $r->user();
        if ($userId !== $user->id && !$user->hasAnyRole(['super_admin','admin','tenant_admin'])) {
            return response()->json(['message'=>'无权查看该会员档案'], 403);
        }
        $tenantId = (int)($r->input('tenant_id', 1));
        $profile = MemberProfile::with(['level','user','branch'])->where('tenant_id',$tenantId)->where('user_id',$userId)->firstOrFail();
        $subs = Subscription::with('plan')->where('tenant_id',$tenantId)->where('user_id',$userId)->latest()->limit(5)->get();
        $wallet = \App\Models\Wallet::where('tenant_id',$tenantId)->where('user_id',$userId)->first();
        return response()->json(compact('profile','subs','wallet'));
    }

    // 订阅：user_id 以登录态为准，仅管理员可代开
    public function subscribe(Request $r) {
        $data = $r->validate(['tenant_id'=>'nullable|integer','user_id'=>'nullable|integer','plan_id'=>'required|integer','payment_method'=>'nullable|string']);
        $user = $r->user();
        try {
            $tenantId = $this->targetTenantId($r);
        } catch (\InvalidArgumentException $e) {
            return response()->json(['message'=>$e->getMessage()], 422);
        }
        $userId = $user->id;
        if (isset($data['user_id']) && (int)$data['user_id'] !== $userId) {
            if (!$user->hasAnyRole(['super_admin','admin','tenant_admin'])) {
                return response()->json(['message'=>'无权为其他用户订阅'], 403);
            }
            $userId = (int)$data['user_id'];
        }
        try {
            $this->requireProfileInTenant($tenantId, $userId);
            $sub = $this->svc->subscribe($tenantId,$userId,$data['plan_id'], $data);
        } catch (\InvalidArgumentException $e) {
            return response()->json(['message'=>$e->getMessage()], 422);
        }
        return response()->json($sub->load('plan','featureUsages.feature'), 201);
    }

    // 积分调整：仅管理员（路由已限角色）；租户职员强制本租户
    public function addPoints(Request $r) {
        $data = $r->validate(['user_id'=>'required|integer','points'=>'required|integer','type'=>'required|in:earn,spend,adjust','description'=>'nullable|string']);
        try {
            $tenantId = $this->targetTenantId($r);
            $this->requireProfileInTenant($tenantId, (int)$data['user_id']);
        } catch (\InvalidArgumentException $e) {
            return response()->json(['message'=>$e->getMessage()], 422);
        }
        $ledger = $this->svc->addPoints($tenantId,$data['user_id'],$data['points'],$data['type'],$data['description']??'admin');
        return response()->json($ledger, 201);
    }

    // 钱包变动：仅管理员（路由已限角色）；租户职员强制本租户
    public function walletRecharge(Request $r) {
        $data = $r->validate(['user_id'=>'required|integer','amount'=>'required|numeric','type'=>'nullable|in:recharge,consume','description'=>'nullable|string']);
        try {
            $tenantId = $this->targetTenantId($r);
            $this->requireProfileInTenant($tenantId, (int)$data['user_id']);
        } catch (\InvalidArgumentException $e) {
            return response()->json(['message'=>$e->getMessage()], 422);
        }
        $tx = $this->svc->walletChange($tenantId,$data['user_id'], (float)$data['amount'], $data['type']??'recharge', $data['description']??'recharge');
        return response()->json($tx, 201);
    }

    // 配额消耗：仅订阅本人或管理员
    public function consumeFeature(Request $r) {
        $data = $r->validate(['subscription_id'=>'required|integer','feature_code'=>'required|string','amount'=>'nullable|integer|min:1']);
        $sub = Subscription::findOrFail($data['subscription_id']);
        $user = $r->user();
        if ($sub->user_id !== $user->id && !$user->hasAnyRole(['super_admin','admin','tenant_admin'])) {
            return response()->json(['message'=>'无权操作该订阅'], 403);
        }
        $this->svc->consumeFeature($data['subscription_id'],$data['feature_code'],$data['amount']??1);
        return response()->json(['ok'=>true]);
    }
}
