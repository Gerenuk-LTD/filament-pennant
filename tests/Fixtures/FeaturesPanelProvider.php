<?php

namespace Gerenuk\FilamentPennant\Tests\Fixtures;

use Filament\Panel;
use Filament\PanelProvider;
use Gerenuk\FilamentPennant\FilamentPennantPlugin;

class FeaturesPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->id('features')
            ->path('features-panel')
            ->plugin(FilamentPennantPlugin::make());
    }
}
