<?php

namespace Onuraycicek\DatabaseUpdater\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @see \Onuraycicek\DatabaseUpdater\DatabaseUpdater
 */
class DatabaseUpdater extends Facade
{
    protected static function getFacadeAccessor()
    {
        return \Onuraycicek\DatabaseUpdater\DatabaseUpdater::class;
    }
}
