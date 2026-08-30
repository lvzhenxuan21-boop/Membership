<?php

namespace App\Console\Commands;

use App\Models\ActivityLog;
use App\Models\MembershipLevel;
use App\Models\MemberProfile;
use Illuminate\Console\Command;

class RelevelMemberships extends Command
{
    protected $signature = 'memberships:relevel';

    protected $description = '按保级周期重算会员等级：周期内成长值不达标的会员降级，并重置周期基线';

    public function handle(): int
    {
        $months = (int) config('membership.relevel_period_months', 12);
        $boundary = now()->subMonths($months);

        // 初始化从未设置过周期的档案（基线 = 当前累计成长值）
        $uninitialized = MemberProfile::whereNull('period_started_at')->get();
        foreach ($uninitialized as $profile) {
            $profile->update(['period_started_at' => now(), 'growth_base' => $profile->growth]);
        }
        if ($uninitialized->count()) {
            $this->info("初始化周期基线 {$uninitialized->count()} 位会员");
        }

        // 到期重算：周期内成长值（growth - growth_base）决定本周期可享的最高等级
        $due = MemberProfile::with('level')
            ->whereNotNull('period_started_at')
            ->where('period_started_at', '<', $boundary)
            ->get();

        $downgraded = 0;
        foreach ($due as $profile) {
            $periodGrowth = max(0, $profile->growth - $profile->growth_base);
            $target = MembershipLevel::where('tenant_id', $profile->tenant_id)
                ->where('min_growth', '<=', $periodGrowth)
                ->orderByDesc('level')
                ->first();

            $from = $profile->level?->name ?? '无等级';
            if ($target && $target->id !== $profile->membership_level_id) {
                $profile->update(['membership_level_id' => $target->id]);
                if ($target->level < (int) ($profile->level?->level ?? 1)) $downgraded++;
                ActivityLog::create([
                    'tenant_id' => $profile->tenant_id,
                    'user_id' => $profile->user_id,
                    'action' => 'membership.relevel',
                    'auditable_type' => MemberProfile::class,
                    'auditable_id' => $profile->id,
                    'new_values' => ['from' => $from, 'to' => $target->name, 'period_growth' => $periodGrowth, 'period_months' => $months],
                ]);
                $this->line("会员#{$profile->id} ({$profile->member_no}) {$from} -> {$target->name}（周期成长 {$periodGrowth}）");
            }

            // 重置周期基线
            $profile->update(['period_started_at' => now(), 'growth_base' => $profile->growth]);
        }

        $this->info("周期重算完成：处理 {$due->count()} 位会员，降级 {$downgraded} 位");
        return self::SUCCESS;
    }
}
