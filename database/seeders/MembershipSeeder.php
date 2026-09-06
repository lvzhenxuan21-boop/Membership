<?php

namespace Database\Seeders;

use App\Models\Branch;
use App\Models\Coupon;
use App\Models\Feature;
use App\Models\MembershipLevel;
use App\Models\MembershipPlan;
use App\Models\Tenant;
use App\Models\User;
use App\Models\MemberProfile;
use App\Models\Wallet;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;

class MembershipSeeder extends Seeder
{
    public function run(): void
    {
        // 租户
        $tenant = Tenant::firstOrCreate(['slug'=>'demo'], ['name'=>'演示商户','status'=>'active']);

        // 分支
        Branch::firstOrCreate(['tenant_id'=>$tenant->id,'name'=>'总店'], ['address'=>'北京市朝阳区','status'=>'active']);
        Branch::firstOrCreate(['tenant_id'=>$tenant->id,'name'=>'分店A'], ['address'=>'上海市浦东新区','status'=>'active']);

        // 等级 - 传统积分等级体系
        $levels = [
            ['name'=>'普通会员','slug'=>'normal','level'=>1,'min_growth'=>0,'discount_rate'=>1.00,'points_multiplier'=>1,'badge_color'=>'#999999','is_default'=>true],
            ['name'=>'银卡','slug'=>'silver','level'=>2,'min_growth'=>1000,'discount_rate'=>0.95,'points_multiplier'=>1,'badge_color'=>'#C0C0C0'],
            ['name'=>'金卡','slug'=>'gold','level'=>3,'min_growth'=>5000,'discount_rate'=>0.88,'points_multiplier'=>2,'badge_color'=>'#FFD700'],
            ['name'=>'钻石','slug'=>'diamond','level'=>4,'min_growth'=>20000,'discount_rate'=>0.80,'points_multiplier'=>3,'badge_color'=>'#00D1FF'],
        ];
        foreach ($levels as $l) MembershipLevel::firstOrCreate(['tenant_id'=>$tenant->id,'slug'=>$l['slug']], $l+['tenant_id'=>$tenant->id]);

        // 权益 - Soulbscription Feature 模式
        $features = [
            ['code'=>'FREE_SHIPPING','name'=>'免费配送','slug'=>'free-shipping','type'=>'quota','unit'=>'次'],
            ['code'=>'COUPON_PACK','name'=>'每月券包','slug'=>'coupon-pack','type'=>'quota','unit'=>'张'],
            ['code'=>'PRIORITY_CS','name'=>'优先客服','slug'=>'priority-cs','type'=>'boolean'],
            ['code'=>'DISCOUNT','name'=>'会员折扣','slug'=>'discount','type'=>'discount'],
        ];
        foreach ($features as $f) Feature::firstOrCreate(['code'=>$f['code']], $f+['tenant_id'=>$tenant->id,'description'=>$f['name']]);

        // 套餐 - 付费会员 (88VIP模式)
        $plans = [
            ['name'=>'月卡VIP','slug'=>'monthly','price'=>19.9,'original_price'=>29.9,'billing_cycle'=>'monthly','trial_days'=>3,'is_recommended'=>false,'benefits'=>['适合尝鲜']],
            ['name'=>'年卡VIP','slug'=>'yearly','price'=>199,'original_price'=>299,'billing_cycle'=>'yearly','trial_days'=>7,'is_recommended'=>true,'benefits'=>['最划算','送12张券']],
            ['name'=>'终身VIP','slug'=>'lifetime','price'=>599,'billing_cycle'=>'lifetime','is_recommended'=>false,'benefits'=>['一次付费终身享受']],
        ];
        foreach ($plans as $p) {
            $plan = MembershipPlan::firstOrCreate(['tenant_id'=>$tenant->id,'slug'=>$p['slug']], $p+['tenant_id'=>$tenant->id,'currency'=>'CNY','is_active'=>true,'sort_order'=>0]);
            // 绑定权益
            $freeShip = Feature::where('code','FREE_SHIPPING')->first();
            $coupon = Feature::where('code','COUPON_PACK')->first();
            $discount = Feature::where('code','DISCOUNT')->first();
            $quota = $p['slug']==='monthly'?5:($p['slug']==='yearly'?12:999);
            $plan->features()->syncWithoutDetaching([
                $freeShip->id=>['quota'=>$quota,'value'=> (string)$quota],
                $coupon->id=>['quota'=>$quota,'value'=> (string)$quota],
                $discount->id=>['quota'=>null,'value'=> $p['slug']==='monthly'?'0.95':($p['slug']==='yearly'?'0.88':'0.80')],
            ]);
        }

        // 优惠券
        Coupon::firstOrCreate(['code'=>'WELCOME10'], ['tenant_id'=>$tenant->id,'name'=>'新人10元券','type'=>'cash','value'=>10,'min_amount'=>59,'total_quota'=>1000,'per_user_limit'=>1,'is_active'=>true,'starts_at'=>now(),'ends_at'=>now()->addMonth()]);

        // 权限 - Spatie (平台管理员/商户管理员/店员)
        $perms = ['member.view','member.edit','member.recharge','plan.manage','coupon.manage','branch.manage','checkin.manage'];
        foreach ($perms as $pn) Permission::firstOrCreate(['name'=>$pn,'guard_name'=>'web']);
        $superRole = Role::firstOrCreate(['name'=>'super_admin','guard_name'=>'web']);
        $superRole->syncPermissions(Permission::all());
        $adminRole = Role::firstOrCreate(['name'=>'admin','guard_name'=>'web']);
        $adminRole->syncPermissions($perms);
        $adminRole = Role::firstOrCreate(['name'=>'tenant_admin','guard_name'=>'web']);
        $adminRole->syncPermissions($perms);
        $staffRole = Role::firstOrCreate(['name'=>'staff','guard_name'=>'web']);
        $staffRole->syncPermissions(['member.view','checkin.manage']);
        $memberRole = Role::firstOrCreate(['name'=>'member','guard_name'=>'web']);

        // 演示会员（纯会员，无后台权限）
        $user = User::firstOrCreate(['email'=>'demo@member.com'], ['name'=>'演示会员','password'=>Hash::make('12345678')]);
        $user->assignRole('member');
        $levelNormal = MembershipLevel::where('tenant_id',$tenant->id)->where('slug','normal')->first();
        $branch = Branch::where('tenant_id',$tenant->id)->first();
        MemberProfile::firstOrCreate(['tenant_id'=>$tenant->id,'user_id'=>$user->id], [
            'member_no'=>'M'.date('Ymd').'000001','branch_id'=>$branch->id,'membership_level_id'=>$levelNormal->id,'phone'=>'13800138000','points'=>100,'growth'=>100,'balance'=>0,'status'=>'active'
        ]);
        Wallet::firstOrCreate(['tenant_id'=>$tenant->id,'user_id'=>$user->id], ['balance'=>0]);

        // 商户管理员（可登录后台，用于测试后台管理功能）
        $tenantAdmin = User::firstOrCreate(['email'=>'tenant@demo.com'], ['name'=>'商户管理员','password'=>Hash::make('12345678'),'tenant_id'=>$tenant->id]);
        $tenantAdmin->assignRole('tenant_admin');

        // 上线前必改：弱密码演示账号直接上生产等于裸奔
        $this->command?->warn('');
        $this->command?->warn('════════════════════════════════════════════════════════');
        $this->command?->warn('  ⚠️  演示账号已创建，生产环境务必先改密码再对外开放：');
        $this->command?->warn('     tenant@demo.com / 12345678   (后台 /admin)');
        $this->command?->warn('     demo@member.com  / 12345678  (商城会员)');
        $this->command?->warn('════════════════════════════════════════════════════════');
    }
}
