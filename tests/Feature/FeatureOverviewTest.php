<?php

use Filament\Facades\Filament;
use Filament\Panel;
use Gerenuk\FilamentPennant\FilamentPennantPlugin;
use Gerenuk\FilamentPennant\Pages\FeatureOverview;
use Gerenuk\FilamentPennant\Tests\Fixtures\CustomFeatureOverview;
use Gerenuk\FilamentPennant\Tests\Fixtures\PlainRecord;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Laravel\Pennant\Feature;
use Livewire\Livewire;
use Workbench\App\Models\User;

beforeEach(function () {
    Feature::define('view-beta-reports', false);
    Feature::define('view-new-dashboard', false);

    $this->user = User::create(['name' => 'Alice']);

    $this->actingAs($this->user);

    Filament::setCurrentPanel('features');

    $this->plugin = FilamentPennantPlugin::get();
});

function saveFeatureRow(string $name, string $scope, string $value, string $table = 'features'): void
{
    DB::table($table)->insert([
        'name' => $name,
        'scope' => $scope,
        'value' => $value,
        'created_at' => now(),
        'updated_at' => now(),
    ]);
}

it('is not on a panel without the plugin', function () {
    expect(Filament::getPanel('admin')->getPages())->not->toContain(FeatureOverview::class)
        ->and(Filament::getPanel('features')->getPages())->toContain(FeatureOverview::class);
});

it('registers a replacement page instead of the default one', function () {
    $pages = Panel::make()
        ->id('replacement')
        ->plugin(FilamentPennantPlugin::make()->page(CustomFeatureOverview::class))
        ->getPages();

    expect($pages)->toContain(CustomFeatureOverview::class)
        ->not->toContain(FeatureOverview::class);
});

it('lets a replacement page change the table and keep the access checks', function () {
    Livewire::test(CustomFeatureOverview::class)->assertForbidden();

    $this->plugin
        ->authorize(fn (): bool => true)
        ->bulkActions(fn (): bool => true);

    Feature::for($this->user)->deactivate('view-beta-reports');

    Livewire::test(CustomFeatureOverview::class)
        ->assertSee('Custom heading')
        ->assertSee('View Beta Reports')
        ->assertDontSee('1 off')
        ->assertTableActionDoesNotExist('clearSavedValues');
});

it('cannot be opened until authorize is set', function () {
    Livewire::test(FeatureOverview::class)->assertForbidden();

    FeatureOverview::registerNavigationItems();

    expect(Filament::getPanel('features')->getNavigationItems())->toBeEmpty();
});

it('allows and refuses users through authorize', function () {
    $this->plugin->authorize(fn (User $user): bool => $user->name === 'Alice');

    Livewire::test(FeatureOverview::class)->assertOk();

    FeatureOverview::registerNavigationItems();

    expect(Filament::getPanel('features')->getNavigationItems())->toHaveCount(1);

    $this->actingAs(User::create(['name' => 'Bob']));

    Livewire::test(FeatureOverview::class)->assertForbidden();
});

it('refuses a guest', function () {
    $this->plugin->authorize(fn (): bool => true);

    Filament::auth()->logout();

    expect($this->plugin->canAccess())->toBeFalse();
});

it('shows saved on and off counts, with rich values as on', function () {
    $this->plugin->authorize(fn (): bool => true);

    saveFeatureRow('view-beta-reports', 'a', 'true');
    saveFeatureRow('view-beta-reports', 'b', '{"tier":"gold"}');
    saveFeatureRow('view-beta-reports', 'c', 'false');

    Livewire::test(FeatureOverview::class)
        ->assertSee('View Beta Reports')
        ->assertSeeHtml('2 on &middot; 1 off')
        ->assertSeeHtml('width: 66.7%')
        ->assertSee('No saved values');
});

it('runs no rules and saves nothing', function () {
    $ran = false;

    Feature::define('rule-feature', function () use (&$ran): bool {
        $ran = true;

        return true;
    });

    $this->plugin->authorize(fn (): bool => true);

    Livewire::test(FeatureOverview::class)->assertSee('Rule Feature');

    expect($ran)->toBeFalse()
        ->and(DB::table('features')->count())->toBe(0);
});

it('leaves features off the page', function () {
    $this->plugin
        ->authorize(fn (): bool => true)
        ->features(fn (string $feature, User $user): bool => $feature !== 'view-beta-reports');

    Livewire::test(FeatureOverview::class)
        ->assertDontSee('View Beta Reports')
        ->assertSee('View New Dashboard');
});

it('marks saved features that are no longer defined', function () {
    $this->plugin->authorize(fn (): bool => true);

    saveFeatureRow('removed-feature', 'a', 'true');

    Livewire::test(FeatureOverview::class)
        ->assertSee('Removed Feature')
        ->assertSee('No longer defined')
        ->assertSeeHtml('1 on &middot; 0 off');
});

it('shows which models a feature applies to only when scopes are set', function () {
    Feature::define('users-only', fn (User $user): bool => false);
    Feature::define('records-only', fn (PlainRecord $record): bool => false);

    $this->plugin->authorize(fn (): bool => true);

    Livewire::test(FeatureOverview::class)->assertTableColumnHidden('applies_to');

    $this->plugin->scopes([User::class, PlainRecord::class]);

    Livewire::test(FeatureOverview::class)
        ->assertTableColumnVisible('applies_to')
        ->assertTableColumnStateSet('applies_to', ['User'], 'users-only')
        ->assertTableColumnStateSet('applies_to', ['Plain record'], 'records-only')
        ->assertTableColumnStateSet('applies_to', ['User', 'Plain record'], 'view-beta-reports');
});

it('lists features without counts on another store', function () {
    config()->set('pennant.default', 'array');

    Feature::define('array-feature', false);

    $this->plugin->authorize(fn (): bool => true);

    Livewire::test(FeatureOverview::class)
        ->assertSee('Array Feature')
        ->assertTableColumnHidden('saved');
});

it('uses the table set for the store', function () {
    Schema::create('custom_features', function (Blueprint $table) {
        $table->id();
        $table->string('name');
        $table->string('scope');
        $table->text('value');
        $table->timestamps();
    });

    config()->set('pennant.stores.database.table', 'custom_features');

    saveFeatureRow('view-beta-reports', 'a', 'true', 'custom_features');

    $this->plugin->authorize(fn (): bool => true);

    Livewire::test(FeatureOverview::class)
        ->assertSeeHtml('1 on &middot; 0 off');
});
