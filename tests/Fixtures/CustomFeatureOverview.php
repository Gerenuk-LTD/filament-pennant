<?php

namespace Gerenuk\FilamentPennant\Tests\Fixtures;

use Filament\Tables\Columns\TextColumn;
use Gerenuk\FilamentPennant\Pages\FeatureOverview;

class CustomFeatureOverview extends FeatureOverview
{
    protected static ?string $slug = 'custom-features';

    protected function getTableColumns(): array
    {
        return [
            TextColumn::make('label')->label('Custom heading'),
        ];
    }

    protected function getBulkActions(): array
    {
        return [];
    }
}
