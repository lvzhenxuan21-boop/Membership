<?php

namespace App\Filament\Resources\Products\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Schemas\Schema;

class ProductForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Select::make('tenant_id')->relationship('tenant','name')->required()->searchable()->preload()->reactive(),
            Select::make('shop_id')->relationship('shop','name')->required()->searchable()->preload(),
            TextInput::make('name')->required()->maxLength(100),
            TextInput::make('slug')->required()->maxLength(100)->unique(ignoreRecord:true),
            TextInput::make('price')->numeric()->required()->prefix('¥'),
            TextInput::make('original_price')->numeric()->prefix('¥'),
            TextInput::make('stock')->numeric()->default(100)->required(),
            Select::make('status')->options(['draft'=>'草稿','on_sale'=>'上架','off_sale'=>'下架'])->default('on_sale')->required(),
            TextInput::make('cover')->label('封面URL'),
            Textarea::make('description')->columnSpanFull(),
        ]);
    }
}
