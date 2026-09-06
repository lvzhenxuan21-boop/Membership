<?php

namespace App\Filament\Resources\Payments;

use App\Filament\Resources\Payments\Pages\ListPayments;
use App\Filament\Resources\Payments\Pages\ViewPayment;
use App\Filament\Resources\Payments\Schemas\PaymentForm;
use App\Filament\Resources\Payments\Tables\PaymentsTable;
use App\Models\Payment;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class PaymentResource extends Resource
{
    protected static ?string $model = Payment::class;
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCreditCard;
    protected static string|\UnitEnum|null $navigationGroup = '会员运营';
    protected static ?int $navigationSort = 20;
    protected static ?string $modelLabel = '支付单';
    protected static ?string $pluralModelLabel = '支付管理';
    protected static ?string $recordTitleAttribute = 'order_no';

    public static function form(Schema $schema): Schema { return PaymentForm::configure($schema); }
    public static function table(Table $table): Table { return PaymentsTable::configure($table); }
    public static function getRelations(): array { return []; }
    // 支付单只读：状态变更只能走列表页的核销/退款 Action（经 PaymentService），禁止后台手建/手改
    public static function canCreate(): bool { return false; }
    public static function getPages(): array
    {
        return [
            'index' => ListPayments::route('/'),
            'view' => ViewPayment::route('/{record}'),
        ];
    }
}
