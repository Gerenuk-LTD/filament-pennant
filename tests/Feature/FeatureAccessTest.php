<?php

use Filament\Facades\Filament;
use Gerenuk\FilamentPennant\Actions\GrantFeatureAction;
use Gerenuk\FilamentPennant\Actions\RevokeFeatureAction;
use Gerenuk\FilamentPennant\FeatureAccess;
use Gerenuk\FilamentPennant\RelationManagers\FeatureRelationManager;
use Gerenuk\FilamentPennant\Tests\Fixtures\Features\GeneratedFeature;
use Gerenuk\FilamentPennant\Tests\Fixtures\Features\NamedFeature;
use Gerenuk\FilamentPennant\Tests\Fixtures\PlainRecord;
use Illuminate\Support\Facades\DB;
use Laravel\Pennant\Feature;
use Livewire\Livewire;
use Workbench\App\Filament\Resources\UserResource\Pages\EditUser;
use Workbench\App\Models\User;

beforeEach(function () {
    Feature::define('view-beta-reports', false);
    Feature::define('view-new-dashboard', false);

    $this->user = User::create(['name' => 'Alice']);

    $this->actingAs($this->user);

    $this->refuseBetaReports = fn (string $feature): bool => $feature !== 'view-beta-reports';
});

afterEach(function () {
    FeatureAccess::allowUsing(null);
});

function accessManager(User $user)
{
    return Livewire::test(FeatureRelationManager::class, [
        'ownerRecord' => $user,
        'pageClass' => EditUser::class,
    ]);
}

function accessPage(User $user)
{
    return Livewire::test(EditUser::class, ['record' => $user->getKey()]);
}

function mountToggle(User $user)
{
    return accessManager($user)->call('mountAction', 'toggleFeature', ['activate' => true], [
        'table' => true,
        'recordKey' => 'view-beta-reports',
    ]);
}

function savedBetaReports(): int
{
    return DB::table('features')->where('name', 'view-beta-reports')->count();
}

it('lists every feature when no closure is registered', function () {
    accessManager($this->user)
        ->assertSee('View Beta Reports')
        ->assertSee('View New Dashboard');
});

it('hides a refused feature from the table', function () {
    FeatureAccess::allowUsing($this->refuseBetaReports);

    accessManager($this->user)
        ->assertDontSee('View Beta Reports')
        ->assertSee('View New Dashboard');
});

it('leaves a refused feature out of the pickers', function () {
    $changeable = fn ($action) => (fn (): array => $this->getChangeableFeatures(test()->user))->call($action);

    expect($changeable(GrantFeatureAction::make()))->toHaveKey('view-beta-reports');

    FeatureAccess::allowUsing($this->refuseBetaReports);

    expect($changeable(GrantFeatureAction::make()))->not->toHaveKey('view-beta-reports')
        ->toHaveKey('view-new-dashboard');

    FeatureAccess::allowUsing(null);
    Feature::for($this->user)->activate(['view-beta-reports', 'view-new-dashboard']);
    FeatureAccess::allowUsing($this->refuseBetaReports);

    expect($changeable(RevokeFeatureAction::make()))->not->toHaveKey('view-beta-reports')
        ->toHaveKey('view-new-dashboard');
});

it('refuses the toggle for a filtered feature', function () {
    FeatureAccess::allowUsing($this->refuseBetaReports);

    $component = mountToggle($this->user);

    expect($component->get('mountedActions'))->toBeEmpty();

    $component->call('callMountedAction');

    expect(savedBetaReports())->toBe(0);
});

it('refuses the toggle when access is removed while its modal is open', function () {
    $component = mountToggle($this->user);

    expect($component->get('mountedActions'))->toHaveCount(1);

    FeatureAccess::allowUsing($this->refuseBetaReports);

    $component->call('callMountedAction');

    expect($component->get('mountedActions'))->toBeEmpty()
        ->and(DB::table('features')->where('name', 'view-beta-reports')->where('value', 'true')->count())->toBe(0);
});

