<?php

namespace App\Filament\Widgets;

use App\Models\Order;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\DB;

/**
 * 平台抽佣收入总览：SaaS 运营方最关心的"我的佣金在哪看"。
 * 数据范围：平台管理员看全平台；租户管理员因租户全局作用域自动只看本租户订单的佣金。
 * 口径：已收订单（paid/shipped/completed）的 platform_fee；取消/退款单不计。
 */
class PlatformRevenue extends StatsOverviewWidget
{
    protected static ?int $sort = 2;

    protected function getStats(): array
    {
        $realized = fn () => Order::query()->whereIn('status', ['paid', 'shipped', 'completed']);

        $totalFee = (clone $realized)()->sum('platform_fee');
        $monthFee = (clone $realized)()->whereBetween('paid_at', [now()->startOfMonth(), now()->endOfMonth()])->sum('platform_fee');
        $todayFee = (clone $realized)()->whereBetween('paid_at', [today()->startOfDay(), today()->endOfDay()])->sum('platform_fee');
        $gmv = (clone $realized)()->sum('total_amount');

        return [
            Stat::make('平台佣金（累计）', '¥ ' . number_format((float) $totalFee, 2))
                ->description('已收订单抽佣合计')
                ->color('success')
                ->icon('heroicon-o-banknotes')
                ->chart($this->lastDaysFee()),
            Stat::make('本月佣金', '¥ ' . number_format((float) $monthFee, 2))
                ->description(now()->format('Y年m月'))
                ->color('primary')
                ->icon('heroicon-o-calendar-days'),
            Stat::make('今日佣金', '¥ ' . number_format((float) $todayFee, 2))
                ->description(today()->format('Y年m月d日'))
                ->color('warning')
                ->icon('heroicon-o-clock'),
            Stat::make('累计成交额', '¥ ' . number_format((float) $gmv, 2))
                ->description('已收订单商品总额（GMV）')
                ->color('info')
                ->icon('heroicon-o-shopping-cart'),
        ];
    }

    /** 近 7 天佣金趋势（供迷你图表） */
    private function lastDaysFee(): array
    {
        $rows = Order::query()
            ->whereIn('status', ['paid', 'shipped', 'completed'])
            ->where('paid_at', '>=', today()->subDays(6)->startOfDay())
            ->groupBy('day')
            ->orderBy('day')
            ->get([DB::raw('DATE(paid_at) as day'), DB::raw('SUM(platform_fee) as fee')])
            ->pluck('fee', 'day');

        $chart = [];
        for ($i = 6; $i >= 0; $i--) {
            $day = today()->subDays($i)->toDateString();
            $chart[] = round((float) ($rows[$day] ?? 0), 2);
        }
        return $chart;
    }
}
