<?php

namespace App\Filament\Resources\PointLedgers\Pages;

use App\Filament\Resources\PointLedgers\PointLedgerResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditPointLedger extends EditRecord
{
    protected static string $resource = PointLedgerResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
