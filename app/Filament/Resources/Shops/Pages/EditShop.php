<?php

namespace App\Filament\Resources\Shops\Pages;

use App\Filament\Resources\Shops\ShopResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditShop extends EditRecord
{
    protected static string $resource = ShopResource::class;
    protected function getHeaderActions(): array { return [DeleteAction::make()]; }
}
