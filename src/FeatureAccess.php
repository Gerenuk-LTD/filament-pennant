<?php

namespace Gerenuk\FilamentPennant;

use Closure;
use Filament\Facades\Filament;
use Illuminate\Database\Eloquent\Model;
use Laravel\Pennant\Feature;

class FeatureAccess
{
    protected static ?Closure $allowUsing = null;

    /**
     * Pass null to go back to allowing every feature.
     */
    public static function allowUsing(?Closure $callback): void
    {
        static::$allowUsing = $callback;
    }

    public static function allows(string $feature, Model $record): bool
    {
        return static::$allowUsing === null
            || (bool) (static::$allowUsing)($feature, $record, Filament::auth()->user());
    }

    /**
     * Refused features are never read, so their rules don't run and nothing is saved for them.
     *
     * @return array<string, mixed>
     */
    public static function values(Model $record): array
    {
        $features = Feature::store()->definedFeaturesForScope($record)
            ->filter(fn (string $feature): bool => static::allows($feature, $record))
            ->values()
            ->all();

        return Feature::for($record)->values($features);
    }
}
