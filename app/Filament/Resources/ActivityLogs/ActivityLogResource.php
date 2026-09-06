<?php

namespace App\Filament\Resources\ActivityLogs;

use App\Filament\Resources\ActivityLogs\Pages\ListActivityLogs;
use App\Filament\Resources\ActivityLogs\Tables\ActivityLogsTable;
use App\Models\ActivityLog;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class ActivityLogResource extends Resource
{
    protected static ?string $model = ActivityLog::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClipboardDocumentList;

    protected static string|\UnitEnum|null $navigationGroup = '系统管理';

    protected static ?int $navigationSort = 5;

    protected static ?string $modelLabel = '审计日志';

    protected static ?string $pluralModelLabel = '审计日志';

    protected static ?string $recordTitleAttribute = 'action';

    // 审计日志只读：由系统事件写入，后台仅查看，禁止增删改
    public static function canCreate(): bool { return false; }

    public static function canEdit(\Illuminate\Database\Eloquent\Model $record): bool { return false; }

    public static function table(\Filament\Tables\Table $table): Table
    {
        return ActivityLogsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListActivityLogs::route('/'),
        ];
    }
}
