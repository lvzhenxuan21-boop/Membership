<?php

namespace App\Filament\Resources\Payments\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\ViewAction;
use Filament\Actions\Action;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use App\Services\PaymentService;
use Illuminate\Support\Facades\Log;

class PaymentsTable
{
    // 核销/退款属资金操作，与 API 端 role:super_admin|admin|tenant_admin 保持一致
    private static function isAdmin(): bool
    {
        return auth()->user()?->hasAnyRole(['super_admin','admin','tenant_admin']) ?? false;
    }

    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('order_no')->searchable()->label('单号')->copyable(),
                TextColumn::make('tenant.name')->label('商户')->searchable()->toggleable(),
                TextColumn::make('user.name')->label('用户')->searchable(),
                TextColumn::make('subject')->searchable()->label('标题')->limit(20),
                TextColumn::make('amount')->money('CNY')->sortable()->label('实付'),
                TextColumn::make('channel')->badge()->label('渠道')
                    ->colors(['primary'=>'mock','success'=>'stripe','warning'=>'wechat','info'=>'alipay','gray'=>'wallet','secondary'=>'manual']),
                TextColumn::make('status')->badge()->label('状态')
                    ->colors(['warning'=>'pending','success'=>'paid','danger'=>'failed','gray'=>'cancelled','info'=>'refunded']),
                TextColumn::make('business_type')->label('业务')->badge(),
                TextColumn::make('paid_at')->dateTime()->sortable()->label('支付时间')->toggleable(),
                TextColumn::make('created_at')->dateTime()->sortable()->toggleable(isToggledHiddenByDefault:true),
            ])
            ->filters([])
            ->recordActions([
                ViewAction::make(), // 详情只读：直接改行会绕过 PaymentService 业务钩子（见 PaymentPolicy）
                Action::make('markPaid')
                    ->label('标记已付')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->visible(fn($record)=> $record->status==='pending' && self::isAdmin())
                    ->requiresConfirmation()
                    ->action(function ($record) {
                        try {
                            app(PaymentService::class)->markPaid($record);
                        } catch (\Throwable $e) {
                            Log::warning('[Filament] 手动核销失败', ['order_no'=>$record->order_no, 'error'=>$e->getMessage()]);
                            \Filament\Notifications\Notification::make()->title('核销失败：'.$e->getMessage())->danger()->send();
                        }
                    }),
                Action::make('refund')
                    ->label('退款')
                    ->icon('heroicon-o-arrow-path')
                    ->color('danger')
                    ->visible(fn($record)=> $record->status==='paid' && self::isAdmin())
                    ->requiresConfirmation()
                    ->action(function ($record) {
                        try {
                            app(PaymentService::class)->refund($record);
                        } catch (\Throwable $e) {
                            Log::warning('[Filament] 退款失败', ['order_no'=>$record->order_no, 'error'=>$e->getMessage()]);
                            \Filament\Notifications\Notification::make()->title('退款失败：'.$e->getMessage())->danger()->send();
                        }
                    }),
            ])
            ->toolbarActions([BulkActionGroup::make([DeleteBulkAction::make()])]);
    }
}
