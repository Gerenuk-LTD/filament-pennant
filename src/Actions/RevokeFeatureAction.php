<?php

namespace Gerenuk\FilamentPennant\Actions;

use Filament\Support\Icons\Heroicon;
use Laravel\Pennant\PendingScopedFeatureInteraction;

class RevokeFeatureAction extends FeatureAction
{
    public static function getDefaultName(): ?string
    {
        return 'revokeFeature';
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->icon(Heroicon::OutlinedNoSymbol);

        $this->color('gray');
    }

    protected function getTranslationKey(): string
    {
        return 'revoke';
    }

    protected function canChange(mixed $value): bool
    {
        return $value !== false;
    }

    protected function change(PendingScopedFeatureInteraction $features, string $feature): void
    {
        $features->deactivate($feature);
    }
}
