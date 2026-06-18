<?php

namespace Modules\Product\Filament\Resources\SizeChartResource\Pages;

use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Modules\Product\Filament\Resources\SizeChartResource;

class ListSizeCharts extends ListRecords
{
    protected static string $resource = SizeChartResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
