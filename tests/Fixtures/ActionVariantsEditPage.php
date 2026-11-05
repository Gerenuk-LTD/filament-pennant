<?php

namespace Gerenuk\FilamentPennant\Tests\Fixtures;

use Gerenuk\FilamentPennant\Actions\GrantFeatureAction;
use Gerenuk\FilamentPennant\Actions\RevokeFeatureAction;
use Workbench\App\Filament\Resources\UserResource\Pages\EditUser;

class ActionVariantsEditPage extends EditUser
{
    protected function getHeaderActions(): array
    {
        return [
            GrantFeatureAction::make()->feature('view-new-dashboard'),
            RevokeFeatureAction::make(),
        ];
    }
}
