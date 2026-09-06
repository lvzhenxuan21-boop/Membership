<?php

namespace App\Filament\Resources\MemberProfiles;

use App\Filament\Resources\MemberProfiles\Pages\CreateMemberProfile;
use App\Filament\Resources\MemberProfiles\Pages\EditMemberProfile;
use App\Filament\Resources\MemberProfiles\Pages\ListMemberProfiles;
use App\Filament\Resources\MemberProfiles\Schemas\MemberProfileForm;
use App\Filament\Resources\MemberProfiles\Tables\MemberProfilesTable;
use App\Models\MemberProfile;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class MemberProfileResource extends Resource
{
    protected static ?string $model = MemberProfile::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUsers;

    protected static string|\UnitEnum|null $navigationGroup = '会员运营';

    protected static ?int $navigationSort = 30;

    protected static ?string $modelLabel = '会员';

    protected static ?string $pluralModelLabel = '会员列表';

    protected static ?string $recordTitleAttribute = 'member_no';

    public static function form(Schema $schema): Schema
    {
        return MemberProfileForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return MemberProfilesTable::configure($table);
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
            'index' => ListMemberProfiles::route('/'),
            'edit' => EditMemberProfile::route('/{record}/edit'),
        ];
    }

    // 会员档案仅由注册/自助流程创建：手建档案绕过钱包初始化与默认等级，
    // 资金调整走列表页「调整积分/调整余额」Action（经 MembershipService 产生流水）
    public static function canCreate(): bool { return false; }
}
