<?php

namespace Gerenuk\FilamentPennant\Tests;

use Gerenuk\FilamentPennant\FilamentPennantServiceProvider;
use Gerenuk\FilamentPennant\Tests\Fixtures\FeaturesPanelProvider;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Orchestra\Testbench\TestCase as Orchestra;
use Workbench\App\Models\User;
use Workbench\App\Providers\Filament\AdminPanelProvider;

class TestCase extends Orchestra
{
    protected $enablesPackageDiscoveries = true;

    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('users', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name');
            $table->rememberToken();
            $table->timestamps();
        });

        (include __DIR__ . '/../vendor/laravel/pennant/database/migrations/2022_11_01_000001_create_features_table.php')->up();
    }

    protected function getPackageProviders($app)
    {
        return [
            FilamentPennantServiceProvider::class,
            AdminPanelProvider::class,
            FeaturesPanelProvider::class,
        ];
    }

    public function getEnvironmentSetUp($app)
    {
        config()->set('database.default', 'testing');
        config()->set('app.key', 'base64:' . base64_encode(random_bytes(32)));
        config()->set('auth.providers.users.model', User::class);
    }
}
