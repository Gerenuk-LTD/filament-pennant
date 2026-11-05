<?php

namespace Gerenuk\FilamentPennant\RelationManagers;

use Filament\Actions\Action;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Table;
use Gerenuk\FilamentPennant\Actions\FeatureAction;
use Gerenuk\FilamentPennant\FeatureAccess;
use Gerenuk\FilamentPennant\Support\FeatureLabel;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Js;
use Illuminate\Support\Str;
use Laravel\Pennant\Feature;
use Livewire\Attributes\On;

use function Filament\get_authorization_response;
use function Filament\Support\get_model_label;

class FeatureRelationManager extends RelationManager
{
    protected static string $relationship = 'features';

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('filament-pennant::features.title');
    }

    /**
     * Runs on every request, so it also guards the toggle action.
     */
    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        return get_authorization_response('update', $ownerRecord, static::shouldCheckPolicyExistence())->allowed();
    }

    /**
     * The parent table requires an Eloquent relationship, and Pennant flags aren't one.
     */
    protected function makeTable(): Table
    {
        return Table::make($this)
            ->queryStringIdentifier(Str::lcfirst(class_basename(static::class)))
            ->heading(static::getTitle($this->getOwnerRecord(), $this->getPageClass()));
    }

    public function table(Table $table): Table
    {
        return $table
            ->records(fn (): array => $this->getFeatureRecords())
            ->paginated(false)
            ->columns([
                TextColumn::make('label')
                    ->label(__('filament-pennant::features.columns.feature')),
                ToggleColumn::make('active')
                    ->label(__('filament-pennant::features.columns.active'))
                    ->disabled(fn (): bool => $this->isReadOnly())
                    ->action($this->makeToggleAction())
                    ->extraAttributes(fn (array $record): array => $this->getToggleAttributes($record)),
            ]);
    }

    #[On(FeatureAction::UPDATED_EVENT)]
    public function refreshFeatures(): void
    {
        $this->flushCachedTableRecords();
    }

    protected function makeToggleAction(): Action
    {
        return Action::make('toggleFeature')
            ->authorize(fn (): bool => ! $this->isReadOnly())
            ->requiresConfirmation()
            ->color(fn (array $record, array $arguments): string => $this->shouldActivate($record, $arguments) ? 'primary' : 'danger')
            ->modalIcon(Heroicon::OutlinedFlag)
            ->modalHeading(fn (array $record): string => $record['label'])
            ->modalDescription(fn (array $record, array $arguments): string => $this->translateToggle($record, $arguments, 'description'))
            ->modalSubmitActionLabel(fn (array $record, array $arguments): string => $this->translateToggle($record, $arguments, 'submit'))
            ->action(function (array $record, array $arguments, Action $action): void {
                $features = Feature::for($this->getOwnerRecord());

                $activate = $this->shouldActivate($record, $arguments);

                $activate ? $features->activate($record['name']) : $features->deactivate($record['name']);

                $this->flushCachedTableRecords();

                $this->dispatch('refresh-page');

                $action->successNotificationTitle(__('filament-pennant::features.' . ($activate ? 'grant' : 'revoke') . '.success'));

                $action->success();
            });
    }

    /**
     * Without arguments, flips the current state.
     *
     * @param  array<string, mixed>  $record
     * @param  array<string, mixed>  $arguments
     */
    protected function shouldActivate(array $record, array $arguments): bool
    {
        return (bool) ($arguments['activate'] ?? ! $record['active']);
    }

    /**
     * Opens the confirmation instead of letting the switch save on click.
     *
     * @param  array<string, mixed>  $record
     * @return array<string, string>
     */
    protected function getToggleAttributes(array $record): array
    {
        if ($this->isReadOnly()) {
            return [];
        }

        $arguments = Js::from(['activate' => ! $record['active']]);
        $context = Js::from(['table' => true, 'recordKey' => $record['name']]);

        $mountAction = "\$wire.mountAction('toggleFeature', {$arguments}, {$context})";

        return [
            'x-on:click.capture.prevent.stop' => $mountAction,
            'x-on:keydown.enter.capture.prevent.stop' => $mountAction,
            'x-on:keydown.space.capture.prevent.stop' => $mountAction,
        ];
    }

    /**
     * @param  array<string, mixed>  $record
     * @param  array<string, mixed>  $arguments
     */
    protected function translateToggle(array $record, array $arguments, string $key): string
    {
        $state = $this->shouldActivate($record, $arguments) ? 'on' : 'off';

        return __("filament-pennant::features.toggle.{$state}.{$key}", [
            'record' => get_model_label($this->getOwnerRecord()::class),
        ]);
    }

    /**
     * @return array<string, array{name: string, label: string, active: bool}>
     */
    protected function getFeatureRecords(): array
    {
        return collect(FeatureAccess::values($this->getOwnerRecord()))
            ->sortKeys()
            ->map(fn (mixed $value, string $name): array => [
                'name' => $name,
                'label' => FeatureLabel::for($name),
                'active' => $value !== false,
            ])
            ->all();
    }
}
