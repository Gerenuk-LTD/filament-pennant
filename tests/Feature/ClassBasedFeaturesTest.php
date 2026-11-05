<?php

use Gerenuk\FilamentPennant\Actions\GrantFeatureAction;
use Gerenuk\FilamentPennant\RelationManagers\FeatureRelationManager;
use Gerenuk\FilamentPennant\Support\FeatureLabel;
use Gerenuk\FilamentPennant\Tests\Fixtures\Features\GeneratedFeature;
use Gerenuk\FilamentPennant\Tests\Fixtures\Features\NamedFeature;
use Laravel\Pennant\Feature;
use Livewire\Livewire;
use Workbench\App\Filament\Resources\UserResource\Pages\EditUser;
use Workbench\App\Models\User;

beforeEach(function () {
    Feature::define(GeneratedFeature::class);
    Feature::define(NamedFeature::class);

    $this->user = User::create(['name' => 'Alice']);

    $this->actingAs($this->user);
});

it('labels a class-based feature by its short class name', function () {
    expect(FeatureLabel::for(GeneratedFeature::class))->toBe('Generated Feature');

    // The full class name stays in the toggle's attributes, so only look for the old spaced label.
    Livewire::test(FeatureRelationManager::class, [
        'ownerRecord' => $this->user,
        'pageClass' => EditUser::class,
    ])
        ->assertSee('Generated Feature')
        ->assertDontSee('Fixtures\ Features');
});

it('leaves string feature labels unchanged', function () {
    expect(FeatureLabel::for('view-beta-reports'))->toBe('View Beta Reports');
});

it('finds a fixed feature by its class when the class sets a name', function () {
    $action = GrantFeatureAction::make()->feature(NamedFeature::class);

    expect($action->getFeature())->toBe('named-feature');
});

it('keeps a fixed string feature as it is', function () {
    expect(GrantFeatureAction::make()->feature('view-beta-reports')->getFeature())
        ->toBe('view-beta-reports');
});

it('treats a missing fixed feature as no feature', function () {
    expect(GrantFeatureAction::make()->getFeature())->toBeNull();
});
