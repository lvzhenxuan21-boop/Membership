<?php

namespace App\Filament\Resources\Orders;

use App\Filament\Resources\Orders\Pages\ListOrders;
use App\Filament\Resources\Orders\Pages\ViewOrder;
use App\Filament\Resources\Orders\Schemas\OrderForm;
use App\Filament\Resources\Orders\Tables\OrdersTable;
use App\Models\Order;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class OrderResource extends Resource
{
    protected static ?string $model = Order::class;
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedShoppingCart;
    protected static string|\UnitEnum|null $navigationGroup = '电商管理';
    protected static ?int $navigationSort = 3;
    protected static ?string $modelLabel = '订单';
    protected static ?string $pluralModelLabel = '订单管理';
    protected static ?string $recordTitleAttribute = 'order_no';
    public static function form(Schema $schema): Schema { return OrderForm::configure($schema); }
    public static function table(Table $table): Table { return OrdersTable::configure($table); }
    public static function getRelations(): array { return []; }
    // 订单只读：状态变更只能走列表页的核销/发货/完成 Action，禁止后台手改状态/金额
    public static function getPages(): array
    {
        return ['index'=>ListOrders::route('/'),'view'=>ViewOrder::route('/{record}')];
    }
    public static function canCreate(): bool { return false; } // 订单仅前台创建
}
