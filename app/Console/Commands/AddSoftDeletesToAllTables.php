<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;

class AddSoftDeletesToAllTables extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'add-soft-deletes:all';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Add soft deletes to all tables';

    /**
     * Execute the console command.
     *
     * @return int
     */
    protected $excludedTables = [
        'logs',
        'failed_jobs',
        'migrations',
        'password_resets',
        'temp_sejels'
    ];
    public function handle()
    {
        // Get all table names using Laravel's database introspection
        $databaseName = \DB::getDatabaseName();
        $tables = [];

        try {
            // Try MySQL/MariaDB approach first
            if (\DB::getDriverName() === 'mysql') {
                $tables = \DB::select("SELECT table_name FROM information_schema.tables WHERE table_schema = '{$databaseName}'");
            }
            // Try PostgreSQL approach
            elseif (\DB::getDriverName() === 'pgsql') {
                $tables = \DB::select("SELECT table_name FROM information_schema.tables WHERE table_schema = 'public'");
            }
            // Try SQLite approach
            elseif (\DB::getDriverName() === 'sqlite') {
                $tables = \DB::select("SELECT name as table_name FROM sqlite_master WHERE type='table'");
            }
        } catch (\Exception $e) {
            $this->error('Unable to retrieve table list: ' . $e->getMessage());
            return 1;
        }

        foreach ($tables as $tableInfo) {
            $tableName = $tableInfo->table_name ?? $tableInfo->name;

            if (!in_array($tableName, $this->excludedTables)) {
                try {
                    Schema::table($tableName, function (Blueprint $table) {
                        if (!Schema::hasColumn($table->getTable(), 'deleted_at')) {
                            $table->softDeletes();
                            $this->info("Soft deletes added to table: {$table->getTable()}");
                        }
                        if (!Schema::hasColumn($table->getTable(), 'deleted_by')) {
                            // Check if users table exists and get the id column type
                            if (Schema::hasTable('users')) {
                                // For now, use string type to match most common setups
                                $table->string('deleted_by', 36)->nullable();
                                $table->foreign('deleted_by')->references('id')->on('users')->onDelete('set null');
                            } else {
                                $table->string('deleted_by', 36)->nullable();
                            }
                            $this->info("deleted_by column added to table: {$table->getTable()}");
                        }
                    });
                } catch (\Exception $e) {
                    $this->warn("Could not modify table {$tableName}: " . $e->getMessage());
                }
            }
        }

        $this->info('Soft deletes migration completed!');
        return 0;
    }
}
