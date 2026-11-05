<?php

namespace Gerenuk\FilamentPennant\Support;

use Illuminate\Support\Facades\DB;
use Laravel\Pennant\Drivers\DatabaseDriver;
use Laravel\Pennant\Feature;

class FeatureCounts
{
    public static function available(): bool
    {
        return Feature::store()->getDriver() instanceof DatabaseDriver;
    }

    /**
     * Reads the table directly, as going through Pennant would run rules and save values.
     *
     * @return array<string, array{on: int, off: int}>
     */
    public static function all(): array
    {
        if (! static::available()) {
            return [];
        }

        $store = config('pennant.default');

        return DB::connection(config("pennant.stores.{$store}.connection"))
            ->table(config("pennant.stores.{$store}.table") ?? 'features')
            ->selectRaw("name, sum(case when value = 'false' then 0 else 1 end) as saved_on, sum(case when value = 'false' then 1 else 0 end) as saved_off")
            ->groupBy('name')
            ->get()
            ->mapWithKeys(fn (object $row): array => [
                $row->name => ['on' => (int) $row->saved_on, 'off' => (int) $row->saved_off],
            ])
            ->all();
    }
}
