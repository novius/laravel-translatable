<?php

namespace Novius\LaravelTranslatable\Tests;

use Illuminate\Database\Schema\Blueprint;
use Orchestra\Testbench\Concerns\WithWorkbench;
use Orchestra\Testbench\TestCase as Orchestra;

abstract class TestCase extends Orchestra
{
    use WithWorkbench;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpDatabase($this->app);

        $this->artisan('lang:add', ['locales' => 'fr']);
        $this->artisan('lang:add', ['locales' => 'en']);
    }

    protected function setUpDatabase($app): void
    {
        $this->loadLaravelMigrations();

        $app['db']
            ->connection()
            ->getSchemaBuilder()
            ->create('translatable_models', function (Blueprint $table) {
                $table->increments('id');
                $table->string('title');
                $table->timestamps();
                $table->translatable();
            });

        $app['db']
            ->connection()
            ->getSchemaBuilder()
            ->create('custom_columns_translatable_models', function (Blueprint $table) {
                $table->increments('id');
                $table->string('title');
                $table->timestamps();
                $table->translatable('lang', 'lang_parent_id');
            });

        $app['db']
            ->connection()
            ->getSchemaBuilder()
            ->create('soft_deletable_translatable_models', function (Blueprint $table) {
                $table->increments('id');
                $table->string('title');
                $table->timestamps();
                $table->softDeletes();
                $table->translatable();
            });

        $schema = $app['db']->connection()->getSchemaBuilder();
        $schema->create('product_commons', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('position')->nullable();
            $table->timestamps();
        });
        foreach (['products', 'soft_deletable_products'] as $tableName) {
            $schema->create($tableName, function (Blueprint $table) use ($tableName) {
                $table->increments('id');
                $table->string('title');
                $table->unsignedInteger('product_common_id')->nullable();
                $table->timestamps();
                if ($tableName === 'soft_deletable_products') {
                    $table->softDeletes();
                }
                $table->translatable();
            });
        }
    }
}
