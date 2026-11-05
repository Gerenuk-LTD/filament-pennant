<?php

namespace Workbench\App\Filament\Resources\UserResource\Pages;

use Filament\Resources\Pages\EditRecord;
use Gerenuk\FilamentPennant\Actions\GrantFeatureAction;
use Gerenuk\FilamentPennant\Actions\RevokeFeatureAction;
use Workbench\App\Filament\Resources\UserResource;

class EditUser extends EditRecord
{
    protected static string $resource = UserResource::class;

    protected function getHeaderActions(): array
    {
        return [
            GrantFeatureAction::make(),
            RevokeFeatureAction::make()->feature('view-beta-reports'),
        ];
    }
}
