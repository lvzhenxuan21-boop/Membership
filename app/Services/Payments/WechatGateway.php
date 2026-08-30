<?php

namespace App\Services\Payments;

use App\Models\Payment;
use Illuminate\Support\Facades\Log;

/**
 * 微信支付网关占位
 * 已预留 yansongda/pay 接入点：安装 yansongda/pay 后在此实现真实调用
 * 未配置密钥时自动降级为 Mock
 */
class WechatGateway implements GatewayInterface
{
    public function pay(Payment $payment): array
    {
        if ($this->isMockMode()) {
            Log::warning('[WechatGateway] 未配置或未安装 yansongda/pay，降级 Mock');
            return (new MockGateway)->pay($payment);
        }

        // 真实接入示例（需买家自行启用）：
        // $order = [
        //     'out_trade_no' => $payment->order_no,
        //     'description' => $payment->subject,
        //     'amount' => ['total' => (int)($payment->amount * 100), 'currency' => 'CNY'],
        //     'notify_url' => url("/api/v1/payment/callback/wechat"),
        // ];
        // $result = \Yansongda\Pay\Pay::wechat()->mp($order);
        // return ['pay_url'=>$result->get('code_url'), 'channel'=>'wechat', 'raw'=>$result];

        return [
            'pay_url' => url("/api/v1/payment/{$payment->order_no}/mock-pay"),
            'channel' => 'wechat',
            'mock' => true,
            'message' => '微信支付占位：请配置 WECHAT_PAY_* 并安装 yansongda/pay 后替换此处',
        ];
    }

    public function verifyWebhook(array $payload, ?string $signature = null, ?string $rawBody = null): bool
    {
        // 真实： return Pay::wechat()->verify($rawBody ?? $payload);
        return true;
    }

    public function query(Payment $payment): array { return ['status'=>$payment->status, 'channel'=>'wechat']; }
    public function refund(Payment $payment, ?float $amount = null): array { return ['success'=>true, 'channel'=>'wechat']; }
    // 未配置 WECHAT_PAY_MCH_ID 或未安装 yansongda/pay 时视为 Mock 模式（与 pay() 降级条件保持一致）
    public function isMockMode(): bool
    {
        return empty(config('payments.channels.wechat.mch_id')) || !class_exists(\Yansongda\Pay\Pay::class);
    }
}
