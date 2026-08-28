<?php

namespace App\Filament\Resources\Shops\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class ShopsTable
{
    public static function configure(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('tenant.name')->searchable()->label('商户'),
            TextColumn::make('name')->searchable()->label('店铺'),
            TextColumn::make('slug')->searchable()->label('Slug'),
            TextColumn::make('status')->badge()->color(fn(string $state)=> match($state){'active'=>'success','closed'=>'gray','suspended'=>'danger', default=>'gray'}),
            TextColumn::make('platform_fee_rate')->label('抽佣')->formatStateUsing(fn($state)=> ($state*100).'%'),
            TextColumn::make('created_at')->dateTime()->sortable()->toggleable(isToggledHiddenByDefault:true),
        ])->filters([])->recordActions([EditAction::make()])->toolbarActions([BulkActionGroup::make([DeleteBulkAction::make()])]);
    }
}
