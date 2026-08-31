<?php

namespace Tests\Feature;

use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\MembershipSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ApiAuthTest extends TestCase
{
    use RefreshDatabase;

    private function seedDemo(): void
    {
        $this->seed(MembershipSeeder::class);
    }

    private function memberUser(): User
    {
        return User::where('email', 'demo@member.com')->firstOrFail();
    }

    private function tenantId(): int
    {
        return Tenant::where('slug', 'demo')->firstOrFail()->id;
    }

    public function test_membership_and_payment_endpoints_require_authentication(): void
    {
        $this->seedDemo();

        $this->postJson('/api/v1/membership/points/add', [
            'tenant_id' => $this->tenantId(), 'user_id' => $this->memberUser()->id, 'points' => 99999, 'type' => 'earn',
        ])->assertStatus(401);

        $this->postJson('/api/v1/membership/wallet/recharge', [
            'tenant_id' => $this->tenantId(), 'user_id' => $this->memberUser()->id, 'amount' => 99999,
        ])->assertStatus(401);

        $this->postJson('/api/v1/membership/subscribe', [
            'tenant_id' => $this->tenantId(), 'user_id' => $this->memberUser()->id, 'plan_id' => 1,
        ])->assertStatus(401);

        $this->postJson('/api/v1/payment', [
            'tenant_id' => $this->tenantId(), 'user_id' => $this->memberUser()->id,
            'business_type' => 'wallet_recharge', 'amount' => 1000,
        ])->assertStatus(401);

        $this->getJson('/api/v1/payment/PAY20260101000000abcdef')->assertStatus(401);
        $this->getJson('/api/v1/payment/PAY20260101000000abcdef/query')->assertStatus(401);
        $this->postJson('/api/v1/payment/PAY20260101000000abcdef/mock-pay')->assertStatus(401);
        $this->postJson('/api/v1/payment/PAY20260101000000abcdef/cancel')->assertStatus(401);
        $this->getJson('/api/v1/membership/profile/'.$this->memberUser()->id)->assertStatus(401);
    }

    public function test_member_cannot_add_points_or_change_wallet(): void
    {
        $this->seedDemo();
        $member = $this->memberUser();

        $this->actingAs($member, 'sanctum')
            ->postJson('/api/v1/membership/points/add', [
                'tenant_id' => $this->tenantId(), 'user_id' => $member->id, 'points' => 99999, 'type' => 'earn',
            ])->assertStatus(403);

        $this->actingAs($member, 'sanctum')
            ->postJson('/api/v1/membership/wallet/recharge', [
                'tenant_id' => $this->tenantId(), 'user_id' => $member->id, 'amount' => 99999,
            ])->assertStatus(403);
    }

    public function test_member_cannot_create_payment_for_another_user(): void
    {
        $this->seedDemo();
        $member = $this->memberUser();
        $other = User::where('email', 'tenant@demo.com')->firstOrFail();

        $this->actingAs($member, 'sanctum')
            ->postJson('/api/v1/payment', [
                'tenant_id' => $this->tenantId(), 'user_id' => $other->id,
                'business_type' => 'wallet_recharge', 'amount' => 1000,
            ])->assertStatus(403);
    }

    public function test_member_cannot_view_others_profile_or_payment(): void
    {
        $this->seedDemo();
        $member = $this->memberUser();
        $other = User::where('email', 'tenant@demo.com')->firstOrFail();

        $this->actingAs($member, 'sanctum')
            ->getJson('/api/v1/membership/profile/'.$other->id)
            ->assertStatus(403);

        // 管理员创建一张属于别人的支付单，普通会员不可见
        $admin = $other;
        $resp = $this->actingAs($admin, 'sanctum')
            ->postJson('/api/v1/payment', [
                'tenant_id' => $this->tenantId(), 'user_id' => $other->id,
                'business_type' => 'wallet_recharge', 'amount' => 100, 'channel' => 'mock',
            ])->assertStatus(201);
        $orderNo = $resp->json('order_no');

        $this->actingAs($member, 'sanctum')
            ->getJson("/api/v1/payment/{$orderNo}")
            ->assertStatus(403);

        $this->actingAs($member, 'sanctum')
            ->postJson("/api/v1/payment/{$orderNo}/mock-pay")
            ->assertStatus(403);

        // 支付单列表只返回自己的
        $list = $this->actingAs($member, 'sanctum')->getJson('/api/v1/payment')->assertStatus(200);
        $list->assertJsonCount(0, 'data');
    }

    public function test_member_can_mock_pay_own_payment(): void
    {
        $this->seedDemo();
        $member = $this->memberUser();

        $resp = $this->actingAs($member, 'sanctum')
            ->postJson('/api/v1/payment', [
                'tenant_id' => $this->tenantId(),
                'business_type' => 'wallet_recharge', 'amount' => 50, 'channel' => 'mock',
            ])->assertStatus(201);
        $orderNo = $resp->json('order_no');
        // 支付单归属登录用户而非请求体伪造的 user_id
        $this->assertEquals($member->id, $resp->json('user_id'));

        $this->actingAs($member, 'sanctum')
            ->postJson("/api/v1/payment/{$orderNo}/mock-pay")
            ->assertStatus(200)
            ->assertJsonPath('ok', true);
    }

    public function test_web_session_user_can_mock_pay_via_stateful_api(): void
    {
        $this->seedDemo();
        $member = $this->memberUser();

        // 模拟 web 前端 fetch：无 Bearer token，仅 session cookie（同域 stateful）
        $resp = $this->actingAs($member) // 默认 web guard，走 session
            ->postJson('/api/v1/payment', [
                'tenant_id' => $this->tenantId(),
                'business_type' => 'wallet_recharge', 'amount' => 10, 'channel' => 'mock',
            ])->assertStatus(201);
        $orderNo = $resp->json('order_no');

        $this->actingAs($member)
            ->postJson("/api/v1/payment/{$orderNo}/mock-pay")
            ->assertStatus(200)
            ->assertJsonPath('ok', true);
    }

    public function test_only_admin_can_grant_subscription_directly(): void
    {
        $this->seedDemo();
        $member = $this->memberUser();
        $admin = User::where('email', 'tenant@demo.com')->firstOrFail();
        $planId = \App\Models\MembershipPlan::where('tenant_id', $this->tenantId())->firstOrFail()->id;

        // 免费自助开通旁路已堵死：会员直开会员被拒，开通必须走支付流程
        $this->actingAs($member, 'sanctum')
            ->postJson('/api/v1/membership/subscribe', ['tenant_id' => $this->tenantId(), 'plan_id' => $planId])
            ->assertStatus(403);

        // 管理员可手工开通（补偿等场景）
        $this->actingAs($admin, 'sanctum')
            ->postJson('/api/v1/membership/subscribe', ['tenant_id' => $this->tenantId(), 'user_id' => $member->id, 'plan_id' => $planId])
            ->assertStatus(201)
            ->assertJsonPath('status', 'active');
    }

    public function test_member_can_view_own_profile(): void
    {
        $this->seedDemo();
        $member = $this->memberUser();

        $resp = $this->actingAs($member, 'sanctum')
            ->getJson('/api/v1/membership/profile/'.$member->id.'?tenant_id='.$this->tenantId())
            ->assertStatus(200);
        $resp->assertJsonPath('profile.user_id', $member->id);
        $resp->assertJsonPath('profile.points', 100);
        $resp->assertJsonPath('wallet.balance', '0.00');
    }

    public function test_web_login_is_rate_limited(): void
    {
        $this->seedDemo();
        foreach (range(1, 10) as $i) {
            $this->post('/login', ['email' => 'demo@member.com', 'password' => 'wrong']);
        }
        $this->post('/login', ['email' => 'demo@member.com', 'password' => 'wrong'])->assertStatus(429);
    }

    public function test_api_login_is_rate_limited(): void
    {
        $this->seedDemo();
        foreach (range(1, 10) as $i) {
            $this->postJson('/api/v1/auth/login', ['email' => 'demo@member.com', 'password' => 'wrong']);
        }
        $this->postJson('/api/v1/auth/login', ['email' => 'demo@member.com', 'password' => 'wrong'])->assertStatus(429);
    }

    public function test_public_catalog_stays_open(): void
    {
        $this->seedDemo();
        $tid = $this->tenantId();
        $this->getJson("/api/v1/membership/plans?tenant_id={$tid}")->assertStatus(200);
        $this->getJson("/api/v1/membership/levels?tenant_id={$tid}")->assertStatus(200);
        $this->getJson('/api/v1/health')->assertStatus(200);
    }
}
