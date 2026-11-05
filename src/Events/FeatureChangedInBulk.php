<?php

namespace Gerenuk\FilamentPennant\Events;

use Illuminate\Contracts\Auth\Authenticatable;

class FeatureChangedInBulk
{
    /**
     * @param  'on'|'off'|'clear'  $action
     */
    public function __construct(
        public string $feature,
        public string $action,
        public Authenticatable $user,
    ) {}
}
