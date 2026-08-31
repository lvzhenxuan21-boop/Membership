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
        $data = $r->validate(['tenant_id'=>'required|integer','user_id'=>'nullable|integer','plan_id'=>'required|integer','payment_method'=>'nullable|string']);
        $user = $r->user();
        $userId = $user->id;
        if (isset($data['user_id']) && (int)$data['user_id'] !== $userId) {
            if (!$user->hasAnyRole(['super_admin','admin','tenant_admin'])) {
                return response()->json(['message'=>'无权为其他用户订阅'], 403);
            }
            $userId = (int)$data['user_id'];
        }
        $sub = $this->svc->subscribe($data['tenant_id'],$userId,$data['plan_id'], $data);
        return response()->json($sub->load('plan','featureUsages.feature'), 201);
    }

    // 积分调整：仅管理员（路由已限角色）
    public function addPoints(Request $r) {
        $data = $r->validate(['tenant_id'=>'required|integer','user_id'=>'required|integer','points'=>'required|integer','type'=>'required|in:earn,spend,adjust','description'=>'nullable|string']);
        $ledger = $this->svc->addPoints($data['tenant_id'],$data['user_id'],$data['points'],$data['type'],$data['description']??'admin');
        return response()->json($ledger, 201);
    }

    // 钱包变动：仅管理员（路由已限角色）
    public function walletRecharge(Request $r) {
        $data = $r->validate(['tenant_id'=>'required|integer','user_id'=>'required|integer','amount'=>'required|numeric','type'=>'nullable|in:recharge,consume','description'=>'nullable|string']);
        $tx = $this->svc->walletChange($data['tenant_id'],$data['user_id'], (float)$data['amount'], $data['type']??'recharge', $data['description']??'recharge');
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
