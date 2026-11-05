<?php

namespace Gerenuk\FilamentPennant\Pages;

use BackedEnum;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Pages\Page;
use Filament\Schemas\Components\EmbeddedTable;
use Filament\Schemas\Schema;
use Filament\Support\Enums\FontWeight;
use Filament\Support\Enums\Size;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Gerenuk\FilamentPennant\Events\FeatureChangedInBulk;
use Gerenuk\FilamentPennant\FilamentPennantPlugin;
use Gerenuk\FilamentPennant\Support\FeatureCounts;
use Gerenuk\FilamentPennant\Support\FeatureLabel;
use Gerenuk\FilamentPennant\Support\SavedValuesBar;
use Illuminate\Support\HtmlString;
use Laravel\Pennant\Feature;

use function Filament\Support\get_model_label;

class FeatureOverview extends Page implements HasTable
{
    use InteractsWithTable;

    protected static ?string $slug = 'features';

    protected static string | BackedEnum | null $navigationIcon = Heroicon::OutlinedFlag;

    public static function canAccess(): bool
    {
        return FilamentPennantPlugin::get()->canAccess();
    }

    public static function getNavigationLabel(): string
    {
        return __('filament-pennant::features.overview.title');
    }

    public function getTitle(): string
    {
        return __('filament-pennant::features.overview.title');
    }

    public function content(Schema $schema): Schema
    {
        return $schema->components([
            EmbeddedTable::make(),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->records(fn (): array => $this->getFeatureRecords())
            ->paginated(false)
            ->columns($this->getTableColumns())
            ->recordActions($this->getBulkActions())
            ->recordActionsAlignment('end')
            ->emptyStateIcon(Heroicon::OutlinedFlag)
            ->emptyStateHeading($this->translate('empty_heading'))
            ->emptyStateDescription($this->translate('empty_description'));
    }

    /**
     * @return array<TextColumn>
     */
    protected function getTableColumns(): array
    {
        return [
            TextColumn::make('label')
                ->label(__('filament-pennant::features.columns.feature'))
                ->weight(FontWeight::Medium)
                ->description(fn (array $record): string => $record['defined'] ? $record['name'] : $this->translate('undefined')),
            TextColumn::make('applies_to')
                ->label($this->translate('applies_to'))
                ->badge()
                ->color('gray')
                ->separator(', ')
                ->visible(filled(FilamentPennantPlugin::get()->getScopes())),
            TextColumn::make('saved')
                ->label($this->translate('saved'))
                ->state(fn (array $record): HtmlString => SavedValuesBar::render($record['saved_on'], $record['saved_off']))
                ->visible(FeatureCounts::available()),
        ];
    }

    /**
     * @return array<Action>
     */
    protected function getBulkActions(): array
    {
        if (! FilamentPennantPlugin::get()->hasBulkActions()) {
            return [];
        }

        return [
            $this->makeBulkAction('turnOnSavedValues', 'on'),
            $this->makeBulkAction('turnOffSavedValues', 'off'),
            $this->makeBulkAction('clearSavedValues', 'clear'),
        ];
    }

    /**
     * @param  'on'|'off'|'clear'  $type
     */
    protected function makeBulkAction(string $name, string $type): Action
    {
        return Action::make($name)
            ->label(__("filament-pennant::features.bulk.{$type}.button"))
            ->tooltip(__("filament-pennant::features.bulk.{$type}.label"))
            ->icon(match ($type) {
                'on' => Heroicon::OutlinedCheckCircle,
                'off' => Heroicon::OutlinedXCircle,
                'clear' => Heroicon::OutlinedTrash,
            })
            ->color(match ($type) {
                'on' => 'primary',
                'off' => 'gray',
                'clear' => 'danger',
            })
            ->button()
            ->outlined()
            ->size(Size::Small)
            ->requiresConfirmation()
            ->modalIcon(Heroicon::OutlinedFlag)
            ->modalHeading(fn (array $record): string => trans_choice("filament-pennant::features.bulk.{$type}.heading", $record['saved_on'] + $record['saved_off'], [
                'feature' => $record['label'],
            ]))
            ->modalDescription(__("filament-pennant::features.bulk.{$type}.description"))
            ->modalSubmitActionLabel(__("filament-pennant::features.bulk.{$type}.button"))
            ->visible(fn (array $record): bool => $this->canRunBulkAction($record, $type))
            ->authorize(fn (array $record): bool => FilamentPennantPlugin::get()->allowsBulkActions($record['name']))
            ->action(function (array $record, Action $action) use ($type): void {
                $user = Filament::auth()->user();

                if ($user === null) {
                    return;
                }

                match ($type) {
                    'on' => Feature::activateForEveryone($record['name']),
                    'off' => Feature::deactivateForEveryone($record['name']),
                    'clear' => Feature::purge($record['name']),
                };

                event(new FeatureChangedInBulk($record['name'], $type, $user));

                $this->flushCachedTableRecords();

                $action->successNotificationTitle(__("filament-pennant::features.bulk.{$type}.success"));

                $action->success();
            });
    }

    /**
     * Only clearing makes sense for a feature that's no longer defined.
     *
     * @param  array<string, mixed>  $record
     * @param  'on'|'off'|'clear'  $type
     */
    protected function canRunBulkAction(array $record, string $type): bool
    {
        return FeatureCounts::available()
            && ($record['saved_on'] + $record['saved_off']) > 0
            && ($type === 'clear' || $record['defined']);
    }

    public function getSubheading(): ?string
    {
        return FeatureCounts::available() ? $this->translate('description') : null;
    }

    protected function translate(string $key): string
    {
        return __("filament-pennant::features.overview.{$key}");
    }

    /**
     * Includes saved features that are no longer defined.
     *
     * @return array<string, array{name: string, label: string, defined: bool, applies_to: string, saved_on: int, saved_off: int}>
     */
    protected function getFeatureRecords(): array
    {
        $plugin = FilamentPennantPlugin::get();
        $counts = FeatureCounts::all();
        $defined = Feature::defined();

        $scopes = collect($plugin->getScopes())
            ->mapWithKeys(fn (string $model): array => [
                ucfirst(get_model_label($model)) => Feature::store()->definedFeaturesForScope(new $model)->all(),
            ]);

        return collect([...$defined, ...array_keys($counts)])
            ->unique()
            ->filter(fn (string $name): bool => $plugin->allowsFeature($name))
            ->sort()
            ->sortByDesc(fn (string $name): bool => in_array($name, $defined, true))
            ->mapWithKeys(fn (string $name): array => [
                $name => [
                    'name' => $name,
                    'label' => FeatureLabel::for($name),
                    'defined' => in_array($name, $defined, true),
                    'applies_to' => $scopes->filter(fn (array $features): bool => in_array($name, $features, true))->keys()->implode(', '),
                    'saved_on' => $counts[$name]['on'] ?? 0,
                    'saved_off' => $counts[$name]['off'] ?? 0,
                ],
            ])
            ->all();
    }
}
