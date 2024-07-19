<?php

namespace SingleStore\Laravel\Tests\Hybrid;

use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Schema;
use SingleStore\Laravel\Schema\Blueprint;
use SingleStore\Laravel\Tests\BaseTest;

class ChangeColumnTest extends BaseTest
{
    use HybridTestHelpers;

    protected function setUp(): void
    {
        parent::setUp();

        if ($this->runHybridIntegrations()) {
            $this->createTable(function (Blueprint $table) {
                $table->id();
            });
        }
    }

    protected function tearDown(): void
    {
        if ($this->runHybridIntegrations()) {
            Schema::dropIfExists('test_renamed');
        }

        parent::tearDown();
    }

    /** @test */
    public function change_column_on_rowstore_table()
    {
        if (version_compare(Application::VERSION, '10.0', '<')) {
            $this->markTestSkipped('requires higher laravel version');
        }

        if ($this->runHybridIntegrations()) {
            $cached = $this->mockDatabaseConnection;

            $this->mockDatabaseConnection = false;

            $this->createTable(function (Blueprint $table) {
                $table->rowstore();
                $table->id();
                $table->string('data');
            });

            Schema::table('test', function (Blueprint $table) {
                $table->text('data')->nullable()->change();
            });

            $this->assertEquals(['id', 'data'], Schema::getColumnListing('test'));
            $this->assertEquals('text', Schema::getColumnType('test', 'data'));

            $this->mockDatabaseConnection = $cached;
        }

        $blueprint = new Blueprint('test');
        $blueprint->text('data')->nullable()->change();

        $connection = $this->getConnection();
        $connection->shouldReceive('getDatabaseName')->andReturn('database');
        $connection->shouldReceive('scalar')
            ->with("select exists (select 1 from information_schema.tables where table_schema = 'database' and table_name = 'test' and storage_type = 'COLUMNSTORE') as is_columnstore")
            ->andReturn(0);
        $connection->shouldReceive('usingNativeSchemaOperations')->andReturn(true);
        $statements = $blueprint->toSql($connection, $this->getGrammar());

        $this->assertCount(1, $statements);
        $this->assertEquals('alter table `test` modify `data` text null', $statements[0]);
    }

    /** @test */
    public function change_column_of_columnstore_table()
    {
        if (version_compare(Application::VERSION, '11.15', '<')) {
            $this->markTestSkipped('requires higher laravel version');
        }

        if ($this->runHybridIntegrations()) {
            $cached = $this->mockDatabaseConnection;

            $this->mockDatabaseConnection = false;

            $this->createTable(function (Blueprint $table) {
                $table->id();
                $table->string('data');
            });

            Schema::table('test', function (Blueprint $table) {
                $table->text('data')->nullable()->change();
            });

            $this->assertEquals(['id', 'data'], Schema::getColumnListing('test'));
            $this->assertEquals('text', Schema::getColumnType('test', 'data'));

            $this->mockDatabaseConnection = $cached;
        }

        $blueprint = new Blueprint('test');
        $blueprint->text('data')->nullable()->change();

        $connection = $this->getConnection();
        $connection->shouldReceive('getDatabaseName')->andReturn('database');
        $connection->shouldReceive('scalar')
            ->with("select exists (select 1 from information_schema.tables where table_schema = 'database' and table_name = 'test' and storage_type = 'COLUMNSTORE') as is_columnstore")
            ->andReturn(1);

        $statements = $blueprint->toSql($connection, $this->getGrammar());

        $this->assertCount(4, $statements);
        $this->assertEquals([
            'alter table `test` add `__temp__data` text null after `data`',
            'update `test` set `__temp__data` = `data`',
            'alter table `test` drop `data`',
            'alter table `test` change `__temp__data` `data`',
        ], $statements);
    }
}
