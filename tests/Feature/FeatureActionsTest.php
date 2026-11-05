<?php

use Gerenuk\FilamentPennant\Actions\FeatureAction;
use Gerenuk\FilamentPennant\Tests\Fixtures\ActionsViewPage;
use Gerenuk\FilamentPennant\Tests\Fixtures\ActionVariantsEditPage;
use Gerenuk\FilamentPennant\Tests\Fixtures\AuthorizeOverrideViewPage;
use Gerenuk\FilamentPennant\Tests\Fixtures\DenyUpdatePolicy;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Laravel\Pennant\Feature;
use Livewire\Livewire;
use Workbench\App\Filament\Resources\UserResource\Pages\EditUser;
use Workbench\App\Models\User;

beforeEach(function () {
    Feature::define('view-beta-reports', false);
    Feature::define('view-new-dashboard', false);

    $this->user = User::create(['name' => 'Alice']);

    $this->actingAs($this->user);
});

it('grants the feature picked in the modal', function () {
    Livewire::test(EditUser::class, ['record' => $this->user->getKey()])
        ->callAction('grantFeature', data: ['feature' => 'view-new-dashboard'])
        ->assertHasNoActionErrors()
        ->assertDispatched(FeatureAction::UPDATED_EVENT);

    expect(Feature::for($this->user)->active('view-new-dashboard'))->toBeTrue();
});

it('revokes a fixed feature, and only shows while it is active', function () {
    $page = fn () => Livewire::test(EditUser::class, ['record' => $this->user->getKey()]);

    $page()->assertActionHidden('revokeFeature');

    Feature::for($this->user)->activate('view-beta-reports');

    $page()->callAction('revokeFeature');

    expect(Feature::for($this->user)->active('view-beta-reports'))->toBeFalse();
});

it('is hidden when the policy denies updating the record', function () {
    $component = Livewire::test(EditUser::class, ['record' => $this->user->getKey()]);

    Gate::policy(User::class, DenyUpdatePolicy::class);

    $component->assertActionHidden('grantFeature');
});

it('refuses a direct call when the policy denies updating the record', function () {
    Gate::policy(User::class, DenyUpdatePolicy::class);

    Livewire::test(ActionsViewPage::class, ['record' => $this->user->getKey()])
        ->assertActionHidden('grantFeature')
        ->call('mountAction', 'grantFeature')
        ->assertActionNotMounted('grantFeature');

    expect(Feature::for($this->user)->active('view-new-dashboard'))->toBeFalse();
});

it('lets authorize() replace the policy check', function () {
    Gate::policy(User::class, DenyUpdatePolicy::class);

    Livewire::test(AuthorizeOverrideViewPage::class, ['record' => $this->user->getKey()])
        ->assertActionVisible('grantFeature')
        ->callAction('grantFeature', data: ['feature' => 'view-new-dashboard']);

    Feature::flushCache();

    expect(Feature::for($this->user)->active('view-new-dashboard'))->toBeTrue();
});

it('rejects a feature that is not in the picker', function () {
    Livewire::test(EditUser::class, ['record' => $this->user->getKey()])
        ->callAction('grantFeature', data: ['feature' => 'not-defined'])
        ->assertHasActionErrors(['feature']);

    expect(DB::table('features')->where('name', 'not-defined')->count())->toBe(0);
});

it('revokes the feature picked in the modal', function () {
    Feature::for($this->user)->activate('view-beta-reports');

    Livewire::test(ActionVariantsEditPage::class, ['record' => $this->user->getKey()])
        ->callAction('revokeFeature', data: ['feature' => 'view-beta-reports'])
        ->assertHasNoActionErrors();

    Feature::flushCache();

    expect(Feature::for($this->user)->active('view-beta-reports'))->toBeFalse();
});

it('grants a fixed feature without asking which one', function () {
    Livewire::test(ActionVariantsEditPage::class, ['record' => $this->user->getKey()])
        ->callAction('grantFeature')
        ->assertHasNoActionErrors();

    Feature::flushCache();

    expect(Feature::for($this->user)->active('view-new-dashboard'))->toBeTrue();
});
