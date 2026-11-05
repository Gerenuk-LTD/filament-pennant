<?php

namespace Gerenuk\FilamentPennant\Actions;

use Filament\Support\Icons\Heroicon;
use Laravel\Pennant\PendingScopedFeatureInteraction;

class GrantFeatureAction extends FeatureAction
{
    public static function getDefaultName(): ?string
    {
        return 'grantFeature';
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->icon(Heroicon::OutlinedFlag);
    }

    protected function getTranslationKey(): string
    {
        return 'grant';
    }

    protected function canChange(mixed $value): bool
    {
        return $value === false;
    }

    protected function change(PendingScopedFeatureInteraction $features, string $feature): void
    {
        $features->activate($feature);
    }
}
