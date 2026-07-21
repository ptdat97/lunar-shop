<?php

namespace Modules\Content\Filament\Resources\MenuResource\Pages;

use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;
use Filament\Tables\Actions\EditAction;
use Filament\Tables\Table;
use Modules\Content\Filament\Resources\MenuResource;
use Modules\Content\Models\Menu;
use Modules\Content\Services\MenuTree;

/**
 * Single-screen menu management: table of menus + slide-over edit with a
 * nested Repeater driving the whole item tree.
 */
class ManageMenus extends ManageRecords
{
    protected static string $resource = MenuResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->slideOver()
                ->using(function (array $data): Menu {
                    $tree = $data['tree'] ?? [];
                    unset($data['tree']);
                    $menu = Menu::create($data);
                    MenuTree::save($menu, $tree);

                    return $menu;
                }),
        ];
    }

    public function table(Table $table): Table
    {
        return parent::table($table)->actions([
            EditAction::make()
                ->slideOver()
                // Load the existing tree into the form.
                ->mutateRecordDataUsing(function (array $data, Menu $record): array {
                    $data['tree'] = MenuTree::toArray($record);

                    return $data;
                })
                // Persist name/handle + rebuild the tree.
                ->using(function (Menu $record, array $data): Menu {
                    $tree = $data['tree'] ?? [];
                    unset($data['tree']);
                    $record->update($data);
                    MenuTree::save($record, $tree);

                    return $record;
                }),
        ]);
    }
}
