<?php

use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Gerenuk\FilamentPennant\Events\FeatureChangedInBulk;
use Gerenuk\FilamentPennant\FilamentPennantPlugin;
use Gerenuk\FilamentPennant\Pages\FeatureOverview;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Laravel\Pennant\Feature;
use Livewire\Livewire;
use Workbench\App\Models\User;

beforeEach(function () {
    Feature::define('view-beta-reports', false);
    Feature::define('view-new-dashboard', false);

    $this->user = User::create(['name' => 'Alice']);
    $this->other = User::create(['name' => 'Bob']);

    $this->actingAs($this->user);

    Filament::setCurrentPanel('features');

    $this->plugin = FilamentPennantPlugin::get()
        ->authorize(fn (): bool => true)
        ->bulkActions(fn (): bool => true);
});

function overview()
{
    return Livewire::test(FeatureOverview::class);
}

function callBulkDirectly(string $action, string $feature): void
{
    overview()
        ->call('mountAction', $action, [], ['table' => true, 'recordKey' => $feature])
        ->call('callMountedAction');
}

function savedValues(string $feature): array
{
    return DB::table('features')->where('name', $feature)->pluck('value')->all();
}

it('has no bulk actions unless the app opts in', function () {
    $panel = Filament::getPanel('features');

    $panel->plugin(FilamentPennantPlugin::make()->authorize(fn (): bool => true));

    Feature::for($this->user)->deactivate('view-beta-reports');

    overview()
        ->assertTableActionDoesNotExist('turnOnSavedValues')
        ->assertTableActionDoesNotExist('turnOffSavedValues')
        ->assertTableActionDoesNotExist('clearSavedValues');

    callBulkDirectly('turnOnSavedValues', 'view-beta-reports');

    expect(savedValues('view-beta-reports'))->toBe(['false']);
});

it('refuses per feature, including on a direct call', function () {
    $this->plugin->bulkActions(fn (string $feature): bool => $feature !== 'view-beta-reports');

    Feature::for($this->user)->deactivate(['view-beta-reports', 'view-new-dashboard']);

    overview()
        ->assertTableActionHidden('turnOnSavedValues', 'view-beta-reports')
        ->assertTableActionVisible('turnOnSavedValues', 'view-new-dashboard');

    callBulkDirectly('turnOnSavedValues', 'view-beta-reports');
    callBulkDirectly('clearSavedValues', 'view-beta-reports');

    expect(savedValues('view-beta-reports'))->toBe(['false']);
});

it('refuses per user, including on a direct call', function () {
    $this->plugin->bulkActions(fn (string $feature, User $user): bool => $user->name === 'Alice');

    Feature::for($this->user)->deactivate('view-beta-reports');

    $this->actingAs($this->other);

    callBulkDirectly('turnOnSavedValues', 'view-beta-reports');

    expect(savedValues('view-beta-reports'))->toBe(['false']);

    $this->actingAs($this->user);

    callBulkDirectly('turnOnSavedValues', 'view-beta-reports');

    expect(savedValues('view-beta-reports'))->toBe(['true']);
});

it('refuses a bulk action when access is removed while its modal is open', function () {
    Feature::for($this->user)->deactivate('view-beta-reports');

    $component = overview()->call('mountAction', 'turnOnSavedValues', [], ['table' => true, 'recordKey' => 'view-beta-reports']);

    expect($component->get('mountedActions'))->toHaveCount(1);

    $this->plugin->bulkActions(fn (): bool => false);

    $component->call('callMountedAction');

    expect(savedValues('view-beta-reports'))->toBe(['false']);
});

it('cannot change a feature left off the page', function () {
    $this->plugin->features(fn (string $feature): bool => $feature !== 'view-beta-reports');

    Feature::for($this->user)->deactivate('view-beta-reports');

    callBulkDirectly('turnOnSavedValues', 'view-beta-reports');

    expect(savedValues('view-beta-reports'))->toBe(['false']);
});

it('refuses bulk actions for someone who cannot open the page', function () {
    $this->plugin->authorize(fn (): bool => false);

    overview()->assertForbidden();
});

it('changes saved values only and creates none', function () {
    Feature::for($this->user)->deactivate('view-beta-reports');

    overview()
        ->callTableAction('turnOnSavedValues', 'view-beta-reports')
        ->assertNotified('Saved values turned on');

    expect(savedValues('view-beta-reports'))->toBe(['true'])
        ->and(Feature::for($this->other)->active('view-beta-reports'))->toBeFalse();

    Feature::flushCache();
    DB::table('features')->where('scope', 'like', '%' . $this->other->getKey())->delete();

    overview()
        ->callTableAction('turnOffSavedValues', 'view-beta-reports')
        ->assertNotified('Saved values turned off');

    expect(savedValues('view-beta-reports'))->toBe(['false']);
});

