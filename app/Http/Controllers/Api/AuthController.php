<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\MemberProfile;
use App\Models\MembershipLevel;
use App\Models\Branch;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Wallet;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;

class AuthController extends Controller
{
    // 注册 - 多租户感知，创建 User + MemberProfile + Wallet + 角色
    public function register(Request $r)
    {
        // 兼容 shop1.xxx.com 子域名自动解析 + 显式 tenant_id
        $resolved = $r->attributes->get('tenant');
        $data = $r->validate([
            'tenant_id' => 'nullable|integer|exists:tenants,id',
            'tenant_slug' => 'nullable|string|exists:tenants,slug',
            'name' => 'required|string|max:50',
            'email' => 'required|email|max:255|unique:users,email',
            'password' => ['required','string', Password::min(8)->letters()->mixedCase()->numbers(), 'confirmed'],
            'password_confirmation' => 'required',
            'phone' => 'nullable|string|max:20',
            'real_name' => 'nullable|string|max:50',
            'branch_id' => 'nullable|integer|exists:branches,id',
            'gender' => 'nullable|in:unknown,male,female',
            'birthday' => 'nullable|date',
            'avatar' => 'nullable|string|max:255',
        ]);

        // 解析租户优先级: tenant_id > tenant_slug > 子域名中间件 > X-Tenant-Slug
        $tenant = null;
        if (!empty($data['tenant_id'])) $tenant = Tenant::find($data['tenant_id']);
        elseif (!empty($data['tenant_slug'])) $tenant = Tenant::where('slug', $data['tenant_slug'])->first();
        elseif ($resolved) $tenant = $resolved;
        else $tenant = \App\Http\Middleware\ResolveTenant::resolve($r);

        if (!$tenant) return response()->json(['message'=>'无法识别商户，请通过 shop1.xxx.com 域名或传递 tenant_id/tenant_slug'], 422);
        $data['tenant_id'] = $tenant->id;

        return DB::transaction(function () use ($data, $r) {
            $tenant = Tenant::findOrFail($data['tenant_id']);
            if ($tenant->status !== 'active') {
                return response()->json(['message'=>'商户未启用'], 422);
            }

            // 查重 phone 在同一租户下已存在 profile
            if (!empty($data['phone'])) {
                $exists = MemberProfile::where('tenant_id',$data['tenant_id'])->where('phone',$data['phone'])->exists();
                if ($exists) return response()->json(['message'=>'该手机号已在该商户注册'], 422);
            }

            // 创建用户 - 依赖 User casts password=hashed 自动哈希
            $user = User::create([
                'name' => $data['name'],
                'email' => $data['email'],
                'password' => $data['password'],
            ]);
            $user->assignRole('member');

            // 默认等级与门店
            $level = MembershipLevel::where('tenant_id',$data['tenant_id'])->where('is_default',true)->first()
                ?? MembershipLevel::where('tenant_id',$data['tenant_id'])->orderBy('level')->first();
            $branchId = $data['branch_id'] ?? Branch::where('tenant_id',$data['tenant_id'])->value('id');

            $profile = MemberProfile::create([
                'user_id' => $user->id,
                'tenant_id' => $data['tenant_id'],
                'branch_id' => $branchId,
                'membership_level_id' => $level?->id,
                'phone' => $data['phone'] ?? null,
                'real_name' => $data['real_name'] ?? null,
                'gender' => $data['gender'] ?? 'unknown',
                'birthday' => $data['birthday'] ?? null,
                'avatar' => $data['avatar'] ?? null,
            ]);

            Wallet::firstOrCreate(['tenant_id'=>$data['tenant_id'],'user_id'=>$user->id], ['balance'=>0]);

            $token = $user->createToken('auth-token')->plainTextToken;

            return response()->json([
                'user' => $user,
                'profile' => $profile->load(['level','branch','tenant']),
                'token' => $token,
                'token_type' => 'Bearer',
            ], 201);
        });
    }

    public function login(Request $r)
    {
        $data = $r->validate([
            'email' => 'required|email',
            'password' => 'required|string',
            'tenant_id' => 'nullable|integer|exists:tenants,id',
            'tenant_slug' => 'nullable|string|exists:tenants,slug',
        ]);
        // 子域名自动补 tenant_id 用于封禁检查
        if (empty($data['tenant_id']) && !empty($data['tenant_slug'])) {
            $data['tenant_id'] = Tenant::where('slug', $data['tenant_slug'])->value('id');
        }
        if (empty($data['tenant_id']) && ($t = $r->attributes->get('tenant') ?? \App\Http\Middleware\ResolveTenant::resolve($r))) {
            $data['tenant_id'] = $t->id;
        }

        $user = User::where('email',$data['email'])->first();
        if (!$user || !Hash::check($data['password'], $user->password)) {
            return response()->json(['message'=>'邮箱或密码错误'], 422);
        }

        // 封禁检查
        if (!empty($data['tenant_id'])) {
            $profile = MemberProfile::where('tenant_id',$data['tenant_id'])->where('user_id',$user->id)->first();
            if ($profile && $profile->status==='frozen') return response()->json(['message'=>'账号已冻结，请联系商户'], 403);
            if ($profile && $profile->status==='cancelled') return response()->json(['message'=>'账号已注销'], 403);
        }

        // 允许多端登录，不强制单点；如需单点可取消注释下一行
        // $user->tokens()->delete();
        $token = $user->createToken('auth-token')->plainTextToken;

        return response()->json([
            'user' => $user->load(['memberProfiles','wallets']),
            'token' => $token,
            'token_type' => 'Bearer',
        ]);
    }

    public function logout(Request $r)
    {
        $r->user()->currentAccessToken()->delete();
        return response()->json(['ok'=>true]);
    }

    public function me(Request $r)
    {
        $user = $r->user()->load(['memberProfiles.tenant','memberProfiles.level','memberProfiles.branch','wallets','subscriptions.plan']);
        return response()->json($user);
    }

    public function updateProfile(Request $r)
    {
        $data = $r->validate([
            'name' => 'nullable|string|max:50',
            'real_name' => 'nullable|string|max:50',
            'phone' => 'nullable|string|max:20',
            'gender' => 'nullable|in:unknown,male,female',
            'birthday' => 'nullable|date',
            'avatar' => 'nullable|string|max:255',
            'tenant_id' => 'nullable|integer|exists:tenants,id',
            'tenant_slug' => 'nullable|string|exists:tenants,slug',
        ]);
        if (empty($data['tenant_id'])) {
            if (!empty($data['tenant_slug'])) $data['tenant_id'] = Tenant::where('slug', $data['tenant_slug'])->value('id');
            elseif ($t = $r->attributes->get('tenant') ?? \App\Http\Middleware\ResolveTenant::resolve($r)) $data['tenant_id'] = $t->id;
        }
        if (empty($data['tenant_id'])) return response()->json(['message'=>'无法识别商户'], 422);

        $user = $r->user();
        if (!empty($data['name'])) $user->update(['name'=>$data['name']]);

        $profile = MemberProfile::where('tenant_id',$data['tenant_id'])->where('user_id',$user->id)->firstOrFail();
        $up = collect($data)->only(['real_name','phone','gender','birthday','avatar'])->filter(fn($v)=> $v!==null)->toArray();
        if (!empty($up)) $profile->update($up);

        return response()->json($profile->fresh()->load(['level','branch']));
    }
}
