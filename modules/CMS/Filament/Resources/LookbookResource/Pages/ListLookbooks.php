<?php

namespace Modules\CMS\Filament\Resources\LookbookResource\Pages;

use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Modules\CMS\Filament\Resources\LookbookResource;

class ListLookbooks extends ListRecords
{
    protected static string $resource = LookbookResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
