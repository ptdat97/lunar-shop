<?php

namespace Modules\Customer\Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Modules\Customer\Models\Province;
use Modules\Customer\Models\Ward;

/**
 * Seeds Vietnam provinces + wards (2-tier, 2025 reform) from the bundled data
 * file. Idempotent: skips when already populated. ~34 provinces / ~3.3k wards.
 */
class VnLocationSeeder extends Seeder
{
    public function run(): void
    {
        if (Province::query()->exists()) {
            return;
        }

        $data = require __DIR__ . '/../data/vn-locations.php';
        $now = now();

        DB::transaction(function () use ($data, $now) {
            foreach ($data as $province) {
                $p = Province::create([
                    'code' => (string) $province['matinhBNV'],
                    'name' => $province['tentinhmoi'],
                ]);

                $rows = collect($province['phuongxa'] ?? [])->map(fn ($w) => [
                    'province_id' => $p->id,
                    'code' => (string) $w['maphuongxa'],
                    'name' => $w['tenphuongxa'],
                    'created_at' => $now,
                    'updated_at' => $now,
                ])->all();

                foreach (array_chunk($rows, 500) as $chunk) {
                    Ward::insert($chunk);
                }
            }
        });
    }
}
