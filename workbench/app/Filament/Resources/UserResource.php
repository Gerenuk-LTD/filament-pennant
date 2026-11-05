<?php

namespace Workbench\App\Filament\Resources;

use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Gerenuk\FilamentPennant\RelationManagers\FeatureRelationManager;
use Workbench\App\Filament\Resources\UserResource\Pages;
use Workbench\App\Models\User;

class UserResource extends Resource
{
    protected static ?string $model = User::class;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([]);
    }

    public static function getRelations(): array
    {
        return [
            FeatureRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListUsers::route('/'),
            'edit' => Pages\EditUser::route('/{record}/edit'),
        ];
    }
}
