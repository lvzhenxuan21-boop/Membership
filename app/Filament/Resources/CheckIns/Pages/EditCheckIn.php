<?php

namespace App\Filament\Resources\CheckIns\Pages;

use App\Filament\Resources\CheckIns\CheckInResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditCheckIn extends EditRecord
{
    protected static string $resource = CheckInResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
