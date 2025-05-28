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

    public function getEnvironmentSetUp($app): void
    {
        $app['config']->set('database.default', 'sqlite');
        $app['config']->set('database.connections.sqlite', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]);
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
    }
}
