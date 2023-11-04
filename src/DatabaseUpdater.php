<?php

namespace Onuraycicek\DatabaseUpdater;

use Illuminate\Support\Facades\Log;

class DatabaseUpdater extends BaseManager
{
    /**
     * The database manager instance.
     *
     * @var DatabaseManager
     */
    protected $databaseManager;

    /**
     * The database migration manager instance.
     *
     * @var DatabaseMigrationManager
     */
    protected $databaseMigrationManager;

    /**
     * The database backup instance.
     *
     * @var DatabaseBackup
     */
    protected $databaseBackup;

    /**
     * The database name.
     *
     * @var string
     */
    protected $dbName;

    /**
     * Create a new database updater instance.
     *
     * @return void
     */
    public function __construct($dbName = null, $allValueIsNullable = false)
    {
        if (is_null($dbName)) {
            $dbName = env('DB_DATABASE');
        }

        $this->dbName = $dbName;
        $this->databaseManager = new DatabaseManager($dbName);
        $this->databaseMigrationManager = new DatabaseMigrationManager($dbName, $allValueIsNullable);
        $this->databaseBackup = new DatabaseBackup;
    }

    /**
     * Get the database tables differences.
     *
     * @return array
     */
    private function getDiffColumns(string $table_name, array $columns, array $migrationColumns)
    {
        //check type null key default and extra key
        $differents = [
            'new' => [
                'database' => [],
                'migration' => [],
            ],
            'modify' => [
                'database' => [],
                'migration' => [],
            ],
        ];
        foreach ($migrationColumns as $migrationColumnName => $migrationColumn) {
            if (! isset($columns[$migrationColumnName])) {
                $this->addProcess([
                    'status' => 'info',
                    'reason' => 'Column '.$migrationColumnName.' not found in database',
                ]);
                $differents['new']['database'][] = [
                    'table' => $table_name,
                    'column' => $migrationColumnName,
                    'properties' => $migrationColumn,
                ];

                continue;
            }
            $mergedProperties = array_merge($columns[$migrationColumnName], $migrationColumn);
            $diff = array_diff($migrationColumn, $columns[$migrationColumnName]);
            if (count($diff) > 0) {
                $this->addProcess([
                    'status' => 'process',
                    'reason' => 'Column '.$migrationColumnName.' has different properties',
                    'column' => $migrationColumnName,
                    'column_properties' => [
                        'database' => $columns[$migrationColumnName],
                        'migration' => $migrationColumn,
                    ],
                    'properties' => $mergedProperties,
                ]);
                $differents['modify']['database'][] = [
                    'table' => $table_name,
                    'column' => $migrationColumnName,
                    'properties' => $mergedProperties,
                ];
            }
        }

        foreach ($columns as $columnName => $column) {
            if (! isset($migrationColumns[$columnName])) {
                $this->addProcess([
                    'status' => 'info',
                    'reason' => 'Column '.$columnName.' not found in migration',
                ]);
                $differents['new']['migration'][] = [
                    'table' => $table_name,
                    'column' => $columnName,
                    'properties' => $column,
                ];

                continue;
            }
        }

        return $differents;
    }

    private function getDiffTables($dbName = null)
    {
        $databaseTables = $this->databaseManager->getDbTables($dbName);
        $migrationTables = $this->databaseMigrationManager->getMigrationTables();

        $missingTablesInMigration = array_diff($databaseTables, $migrationTables);
        $missingTablesInDatabase = array_diff($migrationTables, $databaseTables);

        return [
            'database' => $missingTablesInDatabase,
            'migration' => $missingTablesInMigration,
        ];
    }

    public function getAllProcesses()
    {
        $processes = [
            'current' => $this->getProcesses(),
            'migration' => $this->databaseMigrationManager->getProcesses(),
            'database' => $this->databaseManager->getProcesses(),
            'backup' => $this->databaseBackup->getProcesses(),
        ];

        return $processes;
    }

