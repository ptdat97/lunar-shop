<?php

namespace Modules\Location\Services;

use Illuminate\Support\Facades\Cache;
use Lunar\Models\Country;

/**
 * Reference lookups for Lunar's country list. Single source for the address-form
 * country dropdown so controllers don't query the model directly (the list is
 * static, so it's cached).
 */
class CountryService
{
    protected const CACHE_KEY = 'location.countries.select';

    /**
     * Countries as lightweight { id, name } rows for a <select>, alphabetical.
     *
     * @return array<int, array{id: int, name: string}>
     */
    public function forSelect(): array
    {
        return Cache::rememberForever(self::CACHE_KEY, fn () => Country::orderBy('name')
            ->get(['id', 'name'])
            ->toArray());
    }
}
