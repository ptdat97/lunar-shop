<?php

namespace Modules\CMS\Filament\Resources\PageResource\Pages;

use Filament\Resources\Pages\ListRecords;
use Modules\CMS\Filament\Resources\PageResource;

class ListPages extends ListRecords
{
    protected static string $resource = PageResource::class;
}