<?php

namespace Modules\Catalog\Filament\Pages;

use Filament\Forms;
use Filament\Forms\Components\SpatieMediaLibraryFileUpload;
use Filament\Forms\Form;
use Filament\Support\Facades\FilamentIcon;
use Lunar\Admin\Support\Pages\BaseEditRecord;
use Modules\Catalog\Filament\Resources\ProductResource;
use Modules\Catalog\Services\Admin\ProductEditorService;

/**
 * "Hình ảnh" tab of the product editor: the drag-drop gallery on its own
 * page (kept out of the main Details form so image uploads don't bloat that
 * page's Livewire payload). First image becomes the product's primary
 * thumbnail — normalized after save via ProductEditorService.
 */
class ManageProductImages extends BaseEditRecord
{
    protected static string $resource = ProductResource::class;

    public static bool $formActionsAreSticky = true;

    public function getTitle(): string
    {
        return __('admin.editor.gallery');
    }

    public static function getNavigationLabel(): string
    {
        return __('admin.editor.gallery');
    }

    public static function getNavigationIcon(): ?string
    {
        return FilamentIcon::resolve('lunar::media') ?? 'heroicon-o-photo';
    }

    public function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make(__('admin.editor.gallery'))
                ->description(__('admin.editor.gallery_hint'))
                ->schema([
                    SpatieMediaLibraryFileUpload::make('gallery')
                        ->hiddenLabel()
                        ->collection(config('lunar.media.collection', 'images'))
                        ->image()
                        ->multiple()
                        ->reorderable()
                        ->appendFiles()
                        ->maxFiles(24)
                        ->panelLayout('grid')
                        ->columnSpanFull(),
                ]),
        ]);
    }

    public function afterSave()
    {
        // Runs after Filament persisted the gallery (form component
        // relationships save post-update), so ordering/primary is final.
        app(ProductEditorService::class)->normalizeGallery($this->getRecord());

        parent::afterSave();
    }

    public function getRelationManagers(): array
    {
        return [];
    }
}
