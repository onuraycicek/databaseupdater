<?php

namespace Onuraycicek\DatabaseUpdater\Components;

use Illuminate\Contracts\View\View;
use Illuminate\View\Component;

class DatabaseUpdaterResponseComponent extends Component
{
    public function __construct()
    {
    }

    public function render(): View
    {
        return view("databaseupdater::components.database-updater-response");
    }

    public function hasField(string $field): bool
    {
        return config("database-updater-response.fields.{$field}", false);
    }
}