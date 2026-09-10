<?php

namespace Modules\Content\Panel;

use Illuminate\Database\Eloquent\Model;
use Modules\Content\Models\Redirect;
use Modules\Core\Panel\Field;
use Modules\Core\Panel\PanelResource;

/** Legacy-URL redirects served by the storefront's redirect middleware. */
class RedirectResource extends PanelResource
{
    public function model(): string
    {
        return Redirect::class;
    }

    public function section(): string
    {
        return 'shop';
    }

    /** Chuyển hướng chỉ đụng khi đổi URL. */
    public function navigationGroup(): string
    {
        return 'shop-content';
    }

    public function navigationPriority(): int
    {
        return 60;
    }

    public function key(): string
    {
        return 'redirects';
    }

    public function label(): string
    {
        return __('admin.redirect.plural');
    }

    public function singular(): string
    {
        return __('admin.redirect.label');
    }

    public function icon(): string
    {
        return 'externalLink';
    }

    public function fields(): array
    {
        return [
            Field::text('old_url', __('admin.redirect.from'))
                ->required()
                ->placeholder('/old-page')
                ->help(__('admin.redirect.from_help'))
                ->onIndex()
                ->width(6),
            Field::text('new_url', __('admin.redirect.to'))
                ->placeholder('/new-page')
                ->help(__('admin.redirect.to_help'))
                ->onIndex()
                ->width(6),
            Field::select('status_code', __('admin.redirect.status_code'), [
                301 => __('admin.redirect.code_301'),
                302 => __('admin.redirect.code_302'),
                410 => __('admin.redirect.code_410'),
            ])->default(301)->onIndex()->width(6),
            Field::toggle('active', __('admin.common.active'))->default(true)->onIndex()->width(6),
        ];
    }

    /**
     * `old_url` must stay unique: the redirect middleware matches on it and
     * would otherwise pick an arbitrary winner between duplicates.
     */
    public function validationRules(?Model $record = null, array $input = []): array
    {
        $rules = parent::validationRules($record, $input);
        $rules['old_url'][] = $this->unique('old_url', $record);

        return $rules;
    }

    public function searchable(): array
    {
        return ['old_url', 'new_url'];
    }

    public function defaultSort(): array
    {
        return ['old_url', 'asc'];
    }
}
