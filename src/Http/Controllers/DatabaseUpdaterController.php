<?php

namespace Onuraycicek\DatabaseUpdater\Http\Controllers;

use Illuminate\Http\Request;
use Onuraycicek\DatabaseUpdater\DatabaseUpdater;

class DatabaseUpdaterController
{
    public function index(Request $request)
    {
        $databaseName = config('databaseupdater.database_name');
        $allValueIsNullable = config('databaseupdater.all_value_is_nullable');

        $databaseUpdater = new DatabaseUpdater($databaseName, $allValueIsNullable);
        $response = $databaseUpdater->update();

        return redirect()->back()->with('response', $response);
    }
}
