<?php

namespace Tests\Feature;

use App\Models\Tenant;
use App\Models\User;
use App\Services\PaymentService;
use App\Services\Payments\PaymentGatewayFactory;
use Database\Seeders\MembershipSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PaymentSecurityTest extends TestCase
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

    // ---------- 回调验签 ----------

    // 测试专用一次性 RSA 密钥对（与生产无关），公钥充当"支付宝公钥"
    private const TEST_ALIPAY_PRIVATE_KEY = <<<'PEM'
-----BEGIN PRIVATE KEY-----
MIIEvQIBADANBgkqhkiG9w0BAQEFAASCBKcwggSjAgEAAoIBAQDEalKpTvVaYuFe
DpNvVpPT3M9xgjXAz6inAlTARmEB7tKNaLgNmg5CjuE9xOALDcosanReXBisR2Mc
RBXoUK2yiWIX+RLSMnAnlLFO4ygRPv2wjPHmUIRqHoB/K6TU+pTZzWQa6lvVWibE
P9GyBBYK7DARwkZBAtFNh9GKSEWyq28tP7vqFjZiQcjVpHyyhVJW3sF6KTlNc6De
NA5FZYWCCIxWEZ/inTOSx00HXdypAb3Oksa4IrX3PbRXNnmpmTaRXiNb45TSIBgx
bgO3Qe+Tfm6BaiwX68wWmjTwK24Fh4jcLLw+dguDRr+bVsxHtoNdDTvcrUxIt4il
r3c/eHuXAgMBAAECggEAHWqsdVMFXW4lwV0pflQB0TSRlQx4BH1ADb3u94US46Pj
FkQIHMa6kAQE0/13w0VjcYItajHHjYsmMDDk9GWbk3u5R0yJzGq6xWgi+LpTYTyF
Pjs9wiLDp35opviLDU13nE8t86IEnaWM3M46F7vo7pCFJS5q2zMS1IEc7uWixVQI
Zx2w/KA/YbZZaTupKKYQdRuMjZ32Z4ZinAOUKd42vU21VvebPLkDFd+HBMum4POY
NuUpJp+sr7xMR77mT8nkNoFbV24C0K0VoSueQh4nJOyxXD3F8DSKymWkCG4dWiXL
0/c6VuTkpwbxzjPZuYMNuOe3wfKwP6YmiSCj4uAxkQKBgQDr/5TsFbQp3UXlce9L
GcLaS9IYD6Hg726J/Z+5u/bg9NLx5MVMhkS+vziqCBwI3DDHuqOdb1ZgQbocOLgj
lBTO65y+VNWBxpN9CBXh9K86Bl5HY/G2FXJwJJlkKJ3vpfUg3yIcCBK7ETpdozZs
Thz9P+Vr0VrD7WtrpBa5Mx04WwKBgQDVD+oQ2gB39aD9kacvtfThyEfBFdHIGNSt
p0yZ4FON34dcdHkKp2bpHCt8XtZqDKJms/hXPu9oEYoG+3qCbBpbXIMUtdqH2wXK
cxaGfoYoNnyUJZQV35B1exgsaUFJ7MzUaEdwtLZwhyrpSSCsIfQrOqjmRkXMt2y1
5fRHpnZOdQKBgD/Mf0D3eRYcOIoXq/4cf72t46UXjMaXU8XAJ875TntwFBrKor/W
SH1cioAE4zdN9233OcYU1D//ZMW+W6FapelubphRrMqBmVuitO+5yykfkZsxHKYB
1EcWzdTy2gdwUP1K9Rio4g9qT+ICfnL3BwU7odTs6uGurGyUFoSImeyjAoGAEZaI
ue9lDoIGUihN5tBccK75zWShtqTmGZev6Rvtic6j++vZehmrkx6yMEgb5xE37sZ4
f6tAVBTukfj8efu2iUgvwevpKEHaToYFnAChznwA+LHJcazM3gXVTwU5UILtvbMG
ArXIQa3Gyw8wVUVQRKlI/AldBbM2lCVxbuC872kCgYEA5OLvlN7eV7oRglcPcx/k
uBdJQ4C6FyquK3si+7xvYYkyl7/cbPDv6Kz6kXuglQm1FOKGGfx7QhXPtQTvj0on
nkIin3AlaF7YsZeLocJrN3266B5dEsYrZcaABQ0NDtkPBxeLxbPQ/uAxnWaUbz8C
SQHtQDtTPK9UkYJMOPbXRhY=
-----END PRIVATE KEY-----
PEM;

    private const TEST_ALIPAY_PUBLIC_KEY = <<<'PEM'
