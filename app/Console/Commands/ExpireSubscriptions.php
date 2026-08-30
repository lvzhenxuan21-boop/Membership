<?php

namespace App\Console\Commands;

use App\Models\ActivityLog;
use App\Models\Subscription;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;

class ExpireSubscriptions extends Command
{
    protected $signature = 'subscriptions:expire';

    protected $description = '处理到期订阅（置为 expired），并对即将到期的订阅发送提醒邮件';

    public function handle(): int
    {
        $this->expireOverdue();
        $this->remindUpcoming();
        return self::SUCCESS;
    }

    // 已到期：active 且 ends_at 已过 -> expired
    private function expireOverdue(): void
    {
        $due = Subscription::query()
            ->where('status', 'active')
            ->whereNotNull('ends_at')
            ->where('ends_at', '<', now())
            ->get();

        foreach ($due as $sub) {
            $sub->update(['status' => 'expired']);
            ActivityLog::create([
                'tenant_id' => $sub->tenant_id,
                'user_id' => $sub->user_id,
                'action' => 'subscription.expired',
                'auditable_type' => Subscription::class,
                'auditable_id' => $sub->id,
                'new_values' => ['order_no' => $sub->order_no, 'ends_at' => optional($sub->ends_at)->toDateTimeString()],
            ]);
            $this->line("已过期: {$sub->order_no}");
        }
        $this->info("到期处理 {$due->count()} 笔");
    }

    // 即将到期（N 天内）：邮件提醒 + 审计日志，meta 记录已提醒防止重复发送
    private function remindUpcoming(): void
    {
        $days = (int) config('membership.subscription_remind_days', 3);
        $soon = Subscription::query()
            ->with('user')
            ->where('status', 'active')
            ->whereNotNull('ends_at')
            ->whereBetween('ends_at', [now(), now()->addDays($days)])
            ->where(function ($q) {
                $q->whereNull('meta->expiry_notified_at')
                  ->orWhere('meta->expiry_notified_at', '<', now()->subDays(1)->toDateTimeString());
            })
            ->get();

        $sent = 0;
        foreach ($soon as $sub) {
            $email = $sub->user?->email;
            if ($email) {
                try {
                    Mail::raw(
                        "您的会员订阅 {$sub->order_no} 将于 " . $sub->ends_at->format('Y-m-d H:i') . " 到期，请及时续费以保留会员权益。",
                        function ($message) use ($email) {
                            $message->to($email)->subject('会员订阅即将到期提醒');
                        }
                    );
                    $sent++;
                } catch (\Throwable $e) {
                    $this->warn("{$sub->order_no} 邮件发送失败: {$e->getMessage()}");
                }
            }
            $meta = $sub->meta ?? [];
            $meta['expiry_notified_at'] = now()->toDateTimeString();
            $sub->update(['meta' => $meta]);
            ActivityLog::create([
                'tenant_id' => $sub->tenant_id,
                'user_id' => $sub->user_id,
                'action' => 'subscription.expiry_reminded',
                'auditable_type' => Subscription::class,
                'auditable_id' => $sub->id,
                'new_values' => ['order_no' => $sub->order_no, 'ends_at' => optional($sub->ends_at)->toDateTimeString(), 'days' => $days],
            ]);
        }
        $this->info("到期提醒 {$soon->count()} 笔（邮件发送成功 {$sent}）");
    }
}
