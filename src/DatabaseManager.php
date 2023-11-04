<?php

namespace Onuraycicek\DatabaseUpdater;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class DatabaseManager extends BaseManager
{
    use processes;

    private $newMigrationStructure;

    public function __construct()
    {
    }

    public function getDbTables($dbName)
    {
        $tables = DB::select('SHOW TABLES');
        $dbName = 'Tables_in_'.$dbName;
        $tables = array_map(function ($table) use ($dbName) {
            return $table->$dbName;
        }, $tables);

        return $tables;
    }

    private function getDbColumns($table_name)
    {
        // $columns = DB::select('SHOW COLUMNS FROM `' . $table_name . '`');
        // $columnsWithProperties = [];
        // foreach ($columns as $column) {
        // 	$columnsWithProperties[$column->Field] = [
        // 		'type' => $column->Type,
        // 		'null' => $column->Null,
        // 		'key' => $column->Key,
        // 		'default' => $column->Default,
        // 		'extra' => $column->Extra,
        // 	];
        // }
        // return $columnsWithProperties;
        $smt = Schema::getConnection()->getDoctrineSchemaManager();
        $smt->getDatabasePlatform()->registerDoctrineTypeMapping('enum', 'string');

        $doctrineTable = $smt->introspectTable($table_name);
        $columns = $doctrineTable->getColumns();
        $columnsWithProperties = [];
        foreach ($columns as $column) {
            $columnsWithProperties[$column->getName()] = [
                'type' => $column->getType()->getName(),
                'length' => $column->getLength(),
                'unsigned' => $column->getUnsigned(),
                'nullable' => ! $column->getNotnull(),
                'default' => $column->getDefault(),
                'autoincrement' => $column->getAutoincrement(),
                'comment' => $column->getComment(),
            ];
        }

        return $columnsWithProperties;
    }

    public function getDbStructure($dbName)
    {
        $tables = $this->getDbTables($dbName);
        $structure = [];
        foreach ($tables as $table) {
            $structure[$table] = $this->getDbColumns($table);
        }

        return $structure;
    }

    public function maintanceOn()
    {
        $this->callArtisan('down');
        $this->addProcess([
            'status' => 'info',
            'reason' => 'Maintenance mode on',
        ]);
    }

    public function maintanceOff()
    {
        $this->callArtisan('up');
        $this->addProcess([
            'status' => 'info',
            'reason' => 'Maintenance mode off',
        ]);
    }

    public function restore($file)
    {
        $this->addProcess([
            'status' => 'info',
            'process' => 'Restore database from '.$file,
        ]);

        $dbUsername = escapeshellarg(env('DB_USERNAME'));
        $dbPassword = escapeshellarg(env('DB_PASSWORD'));
        $dbDatabase = escapeshellarg(env('DB_DATABASE'));
        $filePath = escapeshellarg($file);

        $command = 'mysql -u '.$dbUsername.' -p'.$dbPassword.' '.$dbDatabase.' < '.$filePath;
        $output = shell_exec($command);
        $this->addProcess([
            'status' => 'info',
            'reason' => 'Database restored',
            'output' => $output,
            'command' => $command,
        ]);
    }
}
