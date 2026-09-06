<?php

namespace App\Filament\Resources\ActivityLogs\Tables;

use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class ActivityLogsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('created_at')->dateTime()->label('时间')->sortable(),
                TextColumn::make('tenant.name')->label('商户')->badge()->toggleable(),
                TextColumn::make('user.name')->label('操作对象用户')->placeholder('—'),
                TextColumn::make('action')->badge()->label('事件')->searchable(),
                TextColumn::make('auditable_type')->label('关联对象')->formatStateUsing(fn ($state, $record) => $state ? class_basename($state).'#'.$record->auditable_id : '—')->toggleable(),
                TextColumn::make('new_values')->label('内容')->limit(60)->toggleable(),
                TextColumn::make('ip')->label('IP')->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([])
            ->recordActions([])
            ->toolbarActions([]);
    }
}
