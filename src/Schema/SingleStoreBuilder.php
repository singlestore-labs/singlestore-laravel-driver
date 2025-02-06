<?php

namespace SingleStore\Laravel\Schema;

use Closure;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Schema\MySqlBuilder;

class SingleStoreBuilder extends MySqlBuilder
{
    /**
     * @param  string  $table
     */
    protected function createBlueprint($table, ?Closure $callback = null): Blueprint
    {
        // Set the resolver and then call the parent method so that we don't have
        // to duplicate the prefix generation logic. We don't bind our Blueprint
        // into the container in place of the base Blueprint because we might
        // not always be using SingleStore even if the package is installed.
        $this->blueprintResolver(function ($table, $callback, $prefix) {
            return new Blueprint($table, $callback, $prefix);
        });

        return parent::createBlueprint($table, $callback);
    }

    public function getAllTables(): array
    {
        return $this->connection->select(
            'SHOW FULL TABLES WHERE table_type = \'BASE TABLE\''
        );
    }

    /**
     * Drop all tables from the database.
     */
    public function dropAllTables(): void
    {
        $tables = [];

        foreach ($this->getAllTables() as $row) {
            $row = (array) $row;

            $tables[] = reset($row);
        }

        if (empty($tables)) {
            return;
        }

        foreach ($tables as $table) {
            $this->connection->statement(
                $this->grammar->compileDropAllTables([$table])
            );
        }
    }
}
