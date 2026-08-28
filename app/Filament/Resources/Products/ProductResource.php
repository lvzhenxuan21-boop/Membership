<?php

namespace App\Filament\Resources\Products;

use App\Filament\Resources\Products\Pages\CreateProduct;
use App\Filament\Resources\Products\Pages\EditProduct;
use App\Filament\Resources\Products\Pages\ListProducts;
use App\Filament\Resources\Products\Schemas\ProductForm;
use App\Filament\Resources\Products\Tables\ProductsTable;
use App\Models\Product;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class ProductResource extends Resource
{
    protected static ?string $model = Product::class;
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCube;
    protected static string|\UnitEnum|null $navigationGroup = '电商管理';
    protected static ?int $navigationSort = 2;
    protected static ?string $modelLabel = '商品';
    protected static ?string $pluralModelLabel = '商品管理';
    protected static ?string $recordTitleAttribute = 'name';
    public static function form(Schema $schema): Schema { return ProductForm::configure($schema); }
    public static function table(Table $table): Table { return ProductsTable::configure($table); }
    public static function getRelations(): array { return []; }
    public static function getPages(): array
    {
        return ['index'=>ListProducts::route('/'),'create'=>CreateProduct::route('/create'),'edit'=>EditProduct::route('/{record}/edit')];
    }
}
