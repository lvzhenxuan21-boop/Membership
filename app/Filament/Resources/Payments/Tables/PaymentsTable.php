<?php

namespace App\Filament\Resources\Payments\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\Action;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\BadgeColumn;
use Filament\Tables\Table;
use App\Services\PaymentService;

class PaymentsTable
{
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
                EditAction::make(),
                Action::make('markPaid')
                    ->label('标记已付')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->visible(fn($record)=> $record->status==='pending')
                    ->requiresConfirmation()
                    ->action(function ($record) {
                        app(PaymentService::class)->markPaid($record);
                    }),
                Action::make('refund')
                    ->label('退款')
                    ->icon('heroicon-o-arrow-path')
                    ->color('danger')
                    ->visible(fn($record)=> $record->status==='paid')
                    ->requiresConfirmation()
                    ->action(function ($record) {
                        app(PaymentService::class)->refund($record);
                    }),
            ])
            ->toolbarActions([BulkActionGroup::make([DeleteBulkAction::make()])]);
    }
}
