<?php

namespace App\Filament\Resources\PointLedgers;

use App\Filament\Resources\PointLedgers\Pages\CreatePointLedger;
use App\Filament\Resources\PointLedgers\Pages\EditPointLedger;
use App\Filament\Resources\PointLedgers\Pages\ListPointLedgers;
use App\Filament\Resources\PointLedgers\Schemas\PointLedgerForm;
use App\Filament\Resources\PointLedgers\Tables\PointLedgersTable;
use App\Models\PointLedger;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class PointLedgerResource extends Resource
{
    protected static ?string $model = PointLedger::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedChartBar;

    protected static string|\UnitEnum|null $navigationGroup = '会员运营';

    protected static ?int $navigationSort = 33;

    protected static ?string $modelLabel = '积分流水';

    protected static ?string $pluralModelLabel = '积分明细';

    protected static ?string $recordTitleAttribute = 'id';

    // 积分流水只读：账本只能经 MembershipService::addPoints 产生，手工插入/篡改会破坏审计链
    public static function canCreate(): bool { return false; }

    public static function canEdit(\Illuminate\Database\Eloquent\Model $record): bool { return false; }

    public static function form(Schema $schema): Schema
    {
        return PointLedgerForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return PointLedgersTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListPointLedgers::route('/'),
            'create' => CreatePointLedger::route('/create'),
            'edit' => EditPointLedger::route('/{record}/edit'),
        ];
    }
}
