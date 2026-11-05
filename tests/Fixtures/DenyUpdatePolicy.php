<?php

namespace Gerenuk\FilamentPennant\Tests\Fixtures;

class DenyUpdatePolicy
{
    public function update(): bool
    {
        return false;
    }
}
