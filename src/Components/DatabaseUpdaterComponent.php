<?php

namespace Onuraycicek\DatabaseUpdater\Components;

use Illuminate\Contracts\View\View;
use Illuminate\View\Component;

class DatabaseUpdaterComponent extends Component
{
    public function __construct()
    {
    }

    public function render(): View
    {
        return view("databaseupdater::components.database-updater");
    }

    public function hasField(string $field): bool
    {
        return config("database-updater.fields.{$field}", false);
    }
}