it('rejects a filtered feature submitted through the picker', function () {
    FeatureAccess::allowUsing($this->refuseBetaReports);

    accessPage($this->user)
        ->callAction('grantFeature', data: ['feature' => 'view-beta-reports'])
        ->assertHasActionErrors(['feature']);

    expect(savedBetaReports())->toBe(0);
});

it('refuses the picker when access is removed while its modal is open', function () {
    $component = accessPage($this->user)
        ->mountAction('grantFeature')
        ->fillForm(['feature' => 'view-beta-reports']);

    FeatureAccess::allowUsing($this->refuseBetaReports);

    $component->callMountedAction();

    expect(DB::table('features')->where('name', 'view-beta-reports')->where('value', 'true')->count())->toBe(0);
});

it('hides a fixed action for a filtered feature and refuses a direct call', function () {
    Feature::for($this->user)->activate('view-beta-reports');

    accessPage($this->user)->assertActionVisible('revokeFeature');

    FeatureAccess::allowUsing($this->refuseBetaReports);

    accessPage($this->user)
        ->assertActionHidden('revokeFeature')
        ->call('mountAction', 'revokeFeature')
        ->call('callMountedAction');

    expect(DB::table('features')->where('name', 'view-beta-reports')->value('value'))->toBe('true');
});

it('does not run the rule or save a row for a filtered feature', function () {
    $ran = [];

    Feature::define('allowed-rule', function () use (&$ran): bool {
        $ran[] = 'allowed-rule';

        return true;
    });

    Feature::define('refused-rule', function () use (&$ran): bool {
        $ran[] = 'refused-rule';

        return true;
    });

    FeatureAccess::allowUsing(fn (string $feature): bool => $feature !== 'refused-rule');

    accessManager($this->user)->assertSee('Allowed Rule');

    expect($ran)->toContain('allowed-rule')->not->toContain('refused-rule')
        ->and(DB::table('features')->where('name', 'refused-rule')->count())->toBe(0);
});

it('passes the feature, the record and the user to the closure', function () {
    $received = null;

    FeatureAccess::allowUsing(function (...$arguments) use (&$received): bool {
        $received = $arguments;

        return true;
    });

    expect(FeatureAccess::allows('view-new-dashboard', $this->user))->toBeTrue()
        ->and($received[0])->toBe('view-new-dashboard')
        ->and($received[1])->toBe($this->user)
        ->and($received[2]?->getKey())->toBe($this->user->getKey());
});

it('passes the user from the guard of the panel', function () {
    config()->set('auth.guards.staff', ['driver' => 'session', 'provider' => 'users']);

    Filament::getPanel('admin')->authGuard('staff');

    $staff = User::create(['name' => 'Staff']);

    $this->actingAs($staff, 'staff');

    $received = null;

    FeatureAccess::allowUsing(function (string $feature, $record, $user) use (&$received): bool {
        $received = $user;

        return true;
    });

    FeatureAccess::allows('view-new-dashboard', $this->user);

    expect($received?->getKey())->toBe($staff->getKey());
});

it('passes the resolved name of a class-based feature to the closure', function () {
    Feature::define(GeneratedFeature::class);
    Feature::define(NamedFeature::class);

    $names = [];

    FeatureAccess::allowUsing(function (string $feature) use (&$names): bool {
        $names[] = $feature;

        return true;
    });

    FeatureAccess::values($this->user);

    expect($names)->toContain(Feature::name(GeneratedFeature::class))
        ->toContain(Feature::name(NamedFeature::class));
});

it('copes with every feature being filtered', function () {
    FeatureAccess::allowUsing(fn (): bool => false);

    expect(FeatureAccess::values($this->user))->toBe([]);

    accessManager($this->user)
        ->assertOk()
        ->assertDontSee('View New Dashboard');

    accessPage($this->user)->assertActionHidden('grantFeature');
});

it('still leaves out features for another scope type', function () {
    Feature::define('other-model-feature', fn (PlainRecord $record): bool => true);

    $names = [];

    FeatureAccess::allowUsing(function (string $feature) use (&$names): bool {
        $names[] = $feature;

        return true;
    });

    accessManager($this->user)->assertDontSee('Other Model Feature');

    expect($names)->not->toContain('other-model-feature');
});
