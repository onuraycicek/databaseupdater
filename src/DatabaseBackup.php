<?php
namespace Onuraycicek\DatabaseUpdater;
use Carbon\Carbon;
use Illuminate\Support\Facades\File;

class DatabaseBackup extends BaseManager
{
    public function create()
    {
        $filename = "backup-" . Carbon::now()->format('Y-m-d-H-i-s') . ".sql";
        $storageAt = config('databaseupdater.backup.path');
        if(!File::exists($storageAt)) {
            File::makeDirectory($storageAt, 0755, true, true);
        }

        $command = config('databaseupdater.backup.command', 'musqldump');
        $username = config('databaseupdater.backup.username', env('DB_USERNAME'));
        $password = config('databaseupdater.backup.password', env('DB_PASSWORD'));
        $host = config('databaseupdater.backup.host', env('DB_HOST'));
        $database = config('databaseupdater.backup.database', env('DB_DATABASE'));
    
        $command = "$command --user=$username --password=$password --host=$host $database > '$storageAt$filename'";
        $returnVar = NULL;
        $output = NULL;
        exec($command, $output, $returnVar);

        $process = [
            'process' => 'DatabaseBackup',
            'command' => str_replace($password, '********', $command),
            'output' => $output,
            'returnVar' => $returnVar,
        ];
        $this->addProcess($process);

        return $storageAt . $filename;
    }
}