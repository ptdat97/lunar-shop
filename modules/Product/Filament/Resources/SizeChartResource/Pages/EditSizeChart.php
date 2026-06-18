<?php

namespace Modules\Product\Filament\Resources\SizeChartResource\Pages;

use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use Modules\Product\Filament\Resources\SizeChartResource;

class EditSizeChart extends EditRecord
{
    protected static string $resource = SizeChartResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
