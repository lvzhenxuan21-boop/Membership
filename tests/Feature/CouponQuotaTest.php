<?php

namespace Tests\Feature;

use App\Models\Coupon;
use App\Models\Payment;
use App\Models\Tenant;
use App\Models\User;
use App\Services\PaymentService;
use Database\Seeders\MembershipSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class CouponQuotaTest extends TestCase
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

    private function otherUser(): User
    {
        return User::firstOrCreate(['email'=>'other@member.com'], ['name'=>'其他会员','password'=>Hash::make('12345678')]);
    }

    private function tenantId(): int
    {
        return Tenant::where('slug', 'demo')->firstOrFail()->id;
    }

    private function coupon(): Coupon
    {
        return Coupon::where('code', 'WELCOME10')->firstOrFail(); // 满59减10，每人限1次，总量1000
    }

    private function createPayment(User $user, ?int $couponId = null): Payment
    {
        return app(PaymentService::class)->create(
            $this->tenantId(), $user->id, 'wallet_recharge', 100, 'mock', ['coupon_id'=>$couponId]
        );
    }

    public function test_coupon_discount_applied_and_quota_reserved_on_create(): void
    {
        $this->seedDemo();
        $coupon = $this->coupon();
        $before = (int)$coupon->used_count;

        $payment = $this->createPayment($this->memberUser(), $coupon->id);

        $this->assertEquals(10, (float)$payment->discount_amount);
        $this->assertEquals(90, (float)$payment->amount);
        $this->assertEquals($before + 1, (int)$coupon->fresh()->used_count, '创建支付单即占用名额');
    }

    public function test_paying_does_not_double_count_quota(): void
    {
        $this->seedDemo();
        $coupon = $this->coupon();
        $payment = $this->createPayment($this->memberUser(), $coupon->id);
        $afterCreate = (int)$coupon->fresh()->used_count;

        app(PaymentService::class)->markPaid($payment);

        $this->assertEquals($afterCreate, (int)$coupon->fresh()->used_count, '支付成功不重复计数');
    }

    public function test_cancel_releases_reserved_quota(): void
    {
        $this->seedDemo();
        $coupon = $this->coupon();
        $user = $this->memberUser();
        $before = (int)$coupon->used_count;

        $payment = $this->createPayment($user, $coupon->id);
        $this->assertEquals($before + 1, (int)$coupon->fresh()->used_count);

        app(PaymentService::class)->cancel($payment);
        $this->assertEquals($before, (int)$coupon->fresh()->used_count, '取消释放名额');

        // 释放后可再次使用（per_user_limit=1 不再拦截）
        $second = $this->createPayment($user, $coupon->id);
        $this->assertEquals('pending', $second->status);
    }

    public function test_quota_exhausted_rejects_new_payment(): void
    {
        $this->seedDemo();
        $coupon = $this->coupon();
        $coupon->update(['total_quota' => 2, 'used_count' => 2]);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('优惠券已被领完');
        $this->createPayment($this->memberUser(), $coupon->id);
    }

    public function test_per_user_limit_blocks_second_pending_or_paid_use(): void
    {
        $this->seedDemo();
        $coupon = $this->coupon();
        $user = $this->memberUser();

        $first = $this->createPayment($user, $coupon->id);
        $this->assertEquals('pending', $first->status);

        // 同一用户第二张（即使第一张还没支付）被拒绝
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('已达使用上限');
        $this->createPayment($user, $coupon->id);
    }

    public function test_per_user_limit_is_per_user(): void
    {
        $this->seedDemo();
        $coupon = $this->coupon();

        $first = $this->createPayment($this->memberUser(), $coupon->id);
        $second = $this->createPayment($this->otherUser(), $coupon->id);

        $this->assertEquals('pending', $first->status);
        $this->assertEquals('pending', $second->status);
        $this->assertEquals(2, (int)$coupon->fresh()->used_count);
    }

    public function test_paid_use_counts_toward_per_user_limit_until_released(): void
    {
        $this->seedDemo();
        $coupon = $this->coupon();
        $user = $this->memberUser();
        $svc = app(PaymentService::class);

        $paid = $this->createPayment($user, $coupon->id);
        $svc->markPaid($paid);

        $this->expectException(\RuntimeException::class);
        $svc->create($this->tenantId(), $user->id, 'wallet_recharge', 100, 'mock', ['coupon_id'=>$coupon->id]);
    }

    public function test_min_amount_threshold_enforced(): void
    {
        $this->seedDemo();
        $coupon = $this->coupon();

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('未达到满减门槛');
        app(PaymentService::class)->create($this->tenantId(), $this->memberUser()->id, 'wallet_recharge', 50, 'mock', ['coupon_id'=>$coupon->id]);
    }

    public function test_api_returns_422_when_quota_exhausted(): void
    {
        $this->seedDemo();
        $coupon = $this->coupon();
        $coupon->update(['total_quota' => 1, 'used_count' => 1]);
        $member = $this->memberUser();

        $this->actingAs($member, 'sanctum')
            ->postJson('/api/v1/payment', [
                'tenant_id' => $this->tenantId(),
                'business_type' => 'wallet_recharge', 'amount' => 100, 'channel' => 'mock',
                'coupon_id' => $coupon->id,
            ])
            ->assertStatus(422)
            ->assertJsonPath('message', '优惠券已被领完');
    }

    public function test_switch_channel_carries_coupon_over(): void
    {
        $this->seedDemo();
        $coupon = $this->coupon();
        $user = $this->memberUser();
        $plan = \App\Models\MembershipPlan::where('tenant_id', $this->tenantId())->where('slug', 'yearly')->firstOrFail();
        $svc = app(PaymentService::class);

        $old = $svc->create($this->tenantId(), $user->id, 'subscription', 199, 'mock', ['plan_id' => $plan->id, 'coupon_id' => $coupon->id]);
        $this->assertEquals(189, (float)$old->amount);

        $this->actingAs($user)
            ->post("/pay/{$old->order_no}/switch-channel", ['channel' => 'alipay'])
            ->assertRedirect();

        $this->assertEquals('cancelled', $old->fresh()->status);
        $new = Payment::where('tenant_id', $this->tenantId())->where('user_id', $user->id)->where('channel', 'alipay')->latest('id')->first();
        $this->assertNotNull($new);
        $this->assertEquals($coupon->id, $new->coupon_id, '切换渠道继承优惠券');
        $this->assertEquals(189, (float)$new->amount);
        $this->assertEquals('pending', $new->status);
        // 名额守恒：旧单释放、新单占用，used_count 不净增
        $this->assertEquals(1, (int)$coupon->fresh()->used_count);
    }
}
