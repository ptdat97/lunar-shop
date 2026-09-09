<?php

namespace Modules\Content\Panel;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Lunar\Core\Models\Collection;
use Modules\Content\Models\Menu;
use Modules\Content\Services\MenuTree;
use Modules\Core\Panel\Field;
use Modules\Core\Panel\PanelResource;

/**
 * Storefront navigation: the header menu, the footer columns, the mega menus.
 *
 * A menu is a tree in `menu_items` (self-referencing `parent_id`), but a tree
 * is not a column — so the whole thing rides in one virtual `tree` field that
 * MenuTree converts in both directions. The engine never sees menu_items.
 *
 * Every node is the same shape (menu_items has one set of columns), so the
 * three levels below are the same field list repeated, with `visibleWhen`
 * deciding which parts of it a node of that type shows.
 */
class MenuResource extends PanelResource
{
    public function model(): string
    {
        return Menu::class;
    }

    public function section(): string
    {
        return 'shop';
    }

    public function key(): string
    {
        return 'menus';
    }

    public function label(): string
    {
        return __('admin.menu.plural');
    }

    public function singular(): string
    {
        return __('admin.menu.label');
    }

    public function icon(): string
    {
        return 'menu';
    }

    public function fields(): array
    {
        return [
            Field::text('name', __('admin.common.name'))->required()->onIndex()->width(6),
            Field::text('handle', __('admin.menu.handle'))
                ->required()->help(__('admin.menu.handle_help'))->onIndex()->width(6),

            Field::repeater('tree', __('admin.menu.items'), $this->nodeFields(depth: 0))
                ->itemLabel('label')
                ->addLabel(__('admin.menu.item'))
                ->virtual(),
        ];
    }

    /**
     * One node of the tree. Depth decides which types it may take and whether
     * it may hold children at all:
     *
     *   0  top level  — link / dropdown / mega / footer column
     *   1  a mega menu's columns and banners, or a dropdown's links
     *   2  the links inside a mega column
     *
     * @return array<int, Field>
     */
    protected function nodeFields(int $depth): array
    {
        $fields = [
            Field::select('type', __('admin.menu.type'), $this->typesFor($depth))
                ->required()->default($depth === 0 ? 'link' : 'mega-column')->width(4),
            Field::text('label', __('admin.menu.item_label'))->required()->width(4),
            Field::text('badge', __('admin.menu.badge'))->placeholder('New / Hot')->width(4),
            Field::text('url', __('admin.menu.url'))->placeholder('/search hoặc https://…')->width(6),
            Field::relation('collection_id', __('admin.menu.link_collection'), fn () => Collection::get()
                ->mapWithKeys(fn (Collection $c) => [$c->id => $c->translate('name')])
                ->all())->width(6),
            // A banner node is a picture, not a link list.
            Field::image('image', __('admin.menu.banner_image'))->visibleWhen('type', 'banner'),
        ];

        // Two levels of nesting, no more: that is the deepest shape the theme's
        // mega menu renders, and an unbounded tree here would only produce
        // menus the storefront silently ignores.
        if ($depth < 2) {
            $fields[] = Field::repeater('children', __('admin.menu.links'), $this->nodeFields($depth + 1))
                ->itemLabel('label')
                ->addLabel(__('admin.menu.item'))
                ->visibleWhen('type', ...$this->parentTypesFor($depth));
        }

        return $fields;
    }

    /** @return array<string, string> */
    protected function typesFor(int $depth): array
    {
        return match ($depth) {
            0 => [
                'link' => __('admin.menu.type_link'),
                'dropdown' => __('admin.menu.type_dropdown'),
                'mega' => __('admin.menu.type_mega'),
                'footer-column' => __('admin.menu.type_footer_column'),
            ],
            1 => [
                'link' => __('admin.menu.type_link'),
                'mega-column' => __('admin.menu.type_column'),
                'banner' => __('admin.menu.type_banner'),
            ],
            default => ['link' => __('admin.menu.type_link')],
        };
    }

    /**
     * The node types that may hold children at this depth — everything else
     * hides the nested repeater.
     *
     * @return array<int, string>
     */
    protected function parentTypesFor(int $depth): array
    {
        return $depth === 0
            ? ['dropdown', 'mega', 'footer-column']
            : ['mega-column'];
    }

    /** A menu is looked up by handle, so a duplicate would shadow another. */
    public function validationRules(?Model $record = null, array $input = []): array
    {
        $rules = parent::validationRules($record, $input);
        $rules['handle'][] = $this->unique('handle', $record);

        return $rules;
    }

    public function toRow(Model $record): array
    {
        // `tree` is virtual: the engine skipped it, and MenuTree assembles it
        // from menu_items in exactly the nested shape the repeater renders.
        return [...parent::toRow($record), 'tree' => MenuTree::toArray($record)];
    }

    public function saved(Model $record, array $data): void
    {
        MenuTree::save($record, $data['tree'] ?? []);
    }

    public function computed(): array
    {
        return ['items_count' => fn (Menu $menu) => $menu->items_count];
    }

    public function computedLabels(): array
    {
        return ['items_count' => __('admin.menu.items')];
    }

    public function indexQuery(Builder $query): Builder
    {
        return $query->withCount('items');
    }

    public function searchable(): array
    {
        return ['name', 'handle'];
    }

    public function defaultSort(): array
    {
        return ['handle', 'asc'];
    }
}
