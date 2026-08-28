<?php

namespace App\Filament\Resources\Products\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class ProductsTable
{
    public static function configure(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('tenant.name')->label('商户')->searchable()->toggleable(),
            TextColumn::make('shop.name')->label('店铺')->searchable(),
            TextColumn::make('name')->searchable()->label('商品'),
            TextColumn::make('price')->money('CNY')->sortable(),
            TextColumn::make('stock')->sortable(),
            TextColumn::make('sales')->sortable()->label('销量'),
            TextColumn::make('status')->badge()->color(fn($s)=> match($s){'on_sale'=>'success','off_sale'=>'gray','draft'=>'warning',default=>'gray'}),
            TextColumn::make('created_at')->dateTime()->sortable()->toggleable(isToggledHiddenByDefault:true),
        ])->filters([])->recordActions([EditAction::make()])->toolbarActions([BulkActionGroup::make([DeleteBulkAction::make()])]);
    }
}
