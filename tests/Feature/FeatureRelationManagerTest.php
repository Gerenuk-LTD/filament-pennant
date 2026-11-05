<?php

use Filament\Actions\Testing\TestAction;
use Gerenuk\FilamentPennant\Actions\FeatureAction;
use Gerenuk\FilamentPennant\RelationManagers\FeatureRelationManager;
use Gerenuk\FilamentPennant\Tests\Fixtures\DenyUpdatePolicy;
use Gerenuk\FilamentPennant\Tests\Fixtures\PlainRecord;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Laravel\Pennant\Feature;
use Livewire\Livewire;
use Workbench\App\Filament\Resources\UserResource\Pages\EditUser;
use Workbench\App\Filament\Resources\UserResource\Pages\ViewUser;
use Workbench\App\Models\User;

beforeEach(function () {
    Feature::define('view-beta-reports', false);
    Feature::define('view-new-dashboard', false);

    $this->user = User::create(['name' => 'Alice']);

    $this->actingAs($this->user);
});

function featureManager(User $user, string $pageClass = EditUser::class)
{
    return Livewire::test(FeatureRelationManager::class, [
        'ownerRecord' => $user,
        'pageClass' => $pageClass,
    ]);
}

it('shows on the edit page of the owner record', function () {
    Livewire::test(EditUser::class, ['record' => $this->user->getKey()])
        ->assertSeeLivewire(FeatureRelationManager::class);
});

it('lists every defined feature', function () {
    featureManager($this->user)
        ->assertSee('View Beta Reports')
        ->assertSee('View New Dashboard');
});

it('toggles a feature for the owner record only', function () {
    $other = User::create(['name' => 'Bob']);

    $component = featureManager($this->user)
        ->callTableAction('toggleFeature', 'view-beta-reports');

    expect(Feature::for($this->user)->active('view-beta-reports'))->toBeTrue()
        ->and(Feature::for($other)->active('view-beta-reports'))->toBeFalse();

    $component->callTableAction('toggleFeature', 'view-beta-reports');

    expect(Feature::for($this->user)->active('view-beta-reports'))->toBeFalse();
});

it('cannot skip the confirmation by saving the toggle directly', function () {
    featureManager($this->user)
        ->call('updateTableColumnState', 'active', 'view-beta-reports', true);

    expect(Feature::for($this->user)->active('view-beta-reports'))->toBeFalse();
});

it('uses flags already stored in the features table', function () {
    DB::table('features')->insert([
        'name' => 'view-beta-reports',
        'scope' => User::class . '|' . $this->user->getKey(),
        'value' => json_encode(true),
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    featureManager($this->user)
        ->assertTableColumnStateSet('active', true, 'view-beta-reports')
        ->assertTableColumnStateSet('active', false, 'view-new-dashboard');
});

it('is read-only on view pages', function () {
    featureManager($this->user, ViewUser::class)
        ->assertTableActionHidden('toggleFeature', 'view-beta-reports');
});

it('blocks access when the policy denies updating the owner record', function () {
    Gate::policy(User::class, DenyUpdatePolicy::class);

    featureManager($this->user)->assertForbidden();
});

it('works for a model without the HasFeatures trait', function () {
    $record = PlainRecord::create(['name' => 'Plain']);

    Livewire::test(FeatureRelationManager::class, [
        'ownerRecord' => $record,
        'pageClass' => EditUser::class,
    ])
        ->assertSee('View Beta Reports')
        ->callTableAction('toggleFeature', 'view-beta-reports');

    expect(Feature::for($record)->active('view-beta-reports'))->toBeTrue();
});

it('allows access when the model has no policy', function () {
    expect(FeatureRelationManager::canViewForRecord($this->user, EditUser::class))->toBeTrue();
});

it('refreshes the table when an action changes a feature', function () {
    $component = featureManager($this->user)
        ->assertTableColumnStateSet('active', false, 'view-beta-reports');

    Feature::for($this->user)->activate('view-beta-reports');
    Feature::flushCache();

    $component->dispatch(FeatureAction::UPDATED_EVENT)
        ->assertTableColumnStateSet('active', true, 'view-beta-reports');
});

it('replaces a rich value with true or false when toggled', function () {
    Feature::define('checkout-variant', 'variant-a');

    $component = featureManager($this->user)
        ->assertTableColumnStateSet('active', true, 'checkout-variant')
        ->callTableAction('toggleFeature', 'checkout-variant');

    Feature::flushCache();

    expect(Feature::for($this->user)->value('checkout-variant'))->toBeFalse();

    $component->callTableAction('toggleFeature', 'checkout-variant');

    Feature::flushCache();

    expect(Feature::for($this->user)->value('checkout-variant'))->toBeTrue();
});

it('leaves out features defined for another model', function () {
    Feature::define('plain-records-only', fn (PlainRecord $record) => true);

    featureManager($this->user)
        ->assertSee('View Beta Reports')
        ->assertDontSee('Plain Records Only');
});

it('keeps a feature on when confirming turn on after it was already turned on', function () {
    $component = featureManager($this->user);

    Feature::for($this->user)->activate('view-beta-reports');
    Feature::flushCache();

    $component->callTableAction('toggleFeature', 'view-beta-reports', arguments: ['activate' => true]);

    Feature::flushCache();

    expect(Feature::for($this->user)->active('view-beta-reports'))->toBeTrue();
});

it('keeps a feature off when confirming turn off after it was already turned off', function () {
    $component = featureManager($this->user);

    $component->callTableAction('toggleFeature', 'view-beta-reports', arguments: ['activate' => false]);

    Feature::flushCache();

    expect(Feature::for($this->user)->active('view-beta-reports'))->toBeFalse();
});

it('describes the intended state in the confirmation', function () {
    Feature::for($this->user)->activate('view-beta-reports');

    featureManager($this->user)
        ->mountAction(TestAction::make('toggleFeature')->table('view-beta-reports')->arguments(['activate' => true]))
        ->assertMountedActionModalSee('Turn this feature on');
});

it('sends the intended state from the toggle', function () {
    $component = featureManager($this->user);

    $attributes = (fn () => $this->getToggleAttributes([
        'name' => 'view-beta-reports',
        'label' => 'View Beta Reports',
        'active' => true,
    ]))->call($component->instance());

    expect($attributes['x-on:click.capture.prevent.stop'])
        ->toContain('activate')
        ->toContain(':false');
});

it('notifies when a feature is toggled', function () {
    featureManager($this->user)
        ->callTableAction('toggleFeature', 'view-beta-reports')
        ->assertNotified('Feature granted')
        ->callTableAction('toggleFeature', 'view-beta-reports')
        ->assertNotified('Feature revoked');
});