-----BEGIN PUBLIC KEY-----
MIIBIjANBgkqhkiG9w0BAQEFAAOCAQ8AMIIBCgKCAQEAxGpSqU71WmLhXg6Tb1aT
09zPcYI1wM+opwJUwEZhAe7SjWi4DZoOQo7hPcTgCw3KLGp0XlwYrEdjHEQV6FCt
soliF/kS0jJwJ5SxTuMoET79sIzx5lCEah6Afyuk1PqU2c1kGupb1VomxD/RsgQW
CuwwEcJGQQLRTYfRikhFsqtvLT+76hY2YkHI1aR8soVSVt7Beik5TXOg3jQORWWF
ggiMVhGf4p0zksdNB13cqQG9zpLGuCK19z20VzZ5qZk2kV4jW+OU0iAYMW4Dt0Hv
k35ugWosF+vMFpo08CtuBYeI3Cy8PnYLg0a/m1bMR7aDXQ073K1MSLeIpa93P3h7
lwIDAQAB
-----END PUBLIC KEY-----
PEM;

    public function test_alipay_rsa2_callback_verification(): void
    {
        config(['payments.channels.alipay.app_id' => '2026000000000000', 'payments.channels.alipay.public_key' => self::TEST_ALIPAY_PUBLIC_KEY]);
        $gw = PaymentGatewayFactory::make('alipay');

        $payload = ['app_id' => '2026000000000000', 'out_trade_no' => 'PAY20260101000000AAAAAA',
            'trade_status' => 'TRADE_SUCCESS', 'total_amount' => '100.00'];
        ksort($payload);
        $signStr = implode('&', array_map(fn($k, $v) => "$k=$v", array_keys($payload), array_values($payload)));
        openssl_sign($signStr, $sign, self::TEST_ALIPAY_PRIVATE_KEY, OPENSSL_ALGO_SHA256);
        $payload['sign'] = base64_encode($sign);
        $payload['sign_type'] = 'RSA2'; // sign_type 不参与签名

        // 正确签名通过
        $this->assertTrue($gw->verifyWebhook($payload));

        // 篡改金额后验签失败
        $payload['total_amount'] = '1.00';
        $this->assertFalse($gw->verifyWebhook($payload));
    }

    public function test_alipay_callback_with_invalid_public_key_is_rejected(): void
    {
        config(['payments.channels.alipay.app_id' => '2026000000000000', 'payments.channels.alipay.public_key' => 'invalid-key']);
        $gw = PaymentGatewayFactory::make('alipay');
        $this->assertFalse($gw->verifyWebhook(['out_trade_no' => 'PAY123', 'trade_status' => 'TRADE_SUCCESS', 'sign' => 'forged']));
    }

    public function test_wechat_v2_callback_signature_is_verified(): void
    {
        config(['payments.channels.wechat.mch_id' => '1900000109', 'payments.channels.wechat.mch_key' => 'test-key-123']);
        $gw = PaymentGatewayFactory::make('wechat');

        $payload = ['appid' => 'wx123', 'mch_id' => '1900000109', 'out_trade_no' => 'PAY20260101000000AAAAAA',
            'result_code' => 'SUCCESS', 'total_fee' => 1000, 'nonce_str' => 'abc123'];
        ksort($payload);
        $signStr = urldecode(http_build_query(array_filter($payload, fn($v) => $v !== '' && $v !== null))).'&key=test-key-123';
        $payload['sign'] = strtoupper(md5($signStr));

        $this->assertTrue($gw->verifyWebhook($payload));

        // 篡改金额后签名不匹配
        $payload['total_fee'] = 1;
        $this->assertFalse($gw->verifyWebhook($payload));
    }

    public function test_callback_with_non_success_trade_status_does_not_mark_paid(): void
    {
        $this->seedDemo();
        // mock 渠道在测试环境允许回调，但支付宝非成功状态必须拒绝核销
        config(['payments.mock_enabled' => true]);
        $member = $this->memberUser();
        $svc = app(PaymentService::class);
        $payment = $svc->create($this->tenantId(), $member->id, 'wallet_recharge', 10, 'mock');

        $this->withoutExceptionHandling()->expectException(\RuntimeException::class);
        $svc->handleCallback('alipay', [
            'out_trade_no' => $payment->order_no,
            'trade_status' => 'WAIT_BUYER_PAY',
        ]);
    }

    // ---------- 生产环境禁用 Mock ----------

    public function test_mock_channel_is_blocked_when_disabled(): void
    {
        $this->seedDemo();
        config(['payments.mock_enabled' => false]); // 模拟生产
        $svc = app(PaymentService::class);
        $member = $this->memberUser();

        $this->expectException(\RuntimeException::class);
        $svc->create($this->tenantId(), $member->id, 'wallet_recharge', 100, 'mock');
    }

    public function test_unconfigured_real_channel_is_blocked_when_mock_disabled(): void
    {
        $this->seedDemo();
        config(['payments.mock_enabled' => false]);
        $svc = app(PaymentService::class);
        $member = $this->memberUser();

        // 微信未配置商户密钥，禁止降级 Mock
        $this->expectException(\RuntimeException::class);
        $svc->create($this->tenantId(), $member->id, 'wallet_recharge', 100, 'wechat');
    }

    public function test_mock_pay_endpoint_rejected_when_mock_disabled(): void
    {
        $this->seedDemo();
        config(['payments.mock_enabled' => true]);
        $member = $this->memberUser();
        $svc = app(PaymentService::class);
        $payment = $svc->create($this->tenantId(), $member->id, 'wallet_recharge', 10, 'mock');

        config(['payments.mock_enabled' => false]); // 模拟生产
        $this->actingAs($member, 'sanctum')
            ->postJson("/api/v1/payment/{$payment->order_no}/mock-pay")
            ->assertStatus(422);
    }

    public function test_zero_amount_payment_succeeds_without_mock_channel(): void
    {
        $this->seedDemo();
        config(['payments.mock_enabled' => false]);
        $member = $this->memberUser();
        // 钱包渠道 0 元单：不应抛"生产禁 mock"，直接成功
        // （wallet_recharge 业务不允许走 wallet 渠道，这里用 other 业务验证 0 元路径）
        $payment = app(PaymentService::class)->create($this->tenantId(), $member->id, 'other', 0, 'wallet');
        $this->assertTrue($payment->isPaid());
    }

    public function test_wallet_recharge_via_wallet_channel_is_rejected(): void
    {
        $this->seedDemo();
        // 钱包充值用余额支付 = 扣了又充，净额为零且退款时凭空加钱，必须拒绝
        $this->expectException(\InvalidArgumentException::class);
        app(PaymentService::class)->create(
            $this->tenantId(), $this->memberUser()->id, 'wallet_recharge', 100, 'wallet'
        );
    }

    public function test_wallet_channel_respects_enabled_switch(): void
    {
        $this->seedDemo();
        config(['payments.wallet_enabled' => false]);
        $this->expectException(\InvalidArgumentException::class);
        app(PaymentService::class)->create(
            $this->tenantId(), $this->memberUser()->id, 'other', 50, 'wallet'
        );
    }
}
