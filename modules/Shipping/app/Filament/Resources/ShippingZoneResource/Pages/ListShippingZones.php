<?php

namespace Modules\Shipping\Filament\Resources\ShippingZoneResource\Pages;

use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Modules\Shipping\Filament\Resources\ShippingZoneResource;

class ListShippingZones extends ListRecords
{
    protected static string $resource = ShippingZoneResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
