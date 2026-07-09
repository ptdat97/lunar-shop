<?php

namespace Modules\Customer\Services;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Modules\Customer\Models\Province;
use Modules\Customer\Models\Ward;

/**
 * Vietnam province/ward reference data for the address dropdowns — the single
 * source for both web SSR (checkout) and /api/v1/locations. The tables are
 * only written by VnLocationSeeder (static between deploys), so the lookups
 * are cached here (standards §4 — only services cache).
 */
class LocationService
{
    /** Seeder-managed reference data — a day of cache is safe. */
    protected const CACHE_TTL = 86400;

    /**
     * All provinces ordered by name (id + code + name only).
     *
     * @return Collection<int, Province>
     */
    public function provinces(): Collection
    {
        return Cache::remember(
            'locations.provinces',
            self::CACHE_TTL,
            fn () => Province::query()->orderBy('name')->get(['id', 'code', 'name']),
        );
    }

    /**
     * Wards of one province (id + code + name only).
     *
     * @return Collection<int, Ward>
     */
    public function wards(Province $province): Collection
    {
        return Cache::remember(
            "locations.wards.{$province->id}",
            self::CACHE_TTL,
            fn () => $province->wards()->get(['id', 'code', 'name']),
        );
    }
}