it('clears saved values so the rule applies again', function () {
    Feature::define('rule-on', true);
    Feature::for($this->user)->deactivate('rule-on');

    overview()
        ->callTableAction('clearSavedValues', 'rule-on')
        ->assertNotified('Saved values cleared');

    Feature::flushCache();

    expect(savedValues('rule-on'))->toBe([])
        ->and(Feature::for($this->user)->active('rule-on'))->toBeTrue();
});

it('clears a feature that is no longer defined, and offers nothing else for it', function () {
    DB::table('features')->insert([
        'name' => 'removed-feature',
        'scope' => 'a',
        'value' => 'true',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    overview()
        ->assertTableActionHidden('turnOnSavedValues', 'removed-feature')
        ->assertTableActionHidden('turnOffSavedValues', 'removed-feature')
        ->callTableAction('clearSavedValues', 'removed-feature');

    expect(savedValues('removed-feature'))->toBe([]);
});

it('hides bulk actions with no saved values', function () {
    overview()
        ->assertTableActionHidden('turnOnSavedValues', 'view-beta-reports')
        ->assertTableActionHidden('clearSavedValues', 'view-beta-reports');
});

it('hides bulk actions on another store', function () {
    Feature::for($this->user)->deactivate('view-beta-reports');

    config()->set('pennant.default', 'array');

    Feature::define('view-beta-reports', false);

    overview()->assertTableActionHidden('clearSavedValues', 'view-beta-reports');
});

it('dispatches an event with the feature, the action and the user', function () {
    Event::fake([FeatureChangedInBulk::class]);

    Feature::for($this->user)->deactivate('view-beta-reports');

    overview()
        ->callTableAction('turnOnSavedValues', 'view-beta-reports')
        ->callTableAction('turnOffSavedValues', 'view-beta-reports')
        ->callTableAction('clearSavedValues', 'view-beta-reports');

    foreach (['on', 'off', 'clear'] as $type) {
        Event::assertDispatched(
            FeatureChangedInBulk::class,
            fn (FeatureChangedInBulk $event): bool => $event->feature === 'view-beta-reports'
                && $event->action === $type
                && $event->user->getAuthIdentifier() === $this->user->getKey(),
        );
    }
});

it('does not dispatch an event when refused', function () {
    Event::fake([FeatureChangedInBulk::class]);

    $this->plugin->bulkActions(fn (): bool => false);

    Feature::for($this->user)->deactivate('view-beta-reports');

    callBulkDirectly('clearSavedValues', 'view-beta-reports');

    Event::assertNotDispatched(FeatureChangedInBulk::class);
});

it('states exactly what each action does', function () {
    Feature::for($this->user)->deactivate('view-beta-reports');
    Feature::for($this->other)->deactivate('view-beta-reports');

    $mount = fn (string $action) => overview()->mountAction(TestAction::make($action)->table('view-beta-reports'));

    $mount('turnOnSavedValues')
        ->assertMountedActionModalSee('Turn on View Beta Reports for 2 saved records?')
        ->assertMountedActionModalSee('Replaces every saved value, including ones set by hand.');

    $mount('turnOffSavedValues')
        ->assertMountedActionModalSee('Turn off View Beta Reports for 2 saved records?');

    $mount('clearSavedValues')
        ->assertMountedActionModalSee('Clear 2 saved values for View Beta Reports?')
        ->assertMountedActionModalSee('including ones set by hand');
});

it('shows short buttons under a saved values heading', function () {
    Feature::for($this->user)->deactivate('view-beta-reports');

    overview()
        ->assertSeeText('Saved values')
        ->assertTableActionHasLabel('turnOnSavedValues', 'Turn on', 'view-beta-reports')
        ->assertTableActionHasLabel('turnOffSavedValues', 'Turn off', 'view-beta-reports')
        ->assertTableActionHasLabel('clearSavedValues', 'Clear', 'view-beta-reports');
});

it('uses the singular for one saved record', function () {
    Feature::for($this->user)->deactivate('view-beta-reports');

    overview()
        ->mountAction(TestAction::make('turnOnSavedValues')->table('view-beta-reports'))
        ->assertMountedActionModalSee('Turn on View Beta Reports for 1 saved record?');
});

it('never claims to reach everyone', function () {
    expect(strtolower(json_encode(__('filament-pennant::features'))))->not->toContain('everyone');
});

it('explains what happens to records without a saved value', function () {
    overview()->assertSee('Records without a saved value follow the feature');
});
