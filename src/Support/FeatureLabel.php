<?php

namespace Gerenuk\FilamentPennant\Support;

use Illuminate\Support\Str;

class FeatureLabel
{
    public static function for(string $feature): string
    {
        return Str::headline(class_exists($feature) ? class_basename($feature) : $feature);
    }
}
