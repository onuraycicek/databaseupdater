<?php

namespace Onuraycicek\DatabaseUpdater;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class DatabaseMigrationManager extends BaseManager
{
    private $dbName;

    private $migrationPath;

    private $newMigrationPath;

    private $newBaseMigrationPath;

    private $newMigrationStructure;

    private $allValueIsNullable;

    private $tableNameSuffix = '_XA3Y1A';

    public function __construct($dbName, $allValueIsNullable = false, $migrationPath = null)
    {
        if ($migrationPath == null) {
            $migrationPath = database_path('migrations');
        }
        $newBaseMigrationPath = 'database/migrations/new';
        $this->newMigrationPath = $migrationPath.'/new';
        $this->setMigrationPath($migrationPath);
        $this->newBaseMigrationPath = $newBaseMigrationPath;
        $this->dbName = $dbName;
        $this->allValueIsNullable = $allValueIsNullable;
    }

    public function getMigrationPath()
    {
        return $this->migrationPath;
    }

    public function setMigrationPath($migrationPath)
    {
        $this->migrationPath = $migrationPath;
    }

    public function getNewMigrationPath()
    {
        return $this->newMigrationPath;
    }

    public function getNewBaseMigrationPath()
    {
        return $this->newBaseMigrationPath;
    }

    private function checkFileContent($content, $additionalInformation)
    {
        //$additionalInformation -> file name | tag
        // $exclude = '/class .*? extends Migration/';
        // $check = preg_match($exclude, $content, $matches);
        // if ($check) {
        //     $this->addProcess([
        //         'status' => 'skipped',
        //         'reason' => 'Migration file has class extends Migration',
        //         'file' => $additionalInformation,
        //     ]);

        //     return false;
        // } else {
        return true;
        // }
    }

    public function getMigrationFiles($filtered = false) //filtered = true -> only files that has not exclusive
    {
        $migrationPath = $this->getMigrationPath();
        $files = scandir(database_path('migrations'));
        $migrationFiles = [];
        foreach ($files as $file) {
            if (in_array($file, ['.', '..', 'new'])) {
                continue;
            }
            try {
                $fileContent = file_get_contents($migrationPath.'/'.$file);
                if (strpos($file, '.php') !== false && (! $filtered || $this->checkFileContent($fileContent, $file))) {
                    $migrationFiles[] = $file;
                }
            } catch (\Exception $e) {
                $this->addProcess([
                    'error' => $e->getMessage(),
                    'file' => $file,
                ]);
            }
        }

        return $migrationFiles;
    }

    private function getMigrationTableNamesViaFile($migrationFiles)
    {
        $migrationTables = [];
        $migrationPath = $this->getMigrationPath();
        foreach ($migrationFiles as $file) {
            $fileContent = file_get_contents($migrationPath.'/'.$file);
            $fileContentHasNotExclusive = $this->checkFileContent($fileContent, $file);
            preg_match('/Schema::create\(\'(.*?)\'/', $fileContent, $matches);
            if (count($matches) > 0 && $fileContentHasNotExclusive) {
                $migrationTables[] = $matches[1];
            } else {
                $this->addProcess([
                    'status' => 'info',
                    'reason' => 'Table name not found in migration file',
                    'file' => $file,
                ]);
            }
        }

        return $migrationTables;
    }

    public function getMigrationTables()
    {
        $migrationFiles = $this->getMigrationFiles(true);
        $migrationTables = $this->getMigrationTableNamesViaFile($migrationFiles);

        return $migrationTables;
    }

    private function getMigrationTablesAndFiles()
    {
        $migrationFiles = $this->getMigrationFiles();
        $migrationTablesAndFiles = [];
        foreach ($migrationFiles as $file) {
            $readFile = file_get_contents($this->getMigrationPath().'/'.$file);
            preg_match('/Schema::create\(\'(.*?)\'/', $readFile, $matches);

            if (empty($matches)) {
                preg_match('/\$this->schema->create\(\'(.*?)\'/', $readFile, $matches);
            }

            if (empty($matches)) {
                $this->addProcess([
                    'status' => 'info',
                    'reason' => 'Table name not found in migration file',
                    'file' => $file,
                ]);
            }
            $migrationTablesAndFiles[] = [
                'table_name' => empty($matches) ? null : $matches[1],
                'file_name' => $file,
            ];
        }

        return $migrationTablesAndFiles;
    }

