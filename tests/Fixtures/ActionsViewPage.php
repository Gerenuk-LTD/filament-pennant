<?php

namespace Gerenuk\FilamentPennant\Tests\Fixtures;

use Gerenuk\FilamentPennant\Actions\GrantFeatureAction;
use Workbench\App\Filament\Resources\UserResource\Pages\ViewUser;

class ActionsViewPage extends ViewUser
{
    protected function getHeaderActions(): array
    {
        return [
            GrantFeatureAction::make(),
        ];
    }
}