    /**
     * Update the database.
     *
     * @return void
     */
    public function update()
    {
        try {
            //artisan maintenance:up
            $this->databaseManager->maintanceOn();
            // remove new migration files
            $this->databaseMigrationManager->deleteNewMigrationFiles();
            // remove new migration tables
            $this->databaseMigrationManager->deleteNewMigrationTables();
            $backupFilePath = $this->databaseBackup->create();

            $dbName = $this->dbName;
            //check table differences
            //check if all tables inserted in migration table
            $this->databaseMigrationManager->checkMigrationTableMattchCurrenctStatus();
            $diffTables = $this->getDiffTables($dbName);
            if (count($diffTables['database']) > 0) {
                $this->databaseMigrationManager->migrate($diffTables['database']);
            }

            //check column differences
            $oldTableStructure = $this->databaseManager->getDbStructure($dbName);
            $newTableStructure = $this->databaseMigrationManager->getMigrationStructure();

            $diffColumns = [
                'new' => [
                    'database' => [],
                    'migration' => [],
                ],
                'modify' => [
                    'database' => [],
                    'migration' => [],
                ],
            ];
            foreach ($newTableStructure as $newTableName => $newTableColumns) {
                if (! isset($oldTableStructure[$newTableName])) {
                    $this->addProcess([
                        'status' => 'info',
                        'reason' => 'Table '.$newTableName.' not found in database',
                    ]);

                    continue;
                }
                $diff = $this->getDiffColumns($newTableName, $oldTableStructure[$newTableName], $newTableColumns);
                if (count($diff) > 0) {
                    if (isset($diff['new']) && ! empty($diff['new'])) {
                        if (isset($diff['new']['database']) && ! empty($diff['new']['database'])) {
                            $diffColumns['new']['database'] = array_merge($diffColumns['new']['database'], $diff['new']['database']);
                        }
                        if (isset($diff['new']['migration']) && ! empty($diff['new']['migration'])) {
                            $diffColumns['new']['migration'] = array_merge($diffColumns['new']['migration'], $diff['new']['migration']);
                        }
                    }

                    if (isset($diff['modify']) && ! empty($diff['modify'])) {
                        if (isset($diff['modify']['database']) && ! empty($diff['modify']['database'])) {
                            $diffColumns['modify']['database'] = array_merge($diffColumns['modify']['database'], $diff['modify']['database']);
                        }
                        if (isset($diff['modify']['migration']) && ! empty($diff['modify']['migration'])) {
                            $diffColumns['modify']['migration'] = array_merge($diffColumns['modify']['migration'], $diff['modify']['migration']);
                        }
                    }
                }
            }

            //process if table not exists in migration
            foreach ($diffColumns['new']['migration'] as $value) {
                $tableName = $value['table'];
                $columnName = $value['column'];
                $this->databaseMigrationManager->removeColumn($tableName, $columnName);
            }

            //process new columns
            foreach ($diffColumns['new']['database'] as $value) {
                $tableName = $value['table'];
                $columnName = $value['column'];
                $properties = $value['properties'];
                $this->databaseMigrationManager->alterColumn($tableName, $columnName, $properties);
            }

            //process modify columns
            foreach ($diffColumns['modify']['database'] as $value) {
                $tableName = $value['table'];
                $columnName = $value['column'];
                $properties = $value['properties'];
                $exists = true;
                $this->databaseMigrationManager->alterColumn($tableName, $columnName, $properties, $exists);
            }

            $processes = $this->getAllProcesses();
            Log::info([
                'processes' => $processes,
                'changes' => $diffColumns,
            ]);
            $this->databaseManager->maintanceOff();

            return [
                'status' => 'success',
                'processes' => $processes,
                'changes' => $diffColumns,
            ];
        } catch (\Exception $e) {
            dd($e);
            // remove new migration files
            $this->databaseMigrationManager->deleteNewMigrationFiles();
            // remove new migration tables
            $this->databaseMigrationManager->deleteNewMigrationTables();
            $this->databaseManager->restore($backupFilePath);
            $this->databaseManager->maintanceOff();

            $processes = $this->getAllProcesses();
            Log::error([
                'error' => $e->getMessage(),
                'line' => $e->getLine(),
                'file' => $e->getFile(),
                'trace' => $e->getTrace(),
                'error_detailed' => $e,
                'processes' => $processes,
            ]);

            return [
                'status' => 'error',
                'error' => $e->getMessage(),
                'line' => $e->getLine(),
                'file' => $e->getFile(),
                'trace' => $e->getTrace(),
                'message' => $e->getMessage() ?? 'Error while updating database',
                'processes' => $processes,
            ];
        }
        // remove new migration files
        $this->databaseMigrationManager->deleteNewMigrationFiles();
        // remove new migration tables
        $this->databaseMigrationManager->deleteNewMigrationTables();
        $this->databaseManager->maintanceOff();

        return [
            'status' => 'success',
            'processes' => $processes,
            'changes' => $diffColumns,
        ];
    }
}