    public function createNewMigrationFiles()
    {
        $newMigrationPath = $this->getNewMigrationPath();

        if (! file_exists($newMigrationPath)) {
            mkdir($newMigrationPath, 0777, true);
        }

        $migrationTablesAndFiles = $this->getMigrationTablesAndFiles();
        foreach ($migrationTablesAndFiles as $data) {
            try {
                $newFileName = str_replace('_table.php', $this->tableNameSuffix.'_table.php', $data['file_name']);
                $newFilePath = $newMigrationPath.'/'.$newFileName;
                $oldFilePath = $this->getMigrationPath().'/'.$data['file_name'];
                $oldFileContent = file_get_contents($oldFilePath);
                $newFileContent = $oldFileContent;
                $newFileContent = preg_replace('/Schema::table\(\'(.*?)\'/', 'Schema::table(\'$1'.$this->tableNameSuffix.'\'', $newFileContent);
                if ($data['table_name']) {
                    $newFileContent = str_replace('Schema::create(\''.$data['table_name'].'\'', 'Schema::create(\''.$data['table_name'].''.$this->tableNameSuffix.'\'', $oldFileContent);
                    $newFileContent = str_replace('Schema::dropIfExists(\''.$data['table_name'].'\'', 'Schema::dropIfExists(\''.$data['table_name'].''.$this->tableNameSuffix.'\'', $newFileContent);
                    $newFileContent = str_replace('Schema::drop(\''.$data['table_name'].'\'', 'Schema::drop(\''.$data['table_name'].''.$this->tableNameSuffix.'\'', $newFileContent);

                    $newFileContent = str_replace('DB::table(\''.$data['table_name'].'\'', 'DB::table(\''.$data['table_name'].''.$this->tableNameSuffix.'\'', $newFileContent);

                    $newFileContent = str_replace('$this->schema->create(\''.$data['table_name'].'\'', '$this->schema->create(\''.$data['table_name'].''.$this->tableNameSuffix.'\'', $newFileContent);
                    $newFileContent = str_replace('$this->schema->dropIfExists(\''.$data['table_name'].'\'', '$this->schema->dropIfExists(\''.$data['table_name'].''.$this->tableNameSuffix.'\'', $newFileContent);
                    $newFileContent = str_replace('$this->schema->drop(\''.$data['table_name'].'\'', '$this->schema->drop(\''.$data['table_name'].''.$this->tableNameSuffix.'\'', $newFileContent);
                }

                $suffixToClassName = str_replace('_', '', $this->tableNameSuffix);
                $newFileContent = str_replace('Table extends Migration', $suffixToClassName.'Table extends Migration', $newFileContent);
                file_put_contents($newFilePath, $newFileContent);
            } catch (\Throwable $th) {
                return response()->json([
                    'error' => $th->getMessage(),
                    'error_detailed' => $th,
                    'processes' => $this->getProcesses(),
                ]);
            }
        }
    }

    public function deleteNewMigrationFiles()
    {
        $this->addProcess([
            'status' => 'info',
            'reason' => 'Deleting new migration files',
        ]);
        $newMigrationPath = $this->getNewMigrationPath();
        if (! file_exists($newMigrationPath)) {
            return;
        }
        $files = scandir($newMigrationPath);
        foreach ($files as $file) {
            if (strpos($file, '.php') !== false && file_exists($newMigrationPath.'/'.$file)) {
                unlink($newMigrationPath.'/'.$file);
            }
        }

        if (file_exists($newMigrationPath)) {
            rmdir($newMigrationPath);
        }
    }

    private function getDbTables()
    {
        $tables = DB::select('SHOW TABLES');
        $dbName = 'Tables_in_'.$this->dbName;
        $tables = array_map(function ($table) use ($dbName) {
            return $table->$dbName;
        }, $tables);

        //filter
        $tables = array_filter($tables, function ($table) {
            return strpos($table, $this->tableNameSuffix) === true;
        });

        return $tables;
    }

    public function deleteNewMigrationTables()
    {
        $this->addProcess([
            'status' => 'info',
            'reason' => 'Deleting new migration tables',
        ]);
        // get all tables
        $newMigrationTables = $this->getDbTables();

        // delete all tables
        foreach ($newMigrationTables as $table) {
            DB::statement('DROP TABLE IF EXISTS `'.$table.'`');
        }
    }

    private function getMigrationColumns($table_name)
    {
        $sm = Schema::getConnection()->getDoctrineSchemaManager();
        $doctrineTable = $sm->introspectTable($table_name);

        $migrationColumns = $doctrineTable->getColumns();
        $migrationColumnsWithProperties = [];
        foreach ($migrationColumns as $columnName => $column) {
            $migrationColumnsWithProperties[$columnName] = [
                'type' => $column->getType()->getName(),
                'length' => $column->getLength(),
                'unsigned' => $column->getUnsigned(),
                'nullable' => ! $column->getNotnull(),
                'default' => $column->getDefault(),
                'autoincrement' => $column->getAutoincrement(),
                'comment' => $column->getComment(),
            ];
        }

        return $migrationColumnsWithProperties;
    }

