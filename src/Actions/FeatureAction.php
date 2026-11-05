<?php

namespace Gerenuk\FilamentPennant\Actions;

use Closure;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Support\Icons\Heroicon;
use Gerenuk\FilamentPennant\FeatureAccess;
use Gerenuk\FilamentPennant\Support\FeatureLabel;
use Illuminate\Database\Eloquent\Model;
use Laravel\Pennant\Feature;
use Laravel\Pennant\PendingScopedFeatureInteraction;
use Livewire\Component;

use function Filament\get_authorization_response;
use function Filament\Support\get_model_label;

abstract class FeatureAction extends Action
{
    public const UPDATED_EVENT = 'filament-pennant-features-updated';

    protected string | Closure | null $feature = null;

    abstract protected function getTranslationKey(): string;

    abstract protected function canChange(mixed $value): bool;

    abstract protected function change(PendingScopedFeatureInteraction $features, string $feature): void;

    protected function setUp(): void
    {
        parent::setUp();

        $this->label($this->translate('label'));

        $this->visible(fn (Model $record): bool => $this->hasChangeableFeature($record));

        $this->authorize(fn (Model $record): bool => get_authorization_response('update', $record)->allowed());

        $this->requiresConfirmation();

        $this->modalIcon(Heroicon::OutlinedFlag);

        $this->modalHeading($this->translate('label'));

        $this->modalDescription(fn (Model $record): string => $this->getDescription($record));

        $this->modalSubmitActionLabel($this->translate('submit'));

        $this->successNotificationTitle($this->translate('success'));

        $this->schema(fn (Model $record): array => filled($this->getFeature()) ? [] : [
            Select::make('feature')
                ->hiddenLabel()
                ->placeholder(__('filament-pennant::features.placeholder'))
                ->options($this->getChangeableFeatures($record))
                ->required(),
        ]);

        $this->action(function (array $data, Component $livewire, Model $record): void {
            $this->change(Feature::for($record), $this->getFeature() ?? $data['feature']);

            $livewire->dispatch(static::UPDATED_EVENT);

            $this->success();
        });
    }

    /**
     * Without a fixed feature, the user picks one in the modal.
     */
    public function feature(string | Closure | null $feature): static
    {
        $this->feature = $feature;

        return $this;
    }

    public function getFeature(): ?string
    {
        $feature = $this->evaluate($this->feature);

        return filled($feature) ? Feature::name($feature) : null;
    }

    /**
     * @param  array<string, string>  $replace
     */
    protected function translate(string $key, array $replace = []): string
    {
        return __("filament-pennant::features.{$this->getTranslationKey()}.{$key}", $replace);
    }

    protected function getDescription(Model $record): string
    {
        $feature = $this->getFeature();

        return $this->translate(filled($feature) ? 'confirm' : 'choose', [
            'feature' => FeatureLabel::for($feature ?? ''),
            'record' => get_model_label($record::class),
        ]);
    }

    protected function hasChangeableFeature(Model $record): bool
    {
        $features = $this->getChangeableFeatures($record);

        return filled($this->getFeature())
            ? array_key_exists($this->getFeature(), $features)
            : filled($features);
    }

    /**
     * @return array<string, string>
     */
    protected function getChangeableFeatures(Model $record): array
    {
        return collect(FeatureAccess::values($record))
            ->filter(fn (mixed $value): bool => $this->canChange($value))
            ->sortKeys()
            ->map(fn (mixed $value, string $name): string => FeatureLabel::for($name))
            ->all();
    }
}
