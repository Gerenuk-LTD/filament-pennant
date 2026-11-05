<?php

namespace Gerenuk\FilamentPennant\Tests\Fixtures;

use Gerenuk\FilamentPennant\Actions\GrantFeatureAction;
use Workbench\App\Filament\Resources\UserResource\Pages\ViewUser;

class AuthorizeOverrideViewPage extends ViewUser
{
    protected function getHeaderActions(): array
    {
        return [
            GrantFeatureAction::make()->authorize(true),
        ];
    }
}
