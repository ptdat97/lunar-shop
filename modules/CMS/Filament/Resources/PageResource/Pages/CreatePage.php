<?php

namespace Modules\CMS\Filament\Resources\PageResource\Pages;

use Filament\Resources\Pages\CreateRecord;
use Modules\CMS\Filament\Resources\PageResource;

class CreatePage extends CreateRecord
{
    protected static string $resource = PageResource::class;
}