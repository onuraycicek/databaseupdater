<?php

namespace Onuraycicek\DatabaseUpdater\Commands;

use Illuminate\Console\Command;
use Onuraycicek\DatabaseUpdater\DatabaseUpdater;

class DatabaseUpdaterCommand extends Command
{
    public $signature = 'databaseupdater';

    public $description = 'DatabaseUpdater';

    public function handle()
    {
        $databaseName = config('databaseupdater.database_name');
        $allValueIsNullable = config('databaseupdater.all_value_is_nullable');

        $databaseUpdater = new DatabaseUpdater($databaseName, $allValueIsNullable);
        $response = $databaseUpdater->update();

        // array info
        $this->info('Database Name: '.$databaseName);
        $this->info('All Value Is Nullable: '.$allValueIsNullable);
        $this->info('Response: '.json_encode($response));

        return $response;
    }
}
