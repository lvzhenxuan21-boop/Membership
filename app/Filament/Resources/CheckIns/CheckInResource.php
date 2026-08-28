<?php

namespace App\Filament\Resources\CheckIns;

use App\Filament\Resources\CheckIns\Pages\CreateCheckIn;
use App\Filament\Resources\CheckIns\Pages\EditCheckIn;
use App\Filament\Resources\CheckIns\Pages\ListCheckIns;
use App\Filament\Resources\CheckIns\Schemas\CheckInForm;
use App\Filament\Resources\CheckIns\Tables\CheckInsTable;
use App\Models\CheckIn;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class CheckInResource extends Resource
{
    protected static ?string $model = CheckIn::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCalendarDays;

    protected static string|\UnitEnum|null $navigationGroup = '会员运营';

    protected static ?int $navigationSort = 34;

    protected static ?string $modelLabel = '签到';

    protected static ?string $pluralModelLabel = '签到记录';

    protected static ?string $recordTitleAttribute = 'id';

    public static function form(Schema $schema): Schema
    {
        return CheckInForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return CheckInsTable::configure($table);
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
            'index' => ListCheckIns::route('/'),
            'create' => CreateCheckIn::route('/create'),
            'edit' => EditCheckIn::route('/{record}/edit'),
        ];
    }
}
