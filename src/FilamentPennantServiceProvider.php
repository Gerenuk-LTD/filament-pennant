<?php

namespace Gerenuk\FilamentPennant;

use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

class FilamentPennantServiceProvider extends PackageServiceProvider
{
    public static string $name = 'filament-pennant';

    public function configurePackage(Package $package): void
    {
        $package->name(static::$name)
            ->hasTranslations();
    }
}
