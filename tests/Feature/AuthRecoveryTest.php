<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\MembershipSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Tests\TestCase;

/**
 * 密码找回（web + API 共用 broker）与可选邮箱验证门禁。
 */
class AuthRecoveryTest extends TestCase
{
    use RefreshDatabase;

    private function seedDemo(): void
    {
        $this->seed(MembershipSeeder::class);
    }

    public function test_password_reset_link_sent_and_api_anti_enumeration(): void
    {
        $this->seedDemo();
        Notification::fake();

        // 已注册与未注册邮箱返回一致（防枚举），但只有已注册的真的发信
        $this->postJson('/api/v1/auth/forgot-password', ['email' => 'demo@member.com'])->assertStatus(200);
        $this->postJson('/api/v1/auth/forgot-password', ['email' => 'nobody@example.com'])->assertStatus(200);

        Notification::assertSentTo(
            User::where('email', 'demo@member.com')->firstOrFail(),
            \App\Notifications\QueuedResetPassword::class // 队列版通知（继承 ResetPassword）
        );
    }

    public function test_password_can_be_reset_with_valid_token(): void
    {
        $this->seedDemo();
        $user = User::where('email', 'demo@member.com')->firstOrFail();
        $token = Password::createToken($user);

        $this->postJson('/api/v1/auth/reset-password', [
            'token' => $token,
            'email' => $user->email,
            'password' => 'NewPass123',
            'password_confirmation' => 'NewPass123',
        ])->assertStatus(200);

        // 新密码可登录（旧密码失效）
        $this->post('/login', ['email' => $user->email, 'password' => 'NewPass123'])->assertRedirect('/');
        $this->post('/login', ['email' => $user->email, 'password' => '12345678'])->assertStatus(302);
    }

    public function test_invalid_reset_token_rejected(): void
    {
        $this->seedDemo();
        $user = User::where('email', 'demo@member.com')->firstOrFail();

        $this->postJson('/api/v1/auth/reset-password', [
            'token' => 'bogus-token',
            'email' => $user->email,
            'password' => 'NewPass123',
            'password_confirmation' => 'NewPass123',
        ])->assertStatus(422);
    }

    public function test_email_verification_gate_blocks_order_when_enabled(): void
    {
        $this->seedDemo();
        config(['membership.email_verification' => true]);
        $member = User::where('email', 'demo@member.com')->firstOrFail();

        // 未验证：下单被门禁拦截
        $this->actingAs($member, 'sanctum')
            ->postJson('/api/v1/shop/orders', [
                'shop_id' => 1, 'items' => [['product_id' => 1, 'quantity' => 1]],
            ])->assertStatus(403);

        // 验证后放行（走到业务校验层，shop 不存在 → 422 验证错误而非 403）
        $member->markEmailAsVerified();
        $this->actingAs($member, 'sanctum')
            ->postJson('/api/v1/shop/orders', [
                'shop_id' => 999999, 'items' => [['product_id' => 1, 'quantity' => 1]],
            ])->assertStatus(422);
    }

    public function test_email_verification_gate_is_silent_when_disabled(): void
    {
        $this->seedDemo(); // 默认关闭
        $member = User::where('email', 'demo@member.com')->firstOrFail();

        // 未验证但开关关闭：不会被 403 拦截（走到业务校验：shop 不存在 → 422）
        $this->actingAs($member, 'sanctum')
            ->postJson('/api/v1/shop/orders', [
                'shop_id' => 999999, 'items' => [['product_id' => 1, 'quantity' => 1]],
            ])->assertStatus(422);
    }
}
