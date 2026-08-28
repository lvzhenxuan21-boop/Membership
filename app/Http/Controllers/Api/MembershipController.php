<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\MembershipLevel;
use App\Models\MembershipPlan;
use App\Models\MemberProfile;
use App\Services\MembershipService;
use Illuminate\Http\Request;

class MembershipController extends Controller
{
    public function __construct(private MembershipService $svc) {}

    public function plans(Request $r) {
        $tenantId = (int)($r->input('tenant_id', 1));
        return MembershipPlan::with('features')->where('tenant_id',$tenantId)->where('is_active',true)->orderBy('price')->get();
    }

    public function levels(Request $r) {
        $tenantId = (int)($r->input('tenant_id', 1));
        return MembershipLevel::where('tenant_id',$tenantId)->orderBy('level')->get();
    }

    public function profile(int $userId, Request $r) {
        $tenantId = (int)($r->input('tenant_id', 1));
        $profile = MemberProfile::with(['level','user','branch'])->where('tenant_id',$tenantId)->where('user_id',$userId)->firstOrFail();
        $subs = \App\Models\Subscription::with('plan')->where('tenant_id',$tenantId)->where('user_id',$userId)->latest()->limit(5)->get();
        $wallet = \App\Models\Wallet::where('tenant_id',$tenantId)->where('user_id',$userId)->first();
        return response()->json(compact('profile','subs','wallet'));
    }

    public function subscribe(Request $r) {
        $data = $r->validate(['tenant_id'=>'required|integer','user_id'=>'required|integer','plan_id'=>'required|integer','payment_method'=>'nullable|string']);
        $sub = $this->svc->subscribe($data['tenant_id'],$data['user_id'],$data['plan_id'], $data);
        return response()->json($sub->load('plan','featureUsages.feature'), 201);
    }

    public function addPoints(Request $r) {
        $data = $r->validate(['tenant_id'=>'required|integer','user_id'=>'required|integer','points'=>'required|integer','type'=>'required|in:earn,spend,adjust','description'=>'nullable|string']);
        $ledger = $this->svc->addPoints($data['tenant_id'],$data['user_id'],$data['points'],$data['type'],$data['description']??'admin');
        return response()->json($ledger, 201);
    }

    public function walletRecharge(Request $r) {
        $data = $r->validate(['tenant_id'=>'required|integer','user_id'=>'required|integer','amount'=>'required|numeric','type'=>'nullable|in:recharge,consume','description'=>'nullable|string']);
        $tx = $this->svc->walletChange($data['tenant_id'],$data['user_id'], (float)$data['amount'], $data['type']??'recharge', $data['description']??'recharge');
        return response()->json($tx, 201);
    }

    public function consumeFeature(Request $r) {
        $data = $r->validate(['subscription_id'=>'required|integer','feature_code'=>'required|string','amount'=>'nullable|integer|min:1']);
        $this->svc->consumeFeature($data['subscription_id'],$data['feature_code'],$data['amount']??1);
        return response()->json(['ok'=>true]);
    }
}
