<img src="https://banners.beyondco.de/Filament%20Pennant.png?theme=light&packageManager=composer+require&packageName=gerenuk%2Ffilament-pennant&pattern=brickWall&style=style_1&description=Manage+Pennant+feature+flags+in+your+Filament+panel&md=1&showWatermark=0&fontSize=100px&images=flag" alt="Project banner">

# Filament Pennant

[![Latest Version on Packagist](https://img.shields.io/packagist/v/gerenuk/filament-pennant.svg?style=flat-square)](https://packagist.org/packages/gerenuk/filament-pennant)
[![GitHub Tests Action Status](https://img.shields.io/github/actions/workflow/status/gerenuk-ltd/filament-pennant/tests.yml?branch=main&label=tests&style=flat-square)](https://github.com/gerenuk-ltd/filament-pennant/actions?query=workflow%3Atests+branch%3Amain)
[![Total Downloads](https://img.shields.io/packagist/dt/gerenuk/filament-pennant.svg?style=flat-square)](https://packagist.org/packages/gerenuk/filament-pennant)
[![Licence](https://img.shields.io/github/license/Gerenuk-LTD/filament-pennant?color=blue&label=license&style=flat-square)](https://github.com/Gerenuk-LTD/filament-pennant/blob/main/LICENSE.md)

This package lets you manage [Laravel Pennant](https://laravel.com/docs/pennant) feature flags in your Filament panel, per record or across a whole feature.

## Table of Contents
1. [Introduction](#filament-pennant)
2. [Version Compatibility](#version-compatibility)
3. [Installation](#installation)
4. [Usage](#usage)
    - [Relation Manager](#relation-manager)
    - [Actions](#actions)
    - [Limiting Which Features Are Listed](#limiting-which-features-are-listed)
    - [Feature Rollouts Page](#feature-rollouts-page)
    - [Bulk Actions](#bulk-actions)
    - [Replacing the Page](#replacing-the-page)
5. [Testing](#testing)
6. [Changelog](#changelog)
7. [Contributing](#contributing)
8. [Security Vulnerabilities](#security-vulnerabilities)
9. [Credits](#credits)
10. [License](#license)

## Version Compatibility

| Plugin | Filament | Laravel | Pennant | PHP      |
|--------|----------|---------|---------|----------|
| 1.x    | 5.10+    | 13.x    | 1.x     | 8.4\|8.5 |

## Installation

You can install the package via composer:

```bash
composer require gerenuk/filament-pennant
```

Set up [Laravel Pennant](https://laravel.com/docs/pennant) as normal, using its `database` store, and define your features.

Optionally, you can publish the translations using

```bash
php artisan vendor:publish --tag="filament-pennant-translations"
```

## Usage

### Relation Manager

Add the relation manager to any resource whose model is a Pennant scope, such as users:

```php
use Gerenuk\FilamentPennant\RelationManagers\FeatureRelationManager;

public static function getRelations(): array
{
    return [
        FeatureRelationManager::class,
    ];
}
```

It lists the features defined for that record, with a toggle to turn each one on or off after a confirmation. You can [limit which features are listed](#limiting-which-features-are-listed). Your model doesn't need a trait.

Viewing and changing flags both use the `update` ability of the record's policy. If the model has no policy, access is allowed, as with other Filament resources.

The relation manager is for boolean flags. Turning a feature on sets it to `true`, and turning it off sets it to `false`.

Pennant saves a feature's value the first time it's checked for a record, and listing features counts as a check. Opening a page with the relation manager or an action on it therefore saves the current value of every listed feature for that record. If a feature's rule depends on something that can change later, the record keeps the saved value until you change it, or clear it with `Feature::for($record)->forget('feature-name')`.

### Actions

Use `GrantFeatureAction` and `RevokeFeatureAction` to put a button anywhere a Filament action can go that has a record, such as a page header or table row:

```php
use Gerenuk\FilamentPennant\Actions\GrantFeatureAction;
use Gerenuk\FilamentPennant\Actions\RevokeFeatureAction;

protected function getHeaderActions(): array
{
    return [
        // Pick from the features the record doesn't have yet.
        GrantFeatureAction::make(),

        // Fixed to one feature, and only shown when it can be applied.
        RevokeFeatureAction::make()->feature('beta-reports'),
    ];
}
```

They use the same `update` policy check as the relation manager, and refresh it if it's on the same page. To change who can use them, call `->authorize()` on the action.

`->feature()` accepts a feature's name or its class.

### Limiting Which Features Are Listed

By default, every feature defined for a record is listed. To limit them, register a closure in a service provider's `boot()` method:

```php
use Gerenuk\FilamentPennant\FeatureAccess;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;
use Laravel\Pennant\Feature;

FeatureAccess::allowUsing(function (string $feature, Model $record, ?Authenticatable $user): bool {
    if ($feature === Feature::name(KillSwitch::class)) {
        return false;
    }

    return $feature !== 'billing-beta' || $user?->can('manage-billing-flags');
});
```

The closure receives the feature's name, the record and the current user. Returning `false` hides the feature from the table, the Grant and Revoke pickers and fixed actions, and the server refuses changes to it. Pennant doesn't check or save a hidden feature on these pages.

For a class-based feature, compare against `Feature::name(KillSwitch::class)`, as a class can set its own name.

The closure runs several times per page, so keep it cheap, or cache its result if it queries the database.

It only applies to this package's table and actions. Your app's own Pennant checks work as normal.

### Feature Rollouts Page

An optional page shows your features with a bar for how many records have each one saved as on or off. To add it, register the plugin on a panel and say who can open it:

```php
use Gerenuk\FilamentPennant\FilamentPennantPlugin;
use Illuminate\Contracts\Auth\Authenticatable;

$panel->plugin(
    FilamentPennantPlugin::make()
        ->authorize(fn (Authenticatable $user): bool => $user->can('view-feature-rollouts'))
);
```

The page appears in the panel's navigation as "Feature rollouts", at `/features`. Nobody can open it until `->authorize()` is set, and it stays out of the navigation until then.

Counts cover saved values, not every record. Pennant saves a value the first time a feature is checked for a record, so counts rise as records are viewed. Rich values count as on. Counts need the `database` store. On other stores, the page lists features without them.

Features with saved values that are no longer defined are listed too, and marked as such.

Two further options are available:

```php
FilamentPennantPlugin::make()
    ->authorize(...)

    // Shows which of these models each feature applies to.
    ->scopes([User::class, Team::class])

    // Leaves features off the page.
    ->features(fn (string $feature, Authenticatable $user): bool => $feature !== 'internal-flag')
```

`->features()` is separate from `FeatureAccess::allowUsing()`, because that closure needs a record and the page doesn't have one. A feature hidden from record tables still appears here unless `->features()` leaves it out as well.

### Bulk Actions

Bulk actions are off by default. To turn them on, say who can use them, and for which features:

```php
FilamentPennantPlugin::make()
    ->authorize(...)
    ->bulkActions(fn (string $feature, Authenticatable $user): bool => $user->can('run-feature-rollouts'))
```

This adds three buttons to each feature with saved values:

- **Turn on** and **Turn off** change every saved value for that feature.
- **Clear** deletes them, so every record goes back to following the feature's rule. It also works on features that are no longer defined.

They only reach saved values. Records without one, including new records, keep following the feature's rule. To change a feature for every record, change its rule in your code, then clear its saved values.

Be careful with these actions:

- They replace values set by hand, as Pennant can't tell those apart from values it saved itself.
- Turning on or off replaces rich values with `true` or `false`.
- Clearing a lottery feature rolls it again for each record.

Each change dispatches `Gerenuk\FilamentPennant\Events\FeatureChangedInBulk`, with the `feature`, the `action` (`on`, `off` or `clear`) and the `user`. Listen for it to keep an audit trail.

### Replacing the Page

To use your own page, extend `FeatureOverview` and pass it to the plugin. It keeps the `->authorize()`, `->features()` and `->bulkActions()` checks:

```php
use Gerenuk\FilamentPennant\Pages\FeatureOverview;

class Rollouts extends FeatureOverview
{
    protected static ?string $slug = 'rollouts';

    protected function getTableColumns(): array
    {
        // Your own columns.
    }

    protected function getBulkActions(): array
    {
        // Your own buttons, or [] for none.
    }
}
```

```php
FilamentPennantPlugin::make()
    ->page(Rollouts::class)
    ->authorize(...)
```

You can also override `table()` or `content()`, or pass any other Filament page. A page that doesn't extend `FeatureOverview` has to do its own access checks, because the plugin's options only apply through that class.

## Testing

```bash
composer test
composer analyse
composer format
```

## Changelog

Please see [CHANGELOG](CHANGELOG.md) for more information on what has changed recently.

## Contributing

Please see [CONTRIBUTING](.github/CONTRIBUTING.md) for details.

## Security Vulnerabilities

Please review [our security policy](../../security/policy) on how to report security vulnerabilities.

## Credits

- [Kieran Proctor](https://github.com/KieranLProctor)
- [All Contributors](../../contributors)

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.
