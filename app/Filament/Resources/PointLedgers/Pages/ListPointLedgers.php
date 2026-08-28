<?php

namespace App\Filament\Resources\PointLedgers\Pages;

use App\Filament\Resources\PointLedgers\PointLedgerResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListPointLedgers extends ListRecords
{
    protected static string $resource = PointLedgerResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
