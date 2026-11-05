<?php

namespace Gerenuk\FilamentPennant\Tests\Fixtures;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class PlainRecord extends Model
{
    use HasUuids;

    protected $table = 'users';

    protected $guarded = [];
}
