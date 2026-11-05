<?php

namespace Gerenuk\FilamentPennant;

use Closure;
use Filament\Contracts\Plugin;
use Filament\Facades\Filament;
use Filament\Pages\Page;
use Filament\Panel;
use Gerenuk\FilamentPennant\Pages\FeatureOverview;
use Illuminate\Database\Eloquent\Model;

class FilamentPennantPlugin implements Plugin
{
    protected ?Closure $authorizeUsing = null;

    protected ?Closure $bulkActionsUsing = null;

    protected ?Closure $featuresUsing = null;

    /**
     * @var array<class-string<Model>>
     */
    protected array $scopes = [];

    /**
     * @var class-string<Page>
     */
    protected string $page = FeatureOverview::class;

    public function getId(): string
    {
        return 'filament-pennant';
    }

    public function register(Panel $panel): void
    {
        $panel->pages([$this->page]);
    }

    public function boot(Panel $panel): void {}

    public static function make(): static
    {
        return app(static::class);
    }

    public static function get(): static
    {
        /** @var static $plugin */
        $plugin = Filament::getPlugin(app(static::class)->getId());

        return $plugin;
    }

    /**
     * Replaces the page. Extend FeatureOverview to keep its access checks.
     *
     * @param  class-string<Page>  $page
     */
    public function page(string $page): static
    {
        $this->page = $page;

        return $this;
    }

    /**
     * Nobody can open the page until this is set.
     */
    public function authorize(Closure $callback): static
    {
        $this->authorizeUsing = $callback;

        return $this;
    }

    /**
     * Leaves features off the page. This is separate from FeatureAccess, which needs a record.
     */
    public function features(Closure $callback): static
    {
        $this->featuresUsing = $callback;

        return $this;
    }

    /**
     * Bulk actions don't exist until this is set.
     */
    public function bulkActions(Closure $callback): static
    {
        $this->bulkActionsUsing = $callback;

        return $this;
    }

    public function hasBulkActions(): bool
    {
        return $this->bulkActionsUsing !== null;
    }

    public function allowsBulkActions(string $feature): bool
    {
        $user = Filament::auth()->user();

        return $user !== null
            && $this->bulkActionsUsing !== null
            && (bool) ($this->bulkActionsUsing)($feature, $user);
    }

    /**
     * @param  array<class-string<Model>>  $scopes
     */
    public function scopes(array $scopes): static
    {
        $this->scopes = $scopes;

        return $this;
    }

    /**
     * @return array<class-string<Model>>
     */
    public function getScopes(): array
    {
        return $this->scopes;
    }

    public function canAccess(): bool
    {
        $user = Filament::auth()->user();

        return $user !== null
            && $this->authorizeUsing !== null
            && (bool) ($this->authorizeUsing)($user);
    }

    public function allowsFeature(string $feature): bool
    {
        if ($this->featuresUsing === null) {
            return true;
        }

        $user = Filament::auth()->user();

        return $user !== null && (bool) ($this->featuresUsing)($feature, $user);
    }
}
