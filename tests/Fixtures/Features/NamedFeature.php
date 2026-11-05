<?php

namespace Gerenuk\FilamentPennant\Tests\Fixtures\Features;

class NamedFeature
{
    public string $name = 'named-feature';

    public function resolve(mixed $scope): mixed
    {
        return false;
    }
}
