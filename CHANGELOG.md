# Changelog

All notable changes to `filament-pennant` will be documented in this file.

## 1.0.0 - 2026-11-06

First release. Requires PHP 8.4+, Laravel 13, Filament 5.10+ and Laravel Pennant.

### Per-record flags

- `FeatureRelationManager` lists a record's features, with a toggle to turn each one on or off after a confirmation.
- `GrantFeatureAction` and `RevokeFeatureAction` can go anywhere a Filament action with a record can. They let you pick a feature, or use `->feature()` to fix one by its name or class.
- Viewing and changing flags use the `update` ability of the record's policy. Actions can override this with `->authorize()`.
- Class-based Pennant features are supported.

### Limiting which features are listed

- `FeatureAccess::allowUsing()` decides which features can be managed, based on the feature, the record and the user. Refused features are hidden, and the server refuses changes to them.
- With no closure registered, every feature is listed.

### Feature rollouts page

- `FilamentPennantPlugin` adds an optional page showing how many records have each feature saved as on or off. Nobody can open it until `->authorize()` is set.
- `->scopes()` shows which models each feature applies to, and `->features()` leaves features off the page.
- Saved features that are no longer defined are listed and marked.
- `->page()` replaces the page with your own.

### Bulk actions

- `->bulkActions()` adds Turn on, Turn off and Clear buttons to the page. They're off by default.
- These change saved values only. Records without a saved value keep following the feature's rule.
- Each change dispatches `FeatureChangedInBulk`, with the feature, the action and the user.
