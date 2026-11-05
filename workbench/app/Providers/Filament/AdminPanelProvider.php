<?php

namespace Workbench\App\Providers\Filament;

use Filament\Panel;
use Filament\PanelProvider;
use Workbench\App\Filament\Resources\UserResource;

class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->id('admin')
            ->path('admin')
            ->default()
            ->resources([
                UserResource::class,
            ]);
    }
}