    private function migrateNewMigrationFiles()
    {
        $newMigrationPath = $this->getNewBaseMigrationPath();
        $migrateCommand = 'migrate --path='.$newMigrationPath;
        $this->addProcess([
            'status' => 'info',
            'reason' => 'Migrating new migration files',
            'command' => $migrateCommand,
        ]);
        $this->callArtisan($migrateCommand);
    }

    private function rollbackNewMigrationFiles()
    {
        $newMigrationPath = $this->getNewBaseMigrationPath();
        $migrateCommand = 'migrate:rollback --path='.$newMigrationPath;
        $this->addProcess([
            'status' => 'info',
            'reason' => 'Rollback new migration files '.$newMigrationPath,
            'command' => $migrateCommand,
        ]);
        $this->callArtisan($migrateCommand);
    }

    public function getMigrationStructure($cache = true)
    {
        if ($cache && $this->newMigrationStructure) {
            return $this->newMigrationStructure;
        }
        $this->deleteNewMigrationFiles();
        $this->deleteNewMigrationTables();

        $this->createNewMigrationFiles();
        $this->migrateNewMigrationFiles();

        $newMigrationStructure = [];
        $newMigrationTables = $this->getMigrationTables();
        foreach ($newMigrationTables as $table_name) {
            $columns = $this->getMigrationColumns($table_name.$this->tableNameSuffix);
            $newMigrationStructure[$table_name] = $columns;
        }
        $this->newMigrationStructure = $newMigrationStructure;

        $this->rollbackNewMigrationFiles();
        $this->deleteNewMigrationFiles();

        return $newMigrationStructure;
    }

    public function migrate($tables = null)
    {
        $this->callArtisan('migrate');
        $this->addProcess([
            'status' => 'success',
            'reason' => 'Migration success',
            'tables' => $tables ?? [],
        ]);
    }

    public function alterColumn($tableName, $columnName, $properties, $columnExists = false)
    {
        DB::connection()->getDoctrineSchemaManager()->getDatabasePlatform()->registerDoctrineTypeMapping('enum', 'string');
        //properties = type, length, unsigned, nullable, default, autoincrement, comment
        if ($properties['type'] == 'bigint') {
            $properties['type'] = 'bigInteger';
        }
        if ($this->allValueIsNullable) {
            $properties['nullable'] = true;
        }
        Schema::table($tableName, function (Blueprint $table) use ($columnName, $properties, $columnExists) {
            if ($columnExists) {
                $table->{$properties['type']}($columnName, $properties['length'])->nullable($properties['nullable'])->default($properties['default'])->comment($properties['comment'])->unsigned($properties['unsigned'])->autoIncrement($properties['autoincrement'])->change();
            } else {
                $table->{$properties['type']}($columnName, $properties['length'])->nullable($properties['nullable'])->default($properties['default'])->comment($properties['comment'])->unsigned($properties['unsigned'])->autoIncrement($properties['autoincrement']);
            }
        });
    }

    public function removeColumn($tableName, $columnName)
    {
        $this->addProcess([
            'status' => 'info',
            'reason' => 'Removing column '.$columnName.' from table '.$tableName,
        ]);
        Schema::table($tableName, function (Blueprint $table) use ($columnName) {
            $table->dropColumn($columnName);
        });
    }

    public function checkMigrationTableMattchCurrenctStatus()
    {
        $this->addProcess([
            'status' => 'info',
            'process' => 'Check migration table match current status',
        ]);

        $migrationTableContent = DB::table('migrations')->get()->toArray();
        $currentMigrationFiles = $this->getMigrationTablesAndFiles();

        foreach ($currentMigrationFiles as $currentMigrationFile) {
            $fileName = $currentMigrationFile['file_name'];
            $tableName = $currentMigrationFile['table_name'];

            $check = array_filter($migrationTableContent, function ($item) use ($fileName) {
                return $item->migration == str_replace('.php', '', $fileName);
            });

            if (count($check) == 0 && Schema::hasTable($tableName)) {
                DB::table('migrations')->insert([
                    'migration' => str_replace('.php', '', $fileName),
                    'batch' => 1,
                ]);
                $this->addProcess([
                    'status' => 'info',
                    'reason' => 'Migration table not match current status',
                    'info' => 'Migration table has '.$fileName.' and '.$tableName.' table',
                    'values' => [
                        'count($check)' => count($check),
                        'Schema::hasTable($tableName)' => Schema::hasTable($tableName) ? 'true' : 'false',
                    ],
                ]);
            } else {
                $this->addProcess([
                    'status' => 'info',
                    'reason' => 'Migration table match current status',
                    'info' => 'Migration table has '.$fileName.' and '.$tableName.' table',
                    'val' => $check,
                    'val2' => Schema::hasTable($tableName) ? 'true' : 'false',
                ]);
            }
        }
    }
}
