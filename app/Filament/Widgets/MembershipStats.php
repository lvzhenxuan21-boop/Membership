<?php

namespace App\Filament\Widgets;

use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class MembershipStats extends StatsOverviewWidget
{
    protected function getStats(): array
    {
        return [
            Stat::make('商户', \App\Models\Tenant::count())->description('租户总数')->color('primary')->icon('heroicon-o-building-office'),
            Stat::make('会员', \App\Models\MemberProfile::count())->description('总会员数')->color('success')->icon('heroicon-o-users'),
            Stat::make('订阅', \App\Models\Subscription::where('status','active')->count())->description('有效订阅')->color('warning')->icon('heroicon-o-clipboard-document-list'),
            Stat::make('积分流水', \App\Models\PointLedger::count())->description('累计记录')->color('info')->icon('heroicon-o-chart-bar'),
            Stat::make('储值', '¥ '.number_format(\App\Models\Wallet::sum('balance'),2))->description('总余额')->color('success')->icon('heroicon-o-wallet'),
            Stat::make('门店', \App\Models\Branch::count())->description('分支数')->color('gray')->icon('heroicon-o-map-pin'),
        ];
    }
}
