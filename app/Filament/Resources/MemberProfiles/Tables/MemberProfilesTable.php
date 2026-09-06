<?php

namespace App\Filament\Resources\MemberProfiles\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use App\Services\MembershipService;
use Illuminate\Support\Facades\Log;

class MemberProfilesTable
{
    // 资金调整与 API 端 role:super_admin|admin|tenant_admin 保持一致
    private static function isAdmin(): bool
    {
        return auth()->user()?->hasAnyRole(['super_admin','admin','tenant_admin']) ?? false;
    }

    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('user.name')
                    ->searchable(),
                TextColumn::make('tenant.name')
                    ->searchable(),
                TextColumn::make('branch.name')
                    ->searchable(),
                TextColumn::make('membership_level_id')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('member_no')
                    ->searchable(),
                TextColumn::make('real_name')
                    ->searchable(),
                TextColumn::make('phone')
                    ->searchable(),
                TextColumn::make('id_card')
                    ->searchable(),
                TextColumn::make('birthday')
                    ->date()
                    ->sortable(),
                TextColumn::make('gender')
                    ->searchable(),
                TextColumn::make('avatar')
                    ->searchable(),
                TextColumn::make('points')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('growth')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('balance')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('total_orders')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('total_spent')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('joined_at')
                    ->dateTime()
                    ->sortable(),
                TextColumn::make('last_active_at')
                    ->dateTime()
                    ->sortable(),
                TextColumn::make('status')
                    ->searchable(),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                //
            ])
            ->recordActions([
                EditAction::make(),
                // 正规调整入口：走 MembershipService 事务，自动产生积分/钱包流水（表单直改已禁用）
                Action::make('adjustPoints')
                    ->label('调整积分')
                    ->icon('heroicon-o-star')
                    ->color('warning')
                    ->visible(fn ($record) => self::isAdmin())
                    ->schema([
                        TextInput::make('points')->numeric()->required()->label('变动积分（负数=扣减）'),
                        Select::make('type')->options(['earn'=>'入账','spend'=>'扣减','adjust'=>'人工调整'])->default('adjust')->required()->label('类型'),
                        TextInput::make('description')->maxLength(100)->default('后台人工调整')->label('备注'),
                    ])
                    ->action(function ($record, array $data) {
                        try {
                            $ledger = app(MembershipService::class)->addPoints(
                                $record->tenant_id, $record->user_id,
                                (int) $data['points'], $data['type'],
                                $data['description'] ?? '后台人工调整', 'admin', $record->id,
                            );
                            \Filament\Notifications\Notification::make()
                                ->title("积分已调整，流水余额：{$ledger->balance_after}")->success()->send();
                        } catch (\Throwable $e) {
                            Log::warning('[Filament] 调整积分失败', ['profile_id'=>$record->id, 'error'=>$e->getMessage()]);
                            \Filament\Notifications\Notification::make()->title('调整失败：'.$e->getMessage())->danger()->send();
                        }
                    }),
                Action::make('adjustBalance')
                    ->label('调整余额')
                    ->icon('heroicon-o-banknotes')
                    ->color('info')
                    ->visible(fn ($record) => self::isAdmin())
                    ->schema([
                        TextInput::make('amount')->numeric()->required()->label('变动金额（负数=扣减）'),
                        Select::make('type')->options(['recharge'=>'充值','consume'=>'消费'])->default('recharge')->required()->label('类型'),
                        TextInput::make('description')->maxLength(100)->default('后台人工调整')->label('备注'),
                    ])
                    ->action(function ($record, array $data) {
                        try {
                            $tx = app(MembershipService::class)->walletChange(
                                $record->tenant_id, $record->user_id,
                                (float) $data['amount'], $data['type'],
                                $data['description'] ?? '后台人工调整',
                            );
                            \Filament\Notifications\Notification::make()
                                ->title("余额已调整，钱包余额：{$tx->balance_after}")->success()->send();
                        } catch (\Throwable $e) {
                            Log::warning('[Filament] 调整余额失败', ['profile_id'=>$record->id, 'error'=>$e->getMessage()]);
                            \Filament\Notifications\Notification::make()->title('调整失败：'.$e->getMessage())->danger()->send();
                        }
                    }),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
