<?php

namespace Novius\LaravelTranslatable\Tests;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Schema\Blueprint;
use Novius\LaravelTranslatable\LaravelTranslatableServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;

abstract class TestCase extends Orchestra
{
    public function setUp(): void
    {
        parent::setUp();

        Factory::guessFactoryNamesUsing(
            function (string $modelName) {
                return 'Novius\\LaravelTranslatable\\Tests\\Database\\Factories\\'.class_basename($modelName).'Factory';
            }
        );

        $this->setUpDatabase($this->app);
    }

    protected function getPackageProviders($app)
    {
        return [
            LaravelTranslatableServiceProvider::class,
        ];
    }

    public function getEnvironmentSetUp($app)
    {
        $app['config']->set('database.default', 'sqlite');
        $app['config']->set('database.connections.sqlite', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]);
    }

    protected function setUpDatabase($app)
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
    }
}
