<?php

namespace SingleStore\Laravel\Schema;

use Illuminate\Database\Connection;
use Illuminate\Database\Schema\Blueprint as BaseBlueprint;
use Illuminate\Database\Schema\Grammars\Grammar;
use SingleStore\Laravel\Schema\Blueprint\AddsTableFlags;
use SingleStore\Laravel\Schema\Blueprint\InlinesIndexes;
use SingleStore\Laravel\Schema\Blueprint\ModifiesIndexes;

class Blueprint extends BaseBlueprint
{
    use AddsTableFlags, ModifiesIndexes, InlinesIndexes;

    public const INDEX_PLACEHOLDER = '__singlestore_indexes__';

    public function geography($column)
    {
        return $this->addColumn('geography', $column);
    }

    public function geographyPoint($column)
    {
        return $this->point($column);
    }

    public function toSql(Connection $connection, Grammar $grammar)
    {
        $statements = parent::toSql($connection, $grammar);

        return $this->creating() ? $this->inlineCreateIndexStatements($statements) : $statements;
    }
}
